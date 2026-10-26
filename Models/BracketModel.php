<?php

namespace tournoi_enoa\Models;

use tournoi_enoa\Core\DbConnect;

/**
 * Génère la phase finale à partir du classement général de qualification :
 * la moitié haute du classement dispute un tableau à élimination directe entre elle
 * (upper bracket), la moitié basse dispute le sien (lower bracket), et les deux
 * vainqueurs se retrouvent en grande finale.
 */
class BracketModel extends DbConnect
{
    /**
     * Y a-t-il déjà un vrai résultat (saisi par l'admin) sur la phase finale ? Sert à protéger
     * une régénération. Les matchs "exempt" (bye), auto-terminés à la génération faute de second
     * adversaire, ne comptent pas : ce ne sont pas des résultats joués.
     */
    public function hasFinalPhaseResults(int $tournamentId): bool
    {
        $query = $this->connection->prepare(
            "SELECT COUNT(*) FROM matches
             WHERE tournament_id = :tournament_id
               AND phase IN ('upper', 'lower', 'grand_final')
               AND status IN ('live', 'finished')
               AND team_a_id IS NOT NULL AND team_b_id IS NOT NULL"
        );
        $query->execute(['tournament_id' => $tournamentId]);
        return ((int) $query->fetchColumn()) > 0;
    }

    /** Supprime les matchs de phase finale déjà générés (à n'appeler que si hasFinalPhaseResults() est faux). */
    public function clearFinalPhase(int $tournamentId): void
    {
        $query = $this->connection->prepare(
            "DELETE FROM matches WHERE tournament_id = :tournament_id AND phase IN ('upper', 'lower', 'grand_final')"
        );
        $query->execute(['tournament_id' => $tournamentId]);
    }

