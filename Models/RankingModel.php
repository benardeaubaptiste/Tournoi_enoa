<?php

namespace tournoi_enoa\Models;

use tournoi_enoa\Core\DbConnect;

/**
 * Persiste dans `rankings` les classements calculés par Core\Standings (par poule et
 * classement général de qualification), ainsi que le classement final de la phase à
 * élimination une fois la grande finale jouée. Sans cette persistance, la table
 * `rankings` restait entièrement vide et aucun classement final n'était jamais montré.
 */
class RankingModel extends DbConnect
{
    /**
     * Remplace le classement d'une phase donnée (qualification par poule si $poolId est
     * fourni, ou global/final si $poolId vaut null) par les lignes fournies telles que
     * renvoyées par Core\Standings::compute() (ou construites de façon équivalente).
     *
     * @param array<int,array{team_id:int,position:int,joues:int,victoires:int,defaites:int,sets_pour:int,sets_contre:int,diff_sets:int,points:float}> $rows
     */
    public function saveStandings(int $tournamentId, ?int $poolId, string $phase, array $rows): void
    {
        $poolCondition = $poolId === null ? 'pool_id IS NULL' : 'pool_id = :pool_id';
        $delete = $this->connection->prepare(
            "DELETE FROM rankings WHERE tournament_id = :tournament_id AND phase = :phase AND $poolCondition"
        );
        $params = ['tournament_id' => $tournamentId, 'phase' => $phase];
        if ($poolId !== null) {
            $params['pool_id'] = $poolId;
        }
        $delete->execute($params);

        if (!$rows) {
            return;
        }

        $insert = $this->connection->prepare(
            'INSERT INTO rankings (
                tournament_id, pool_id, team_id, phase, position, matchs_joues, victoires, defaites,
                points, sets_gagnes, sets_perdus, difference_sets, points_marques, points_encaisses, difference_points
            ) VALUES (
                :tournament_id, :pool_id, :team_id, :phase, :position, :matchs_joues, :victoires, :defaites,
                :points, :sets_gagnes, :sets_perdus, :difference_sets, :points_marques, :points_encaisses, :difference_points
            )'
        );

        foreach ($rows as $row) {
            // Scoring en set unique (voir Core\Standings) : "sets" et "points marqués/encaissés"
            // désignent la même donnée brute (le score du match), reportée dans les deux paires
            // de colonnes pour rester compatible avec le schéma existant.
            $insert->execute([
                'tournament_id'     => $tournamentId,
                'pool_id'           => $poolId,
                'team_id'           => $row['team_id'],
                'phase'             => $phase,
                'position'          => $row['position'],
                'matchs_joues'      => $row['joues'],
                'victoires'         => $row['victoires'],
                'defaites'          => $row['defaites'],
                'points'            => $row['points'],
                'sets_gagnes'       => $row['sets_pour'],
                'sets_perdus'       => $row['sets_contre'],
                'difference_sets'   => $row['diff_sets'],
                'points_marques'    => $row['sets_pour'],
                'points_encaisses'  => $row['sets_contre'],
                'difference_points' => $row['diff_sets'],
            ]);
        }
    }

    /** Classement persisté d'une phase ('qualification' par poule, 'global' ou 'final' avec $poolId = null). */
    public function forPhase(int $tournamentId, ?int $poolId, string $phase): array
    {
        $poolCondition = $poolId === null ? 'r.pool_id IS NULL' : 'r.pool_id = :pool_id';
        $query = $this->connection->prepare(
            "SELECT r.*, t.nom FROM rankings r
             INNER JOIN teams t ON t.id = r.team_id
             WHERE r.tournament_id = :tournament_id AND r.phase = :phase AND $poolCondition
             ORDER BY r.position"
        );
        $params = ['tournament_id' => $tournamentId, 'phase' => $phase];
        if ($poolId !== null) {
            $params['pool_id'] = $poolId;
        }
        $query->execute($params);
        return $query->fetchAll();
    }
}
