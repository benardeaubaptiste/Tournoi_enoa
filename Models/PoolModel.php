<?php

namespace tournoi_enoa\Models;

use tournoi_enoa\Core\DbConnect;
use tournoi_enoa\Entities\Pool;

class PoolModel extends DbConnect
{
    /** Nombre maximal de poules par tournoi (contrainte chk_pool_numero en base). */
    public const MAX_POOLS = 4;

    public function byTournament(int $tournamentId): array
    {
        $query = $this->connection->prepare('SELECT p.*, COUNT(pt.team_id) AS team_count FROM pools p LEFT JOIN pool_teams pt ON pt.pool_id = p.id WHERE p.tournament_id = :tournament_id GROUP BY p.id ORDER BY p.numero');
        $query->execute(['tournament_id' => $tournamentId]);
        return $query->fetchAll(\PDO::FETCH_CLASS, Pool::class);
    }

    /**
     * Équipes déjà placées dans les poules d'un tournoi, groupées par poule et triées par position.
     * @return array<int, array<int, array{team_id:int, nom:string, position:int}>>
     */
    public function teamAssignments(int $tournamentId): array
    {
        $sql = 'SELECT pt.pool_id, pt.team_id, pt.position, t.nom
                FROM pool_teams pt
                INNER JOIN pools p ON p.id = pt.pool_id
                INNER JOIN teams t ON t.id = pt.team_id
                WHERE p.tournament_id = :tournament_id
                ORDER BY pt.pool_id, pt.position';
        $query = $this->connection->prepare($sql);
        $query->execute(['tournament_id' => $tournamentId]);

        $assignments = [];
        foreach ($query->fetchAll() as $row) {
            $assignments[(int) $row->pool_id][] = [
                'team_id'  => (int) $row->team_id,
                'nom'      => $row->nom,
                'position' => (int) $row->position,
            ];
        }
        return $assignments;
    }

    /** Premier numéro de poule (1 à MAX_POOLS) non utilisé pour ce tournoi, 0 si complet. */
    public function nextNumero(int $tournamentId): int
    {
        $query = $this->connection->prepare('SELECT numero FROM pools WHERE tournament_id = :tournament_id');
        $query->execute(['tournament_id' => $tournamentId]);
        $used = array_map('intval', $query->fetchAll(\PDO::FETCH_COLUMN));

        for ($numero = 1; $numero <= self::MAX_POOLS; $numero++) {
            if (!in_array($numero, $used, true)) {
                return $numero;
            }
        }
        return 0;
    }

    public function create(int $tournamentId, int $numero, string $nom, string $statut = 'draft'): int
    {
        $query = $this->connection->prepare('INSERT INTO pools (tournament_id, numero, nom, statut) VALUES (:tournament_id, :numero, :nom, :statut)');
        $query->execute(['tournament_id' => $tournamentId, 'numero' => $numero, 'nom' => $nom, 'statut' => $statut]);
        return (int) $this->connection->lastInsertId();
    }

    public function update(int $id, string $nom, string $statut): void
    {
        $query = $this->connection->prepare('UPDATE pools SET nom = :nom, statut = :statut WHERE id = :id');
        $query->execute(['nom' => $nom, 'statut' => $statut, 'id' => $id]);
    }

    public function delete(int $id, int $tournamentId): void
    {
        $query = $this->connection->prepare('DELETE FROM pools WHERE id = :id AND tournament_id = :tournament_id');
        $query->execute(['id' => $id, 'tournament_id' => $tournamentId]);
    }

    /** Remplace la composition d'une poule : $teamIds dans l'ordre = position 1, 2, 3... */
    public function syncTeams(int $poolId, array $teamIds): void
    {
        $delete = $this->connection->prepare('DELETE FROM pool_teams WHERE pool_id = :pool_id');
        $delete->execute(['pool_id' => $poolId]);

        $insert = $this->connection->prepare('INSERT INTO pool_teams (pool_id, team_id, position) VALUES (:pool_id, :team_id, :position)');
        foreach (array_values($teamIds) as $index => $teamId) {
            $insert->execute(['pool_id' => $poolId, 'team_id' => $teamId, 'position' => $index + 1]);
        }
    }
}