    /**
     * Attribue un terrain aux matchs de phase finale qui n'en ont jamais reçu, quel que
     * soit leur statut — voir QualificationModel::assignMissingCourts() pour le détail :
     * même principe, ne touche jamais un match qui a déjà un terrain.
     *
     * @return int nombre de matchs mis à jour
     */
    public function assignMissingCourts(int $tournamentId): int
    {
        $matchesQuery = $this->connection->prepare(
            "SELECT id FROM matches WHERE tournament_id = :tournament_id AND phase IN ('upper', 'lower', 'grand_final') AND court_id IS NULL ORDER BY match_number"
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
     * @param int[] $upperTeamIds équipes de la moitié haute du classement, de la mieux classée à la moins bien classée
     * @param int[] $lowerTeamIds équipes de la moitié basse, même tri
     */
    public function generateFinalPhase(int $tournamentId, array $upperTeamIds, array $lowerTeamIds): void
    {
        $this->connection->beginTransaction();
        try {
            $matchNumber = $this->nextMatchNumber($tournamentId);

            $upperFinal = $this->generateSingleElim($tournamentId, 'upper', $upperTeamIds, $matchNumber);
            $lowerFinal = $this->generateSingleElim($tournamentId, 'lower', $lowerTeamIds, $matchNumber);

            if ($upperFinal['type'] !== 'none' || $lowerFinal['type'] !== 'none') {
                $teamA = $upperFinal['type'] === 'team' ? $upperFinal['team_id'] : null;
                $teamB = $lowerFinal['type'] === 'team' ? $lowerFinal['team_id'] : null;

                $grandFinalId = $this->insertMatch($tournamentId, 'grand_final', 'Grande finale', null, $matchNumber++, $teamA, $teamB);

                if ($upperFinal['type'] === 'match') {
                    $this->linkNextMatch($upperFinal['match_id'], $grandFinalId, 'team_a');
                }
                if ($lowerFinal['type'] === 'match') {
                    $this->linkNextMatch($lowerFinal['match_id'], $grandFinalId, 'team_b');
                }
            }

            $this->scheduleFinalPhase($tournamentId);

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    /**
     * Affecte à chaque match de phase finale déjà généré un terrain et un horaire :
     * les matchs d'un même round (upper et lower confondus, qui ne dépendent pas l'un
     * de l'autre) tournent en même temps sur les terrains disponibles, round après round ;
     * la grande finale est placée en dernier, avec sa propre durée réglable (tournament_settings.duree_grande_finale).
     * Sans effet si la phase finale n'a pas encore été générée ou si les horaires/terrains
     * ne sont pas encore configurés.
     */
    public function scheduleFinalPhase(int $tournamentId): void
    {
        $tournamentQuery = $this->connection->prepare('SELECT date_tournoi FROM tournaments WHERE id = :id');
        $tournamentQuery->execute(['id' => $tournamentId]);
        $dateTournoi = $tournamentQuery->fetchColumn();

        $settingsQuery = $this->connection->prepare(
            'SELECT heure_debut_tournoi, heure_debut_phase_finale, duree_match_phase_finale, temps_entre_matchs_phase_finale, duree_grande_finale, nombre_terrains
             FROM tournament_settings WHERE tournament_id = :tournament_id'
        );
        $settingsQuery->execute(['tournament_id' => $tournamentId]);
        $settings = $settingsQuery->fetch();

        if (!$dateTournoi || !$settings || !$settings->heure_debut_phase_finale || !$settings->duree_match_phase_finale) {
            return; // pas encore configuré : rien à planifier
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

        $matchesQuery = $this->connection->prepare(
            "SELECT id, phase, round_number FROM matches
             WHERE tournament_id = :tournament_id AND phase IN ('upper', 'lower') AND status = 'pending'
             ORDER BY round_number, phase, match_number"
        );
        $matchesQuery->execute(['tournament_id' => $tournamentId]);
        $bracketMatches = $matchesQuery->fetchAll();

        $rounds = [];
        foreach ($bracketMatches as $match) {
            $rounds[(int) $match->round_number][] = (int) $match->id;
        }
        ksort($rounds);

        $dureeMatch = (int) $settings->duree_match_phase_finale;
        $gap = (int) $settings->temps_entre_matchs_phase_finale;

        // Si la phase finale démarre après minuit (tournoi en soirée), son heure de début
        // "tourne" derrière l'heure de début du tournoi : elle a lieu le lendemain de date_tournoi.
        $jourPhaseFinale = $settings->heure_debut_phase_finale < $settings->heure_debut_tournoi
            ? (new \DateTimeImmutable($dateTournoi))->modify('+1 day')->format('Y-m-d')
            : $dateTournoi;
        $current = new \DateTimeImmutable($jourPhaseFinale . ' ' . $settings->heure_debut_phase_finale);

        $updateMatch = $this->connection->prepare(
            'UPDATE matches SET court_id = :court_id, scheduled_start = :start, scheduled_end = :end, duration_seconds = :duration WHERE id = :id'
        );

        // Rotation continue des terrains sur toute la planification, jamais remise à 0 à
        // chaque vague/round — voir le même correctif et sa justification dans
        // QualificationModel::scheduleQualification().
        $courtCursor = 0;

        foreach ($rounds as $matchIds) {
            foreach (array_chunk($matchIds, $courtCount) as $wave) {
                $end = $current->modify("+{$dureeMatch} minutes");
                foreach ($wave as $matchId) {
                    $updateMatch->execute([
                        'court_id' => $courtIds[$courtCursor % $courtCount],
                        'start'    => $current->format('Y-m-d H:i:s'),
                        'end'      => $end->format('Y-m-d H:i:s'),
                        'duration' => $dureeMatch * 60,
                        'id'       => $matchId,
                    ]);
                    $courtCursor++;
                }
                $current = $end->modify("+{$gap} minutes");
            }
        }

        $grandFinalQuery = $this->connection->prepare(
            "SELECT id FROM matches WHERE tournament_id = :tournament_id AND phase = 'grand_final' AND status = 'pending'"
        );
        $grandFinalQuery->execute(['tournament_id' => $tournamentId]);
        $grandFinalId = $grandFinalQuery->fetchColumn();

        if ($grandFinalId) {
            $duree = (int) $settings->duree_grande_finale;
            $end = $current->modify("+{$duree} minutes");
            $updateMatch->execute([
                'court_id' => $courtIds[0],
                'start'    => $current->format('Y-m-d H:i:s'),
                'end'      => $end->format('Y-m-d H:i:s'),
                'duration' => $duree * 60,
                'id'       => $grandFinalId,
            ]);
        }
    }

    /**
     * Construit un tableau à élimination directe et renvoie sa sortie :
     *  - ['type' => 'none'] si aucune équipe,
     *  - ['type' => 'team', 'team_id' => x] si une seule équipe (qualifiée sans jouer),
     *  - ['type' => 'match', 'match_id' => y] si un dernier match désigne le vainqueur.
     *
     * Si l'effectif n'est pas une puissance de 2, jamais d'exempt (aucune équipe qualifiée
     * pour la suite sans avoir joué) : les moins bien classées disputent d'abord un vrai tour
     * préliminaire (chacune contre une autre, jamais contre une case vide), et seules les
     * gagnantes rejoignent un tableau principal dont la taille — la plus grande puissance de 2
     * qui tient dans l'effectif — ne comporte alors plus aucune case vide.
     */
    private function generateSingleElim(int $tournamentId, string $phase, array $rankedTeamIds, int &$matchNumber): array
    {
        $count = count($rankedTeamIds);
        if ($count === 0) {
            return ['type' => 'none'];
        }
        if ($count === 1) {
            return ['type' => 'team', 'team_id' => $rankedTeamIds[0]];
        }

        // Plus grande puissance de 2 qui tient dans l'effectif (ex. 6 équipes -> 4, pas 8) :
        // taille du tableau principal. Le "trop" par rapport à cette taille (les moins bien
        // classées, en nombre pair par construction) dispute le tour préliminaire.
        $mainSize = 1;
        while ($mainSize * 2 <= $count) {
            $mainSize *= 2;
        }
        $preliminaryMatchCount = $count - $mainSize;
        $directEntrantCount = $count - 2 * $preliminaryMatchCount;

        $directTeamIds = array_slice($rankedTeamIds, 0, $directEntrantCount);
        $preliminaryTeamIds = array_slice($rankedTeamIds, $directEntrantCount);

        // Tour préliminaire : la mieux classée des équipes restantes affronte la moins bonne,
        // etc. (même logique d'appariement "meilleure vs moins bonne" que le reste du
        // placement) — chaque vainqueur prendra la place que son seed aurait occupée au
        // tableau principal si l'effectif avait été pile une puissance de 2.
        $preliminaryMatchIds = [];
        for ($i = 0; $i < $preliminaryMatchCount; $i++) {
            $teamA = $preliminaryTeamIds[$i];
            $teamB = $preliminaryTeamIds[count($preliminaryTeamIds) - 1 - $i];
            $preliminaryMatchIds[] = $this->insertMatch(
                $tournamentId,
                $phase,
                $this->roundName(0, $phase, true),
                1,
                $matchNumber++,
                $teamA,
                $teamB
            );
        }

        // Emplacements du tableau principal, dans l'ordre de placement standard : une équipe
        // déjà connue (qualifiée directement) ou en attente du vainqueur du tour préliminaire
        // qui lui correspond. 'source_match_id' sert à détecter un exempt (toujours null
        // désormais, gardé par sécurité) ; 'display_match_id' relie visuellement ce match au
        // suivant dans l'arbre (BracketTree) — jamais null ici, il n'y a plus de case vide.
        $slots = [];
        $nextPreliminary = 0;
        foreach ($this->seedOrder($mainSize) as $seed) {
            if ($seed <= $directEntrantCount) {
                $slots[] = ['team_id' => $directTeamIds[$seed - 1], 'source_match_id' => null, 'display_match_id' => null];
            } else {
                $matchId = $preliminaryMatchIds[$nextPreliminary++];
                $slots[] = ['team_id' => null, 'source_match_id' => $matchId, 'display_match_id' => $matchId];
            }
        }

        $roundNumber = $preliminaryMatchCount > 0 ? 2 : 1;
        while (count($slots) > 1) {
            $roundName = $this->roundName((int) (count($slots) / 2), $phase);
            $nextSlots = [];

            for ($i = 0; $i < count($slots); $i += 2) {
                $left = $slots[$i];
                $right = $slots[$i + 1];

                $teamA = $left['team_id'];
                $teamB = $right['team_id'];

                // Filet de sécurité, plus censé se produire depuis l'ajout du tour
                // préliminaire ci-dessus (le tableau principal ne contient plus de case
                // vide) : si malgré tout une case restait sans équipe ni match en attente,
                // mieux vaut qualifier l'autre équipe sans y jouer que bloquer l'arbre.
                $winner = null;
                $status = 'pending';
                if ($teamA !== null && $teamB === null && $right['source_match_id'] === null) {
                    $winner = $teamA;
                    $status = 'finished';
                } elseif ($teamB !== null && $teamA === null && $left['source_match_id'] === null) {
                    $winner = $teamB;
                    $status = 'finished';
                }

                $matchId = $this->insertMatch($tournamentId, $phase, $roundName, $roundNumber, $matchNumber++, $teamA, $teamB, $status, $winner);

                if ($left['display_match_id'] !== null) {
                    $this->linkNextMatch($left['display_match_id'], $matchId, 'team_a');
                }
                if ($right['display_match_id'] !== null) {
                    $this->linkNextMatch($right['display_match_id'], $matchId, 'team_b');
                }

                $nextSlots[] = ['team_id' => $winner, 'source_match_id' => $winner === null ? $matchId : null, 'display_match_id' => $matchId];
            }

            $slots = $nextSlots;
            $roundNumber++;
        }

        $final = $slots[0];
        return $final['source_match_id'] !== null
            ? ['type' => 'match', 'match_id' => $final['source_match_id']]
            : ['type' => 'team', 'team_id' => $final['team_id']];
    }

    /** Ordre de placement standard d'un tableau à élimination directe (ex. taille 4 -> [1, 4, 2, 3]). */
    private function seedOrder(int $size): array
    {
        $order = [1, 2];
        while (count($order) < $size) {
            $n = count($order) * 2 + 1;
            $next = [];
            foreach ($order as $seed) {
                $next[] = $seed;
                $next[] = $n - $seed;
            }
            $order = $next;
        }
        return $order;
    }

    private function roundName(int $matchesInRound, string $phase, bool $preliminary = false): string
    {
        $label = $preliminary ? 'Tour préliminaire' : match (true) {
            $matchesInRound <= 1  => 'Finale',
            $matchesInRound === 2 => 'Demi-finale',
            $matchesInRound === 4 => 'Quart de finale',
            $matchesInRound === 8 => 'Huitième de finale',
            default               => '1/' . ($matchesInRound * 2) . ' de finale',
        };

        $suffix = ['upper' => ' (Upper bracket)', 'lower' => ' (Lower bracket)'][$phase] ?? '';
        return $label . $suffix;
    }

    private function nextMatchNumber(int $tournamentId): int
    {
        $query = $this->connection->prepare('SELECT COALESCE(MAX(match_number), 0) + 1 FROM matches WHERE tournament_id = :tournament_id');
        $query->execute(['tournament_id' => $tournamentId]);
        return (int) $query->fetchColumn();
    }

    private function insertMatch(int $tournamentId, string $phase, string $roundName, ?int $roundNumber, int $matchNumber, ?int $teamA, ?int $teamB, string $status = 'pending', ?int $winner = null): int
    {
        $query = $this->connection->prepare(
            'INSERT INTO matches (tournament_id, phase, round_name, round_number, match_number, team_a_id, team_b_id, status, winner_team_id)
             VALUES (:tournament_id, :phase, :round_name, :round_number, :match_number, :team_a_id, :team_b_id, :status, :winner_team_id)'
        );
        $query->execute([
            'tournament_id'  => $tournamentId,
            'phase'          => $phase,
            'round_name'     => $roundName,
            'round_number'   => $roundNumber,
            'match_number'   => $matchNumber,
            'team_a_id'      => $teamA,
            'team_b_id'      => $teamB,
            'status'         => $status,
            'winner_team_id' => $winner,
        ]);
        return (int) $this->connection->lastInsertId();
    }

    private function linkNextMatch(int $sourceMatchId, int $nextMatchId, string $slot): void
    {
        $query = $this->connection->prepare('UPDATE matches SET next_match_id = :next_match_id, next_match_slot = :slot WHERE id = :id');
        $query->execute(['next_match_id' => $nextMatchId, 'slot' => $slot, 'id' => $sourceMatchId]);
    }
}
