<?php

namespace tournoi_enoa\Models;

use tournoi_enoa\Core\DbConnect;
use tournoi_enoa\Entities\MatchGame;

class MatchModel extends DbConnect
{
    public function byTournament(int $tournamentId): array
    {
        $sql = 'SELECT m.*, a.nom AS team_a_name, b.nom AS team_b_name, c.nom AS court_name FROM matches m LEFT JOIN teams a ON a.id = m.team_a_id LEFT JOIN teams b ON b.id = m.team_b_id LEFT JOIN courts c ON c.id = m.court_id WHERE m.tournament_id = :tournament_id ORDER BY m.scheduled_start, m.match_number';
        $query = $this->connection->prepare($sql);
        $query->execute(['tournament_id' => $tournamentId]);
        return $query->fetchAll(\PDO::FETCH_CLASS, MatchGame::class);
    }

    /**
     * Matchs de la phase de qualification (poules), avec la poule résolue via pool_teams
     * quand la colonne matches.pool_id n'est pas renseignée.
     */
    public function qualificationByTournament(int $tournamentId): array
    {
        $sql = 'SELECT m.*, a.nom AS team_a_name, b.nom AS team_b_name, c.nom AS court_name, COALESCE(m.pool_id, p.id) AS resolved_pool_id
                FROM matches m
                LEFT JOIN teams a ON a.id = m.team_a_id
                LEFT JOIN teams b ON b.id = m.team_b_id
                LEFT JOIN courts c ON c.id = m.court_id
                LEFT JOIN pool_teams pt ON pt.team_id = m.team_a_id
                LEFT JOIN pools p ON p.id = pt.pool_id AND p.tournament_id = m.tournament_id
                WHERE m.tournament_id = :tournament_id AND m.phase = "qualification"
                ORDER BY resolved_pool_id, m.match_number';
        $query = $this->connection->prepare($sql);
        $query->execute(['tournament_id' => $tournamentId]);
        return $query->fetchAll(\PDO::FETCH_CLASS, MatchGame::class);
    }

    /**
     * Matchs de la phase finale (upper bracket / lower bracket / grande finale) : sert à la
     * fois à reconstruire l'arbre (Core\BracketTree::build(), indifférent à l'ordre reçu -
     * il retrouve chaque match via phase/round_number/next_match_id) et à la liste plate
     * affichée sous l'arbre, triée par horaire réel pour suivre l'ordre de passage plutôt que
     * de regrouper par section du bracket (upper bracket en bloc, puis lower, puis grande
     * finale), ce qui ne correspond pas à l'ordre dans lequel le gestionnaire saisit les scores.
     */
    public function finalPhaseByTournament(int $tournamentId): array
    {
        $sql = "SELECT m.*, a.nom AS team_a_name, b.nom AS team_b_name, c.nom AS court_name
                FROM matches m
                LEFT JOIN teams a ON a.id = m.team_a_id
                LEFT JOIN teams b ON b.id = m.team_b_id
                LEFT JOIN courts c ON c.id = m.court_id
                WHERE m.tournament_id = :tournament_id AND m.phase IN ('upper', 'lower', 'grand_final')
                ORDER BY m.scheduled_start, m.match_number";
        $query = $this->connection->prepare($sql);
        $query->execute(['tournament_id' => $tournamentId]);
        return $query->fetchAll(\PDO::FETCH_CLASS, MatchGame::class);
    }

    /**
     * Prochains matchs à jouer (ceux déjà en cours d'abord, puisqu'ils sont triés par heure
     * de début), limités au nombre de terrains : il ne peut jamais y avoir plus de matchs
     * simultanés que de terrains disponibles. Sert à la page de suivi live.
     */
    public function upcoming(int $tournamentId, int $limit): array
    {
        $sql = 'SELECT m.*, a.nom AS team_a_name, b.nom AS team_b_name, c.nom AS court_name
                FROM matches m
                LEFT JOIN teams a ON a.id = m.team_a_id
                LEFT JOIN teams b ON b.id = m.team_b_id
                LEFT JOIN courts c ON c.id = m.court_id
                WHERE m.tournament_id = :tournament_id
                  AND m.status NOT IN ("finished", "cancelled")
                  AND m.scheduled_start IS NOT NULL
                ORDER BY m.scheduled_start ASC, m.match_number ASC
                LIMIT :limit';
        $query = $this->connection->prepare($sql);
        $query->bindValue('tournament_id', $tournamentId, \PDO::PARAM_INT);
        $query->bindValue('limit', max(1, $limit), \PDO::PARAM_INT);
        $query->execute();
        return $query->fetchAll(\PDO::FETCH_CLASS, MatchGame::class);
    }

    public function find(int $id): ?MatchGame
    {
        $query = $this->connection->prepare('SELECT * FROM matches WHERE id = :id');
        $query->execute(['id' => $id]);
        $query->setFetchMode(\PDO::FETCH_CLASS, MatchGame::class);
        return $query->fetch() ?: null;
    }

    /** Comme find(), avec en plus les noms des deux équipes et du terrain (pour l'affichage du formulaire de score). */
    public function findWithTeams(int $id): ?MatchGame
    {
        $sql = 'SELECT m.*, a.nom AS team_a_name, b.nom AS team_b_name, c.nom AS court_name
                FROM matches m
                LEFT JOIN teams a ON a.id = m.team_a_id
                LEFT JOIN teams b ON b.id = m.team_b_id
                LEFT JOIN courts c ON c.id = m.court_id
                WHERE m.id = :id';
        $query = $this->connection->prepare($sql);
        $query->execute(['id' => $id]);
        $query->setFetchMode(\PDO::FETCH_CLASS, MatchGame::class);
        return $query->fetch() ?: null;
    }

    /**
     * Enregistre le score d'un match et, s'il est terminé et fait partie d'un bracket
     * (next_match_id renseigné), qualifie automatiquement le vainqueur pour le match suivant.
     */
    public function updateScore(int $id, int $scoreA, int $scoreB, string $status): void
    {
        $current = $this->find($id);
        if ($current === null) {
            return;
        }

        $winnerTeamId = null;
        $loserTeamId = null;
        if ($status === 'finished' && $current->team_a_id && $current->team_b_id) {
            if ($scoreA > $scoreB) {
                $winnerTeamId = (int) $current->team_a_id;
                $loserTeamId = (int) $current->team_b_id;
            } elseif ($scoreB > $scoreA) {
                $winnerTeamId = (int) $current->team_b_id;
                $loserTeamId = (int) $current->team_a_id;
            }
        }

        $query = $this->connection->prepare(
            'UPDATE matches SET score_team_a = :score_a, score_team_b = :score_b, status = :status,
                    winner_team_id = :winner_team_id, loser_team_id = :loser_team_id,
                    ended_at = IF(:status = "finished", NOW(), ended_at)
             WHERE id = :id'
        );
        $query->execute([
            'score_a'         => $scoreA,
            'score_b'         => $scoreB,
            'status'          => $status,
            'winner_team_id'  => $winnerTeamId,
            'loser_team_id'   => $loserTeamId,
            'id'              => $id,
        ]);

        if ($winnerTeamId !== null && $current->next_match_id && $current->next_match_slot) {
            $column = $current->next_match_slot === 'team_b' ? 'team_b_id' : 'team_a_id';
            $propagate = $this->connection->prepare("UPDATE matches SET $column = :team_id WHERE id = :id");
            $propagate->execute(['team_id' => $winnerTeamId, 'id' => $current->next_match_id]);
        }
    }
}
