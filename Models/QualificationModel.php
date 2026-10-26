<?php

namespace tournoi_enoa\Models;

use tournoi_enoa\Core\DbConnect;

/**
 * Génère et planifie les matchs de qualification (round-robin par poule) : chaque poule
 * joue un tour complet aller simple (méthode du cercle), et les rounds de toutes les
 * poules qui n'ont pas encore débuté en même temps (round 1 de la poule A en même temps
 * que le round 1 de la poule B, etc.) sont répartis sur les terrains disponibles.
 */
class QualificationModel extends DbConnect
{
    /** Y a-t-il déjà un vrai résultat de qualification ? Sert à protéger une régénération. */
    public function hasResults(int $tournamentId): bool
    {
        $query = $this->connection->prepare(
            "SELECT COUNT(*) FROM matches
             WHERE tournament_id = :tournament_id AND phase = 'qualification' AND status IN ('live', 'finished')"
        );
        $query->execute(['tournament_id' => $tournamentId]);
        return ((int) $query->fetchColumn()) > 0;
    }

    /** Supprime les matchs de qualification déjà générés (à n'appeler que si hasResults() est faux). */
    public function clear(int $tournamentId): void
    {
        $query = $this->connection->prepare("DELETE FROM matches WHERE tournament_id = :tournament_id AND phase = 'qualification'");
        $query->execute(['tournament_id' => $tournamentId]);
    }

    /**
     * Attribue un terrain aux matchs de qualification qui n'en ont jamais reçu, quel que
     * soit leur statut — typiquement des matchs saisis avant que les horaires/terrains du
     * tournoi ne soient finalisés (scheduleQualification() ne replanifie que les matchs
     * encore "pending", pour ne jamais déplacer un match déjà en cours/joué). Ne touche
     * jamais un match qui a déjà un terrain, même déjà joué : répartit juste les terrains
     * actifs à tour de rôle par ordre de match_number, sans horaire (déjà passé).
     *
     * @return int nombre de matchs mis à jour
     */
    public function assignMissingCourts(int $tournamentId): int
    {
        $matchesQuery = $this->connection->prepare(
            "SELECT id FROM matches WHERE tournament_id = :tournament_id AND phase = 'qualification' AND court_id IS NULL ORDER BY match_number"
        );
        $matchesQuery->execute(['tournament_id' => $tournamentId]);
        $matchIds = $matchesQuery->fetchAll(\PDO::FETCH_COLUMN);
        if (!$matchIds) {
            return 0;
        }

        $courtsQuery = $this->connection->prepare('SELECT id FROM courts WHERE tournament_id = :tournament_id AND actif = 1 ORDER BY numero');
        $courtsQuery->execute(['tournament_id' => $tournamentId]);
        $courtIds = $courtsQuery->fetchAll(\PDO::FETCH_COLUMN);
        if (!$courtIds) {
            return 0;
        }

        $update = $this->connection->prepare('UPDATE matches SET court_id = :court_id WHERE id = :id');
        foreach (array_values($matchIds) as $index => $matchId) {
            $update->execute(['court_id' => $courtIds[$index % count($courtIds)], 'id' => $matchId]);
        }

        return count($matchIds);
    }

    /**
     * Génère le tour complet de chaque poule puis planifie tous les matchs (terrain +
     * horaire) d'après les réglages du tournoi. Sans effet si aucune poule n'a au moins
     * 2 équipes, ou si les horaires/terrains ne sont pas encore configurés.
     *
     * @param array<int,int[]> $poolTeamIds équipes de chaque poule, indexées par pool_id
     */
    public function generate(int $tournamentId, array $poolTeamIds): void
    {
        $roundsByPool = [];
        $maxRounds = 0;
        foreach ($poolTeamIds as $poolId => $teamIds) {
            $rounds = $this->circleRoundRobin(array_values($teamIds));
            if ($rounds) {
                $roundsByPool[$poolId] = $rounds;
                $maxRounds = max($maxRounds, count($rounds));
            }
        }

        if (!$roundsByPool) {
            return;
        }

        $this->connection->beginTransaction();
        try {
            $matchNumber = $this->nextMatchNumber($tournamentId);
            $insert = $this->connection->prepare(
                'INSERT INTO matches (tournament_id, phase, pool_id, round_number, match_number, team_a_id, team_b_id, status)
                 VALUES (:tournament_id, "qualification", :pool_id, :round_number, :match_number, :team_a_id, :team_b_id, "pending")'
            );

            for ($round = 0; $round < $maxRounds; $round++) {
                foreach ($roundsByPool as $poolId => $rounds) {
                    foreach ($rounds[$round] ?? [] as [$teamA, $teamB]) {
                        $insert->execute([
                            'tournament_id' => $tournamentId,
                            'pool_id'       => $poolId,
                            'round_number'  => $round + 1,
                            'match_number'  => $matchNumber++,
                            'team_a_id'     => $teamA,
                            'team_b_id'     => $teamB,
                        ]);
                    }
                }
            }

            $this->scheduleQualification($tournamentId);

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    /**
     * Affecte un terrain et un horaire à tous les matchs de qualification pas encore
     * joués (nouvellement générés, ou déjà en place si les réglages ont changé depuis).
     * Contrairement à la phase finale (BracketModel::scheduleFinalPhase), les rounds de
     * qualification n'ont aucune dépendance entre eux : toutes les rencontres sont déjà
     * connues à l'avance (round-robin), seule la disponibilité de chaque équipe et de
     * chaque terrain compte. On planifie donc chaque match, dans l'ordre (round, poule,
     * numéro), sur le terrain qui permet de démarrer au plus tôt — dès que ses deux
     * équipes ET ce terrain sont libres — plutôt que d'attendre qu'un round entier soit
     * terminé sur tous les terrains avant de commencer le suivant. Sans ça, dès qu'un
     * round contient moins de matchs que de terrains actifs (poule à effectif impair,
     * poule ayant fini ses rounds avant les autres...), un ou plusieurs terrains restent
     * inutilisés pendant que le match suivant — dont les équipes sont pourtant déjà
     * libres — attend inutilement le round suivant. Sans effet si les horaires/terrains
     * ne sont pas configurés, ou si aucun match de qualification n'est en attente.
     */
    public function scheduleQualification(int $tournamentId): void
    {
        $matchesQuery = $this->connection->prepare(
            "SELECT id, team_a_id, team_b_id FROM matches
             WHERE tournament_id = :tournament_id AND phase = 'qualification' AND status = 'pending'
             ORDER BY round_number, pool_id, match_number"
        );
        $matchesQuery->execute(['tournament_id' => $tournamentId]);
        $matches = $matchesQuery->fetchAll();
        if (!$matches) {
            return;
        }

        $tournamentQuery = $this->connection->prepare('SELECT date_tournoi FROM tournaments WHERE id = :id');
        $tournamentQuery->execute(['id' => $tournamentId]);
        $dateTournoi = $tournamentQuery->fetchColumn();

        $settingsQuery = $this->connection->prepare(
            'SELECT heure_debut_qualifications, duree_match_qualification, temps_entre_matchs_qualification, nombre_terrains
             FROM tournament_settings WHERE tournament_id = :tournament_id'
        );
        $settingsQuery->execute(['tournament_id' => $tournamentId]);
        $settings = $settingsQuery->fetch();

        if (!$dateTournoi || !$settings || !$settings->heure_debut_qualifications || !$settings->duree_match_qualification) {
            return; // pas encore configuré : les matchs restent sans horaire
        }

        $courtsQuery = $this->connection->prepare(
            'SELECT id FROM courts WHERE tournament_id = :tournament_id AND actif = 1 ORDER BY numero LIMIT :limit'
        );
        $courtsQuery->bindValue('tournament_id', $tournamentId, \PDO::PARAM_INT);
        $courtsQuery->bindValue('limit', max(1, (int) $settings->nombre_terrains), \PDO::PARAM_INT);
        $courtsQuery->execute();
        $courtIds = $courtsQuery->fetchAll(\PDO::FETCH_COLUMN);
        if (!$courtIds) {
            return; // aucun terrain actif à assigner
        }
        $courtCount = count($courtIds);

        $dureeMatch = (int) $settings->duree_match_qualification;
        $gap = (int) $settings->temps_entre_matchs_qualification;
        $start = new \DateTimeImmutable($dateTournoi . ' ' . $settings->heure_debut_qualifications);

        $updateMatch = $this->connection->prepare(
            'UPDATE matches SET court_id = :court_id, scheduled_start = :start, scheduled_end = :end, duration_seconds = :duration WHERE id = :id'
        );

        // Prochain instant où chaque terrain / équipe est libre (tous disponibles dès le
        // début des qualifications au départ).
        $courtFreeAt = array_fill(0, $courtCount, $start);
        $teamFreeAt = [];

        foreach ($matches as $match) {
            $teamA = (int) $match->team_a_id;
            $teamB = (int) $match->team_b_id;
            $readyAt = max($teamFreeAt[$teamA] ?? $start, $teamFreeAt[$teamB] ?? $start);

            // Terrain permettant de démarrer le plus tôt ; à égalité, le premier dans
            // l'ordre des terrains (celui qui attend depuis le plus longtemps se retrouve
            // devant dès que l'égalité se rompt, ce qui répartit l'usage sur tout le tournoi).
            $bestCourt = 0;
            $bestStart = max($readyAt, $courtFreeAt[0]);
            for ($c = 1; $c < $courtCount; $c++) {
                $candidate = max($readyAt, $courtFreeAt[$c]);
                if ($candidate < $bestStart) {
                    $bestStart = $candidate;
                    $bestCourt = $c;
                }
            }

            $end = $bestStart->modify("+{$dureeMatch} minutes");
            $updateMatch->execute([
                'court_id' => $courtIds[$bestCourt],
                'start'    => $bestStart->format('Y-m-d H:i:s'),
                'end'      => $end->format('Y-m-d H:i:s'),
                'duration' => $dureeMatch * 60,
                'id'       => $match->id,
            ]);

            $nextFree = $end->modify("+{$gap} minutes");
            $courtFreeAt[$bestCourt] = $nextFree;
            $teamFreeAt[$teamA] = $nextFree;
            $teamFreeAt[$teamB] = $nextFree;
        }
    }

    /**
     * Tour complet aller simple d'une poule (méthode du cercle) : pour n équipes (n
     * complété par une exempte si impair), n-1 rounds où chaque équipe joue une fois.
     *
     * @param int[] $teamIds
     * @return array<int, array<int, array{0:int,1:int}>> rounds -> paires [team_a, team_b]
     */
    private function circleRoundRobin(array $teamIds): array
    {
        if (count($teamIds) < 2) {
            return [];
        }

        $teams = $teamIds;
        if (count($teams) % 2 !== 0) {
            $teams[] = null; // exempte
        }
        $n = count($teams);

        $rounds = [];
        for ($r = 0; $r < $n - 1; $r++) {
            $round = [];
            for ($i = 0; $i < $n / 2; $i++) {
                $teamA = $teams[$i];
                $teamB = $teams[$n - 1 - $i];
                if ($teamA !== null && $teamB !== null) {
                    $round[] = [$teamA, $teamB];
                }
            }
            $rounds[] = $round;

            // Rotation : la première équipe reste fixe, les autres tournent d'un cran.
            $fixed = $teams[0];
            $rest = array_slice($teams, 1);
            array_unshift($rest, array_pop($rest));
            $teams = array_merge([$fixed], $rest);
        }

        return $rounds;
    }

    private function nextMatchNumber(int $tournamentId): int
    {
        $query = $this->connection->prepare('SELECT COALESCE(MAX(match_number), 0) + 1 FROM matches WHERE tournament_id = :tournament_id');
        $query->execute(['tournament_id' => $tournamentId]);
        return (int) $query->fetchColumn();
    }
}
