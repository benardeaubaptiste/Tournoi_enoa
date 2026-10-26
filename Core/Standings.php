<?php

namespace tournoi_enoa\Core;

/**
 * Calcule un classement de poule (round-robin) à la volée à partir des scores
 * des matchs déjà enregistrés — aucune donnée n'est lue ni écrite en base ici.
 */
class Standings
{
    /**
     * @param iterable<object> $matches objets avec team_a_id, team_b_id, score_team_a, score_team_b, status
     *                                  (seuls les matchs status="finished" comptent dans le classement)
     * @param array<int,string> $teamNames team_id => nom, pour que les équipes n'ayant pas encore joué apparaissent à 0
     * @param float $pointsVictoire points attribués pour une victoire (réglage du tournoi, 3 par défaut)
     * @param float $pointsDefaite points attribués pour une défaite (réglage du tournoi, 1 par défaut)
     * @return array<int, array{team_id:int, nom:string, joues:int, victoires:int, defaites:int, sets_pour:int, sets_contre:int, diff_sets:int, points:float, position:int}>
     *         Classement trié : points (selon les réglages du tournoi) puis différence de sets puis sets marqués puis nom.
     */
    public static function compute(iterable $matches, array $teamNames, float $pointsVictoire = 3.0, float $pointsDefaite = 1.0): array
    {
        $stats = [];
        foreach ($teamNames as $teamId => $nom) {
            $stats[(int) $teamId] = self::emptyRow((int) $teamId, $nom);
        }

        foreach ($matches as $match) {
            if (($match->status ?? null) !== 'finished') {
                continue;
            }

            $teamA = (int) ($match->team_a_id ?? 0);
            $teamB = (int) ($match->team_b_id ?? 0);
            if ($teamA <= 0 || $teamB <= 0) {
                continue;
            }

            if (!isset($stats[$teamA])) {
                $stats[$teamA] = self::emptyRow($teamA, $match->team_a_name ?? '?');
            }
            if (!isset($stats[$teamB])) {
                $stats[$teamB] = self::emptyRow($teamB, $match->team_b_name ?? '?');
            }

            $scoreA = (int) $match->score_team_a;
            $scoreB = (int) $match->score_team_b;

            $stats[$teamA]['joues']++;
            $stats[$teamB]['joues']++;
            $stats[$teamA]['sets_pour'] += $scoreA;
            $stats[$teamA]['sets_contre'] += $scoreB;
            $stats[$teamB]['sets_pour'] += $scoreB;
            $stats[$teamB]['sets_contre'] += $scoreA;

            if ($scoreA > $scoreB) {
                $stats[$teamA]['victoires']++;
                $stats[$teamB]['defaites']++;
            } elseif ($scoreB > $scoreA) {
                $stats[$teamB]['victoires']++;
                $stats[$teamA]['defaites']++;
            }
        }

        foreach ($stats as &$row) {
            $row['diff_sets'] = $row['sets_pour'] - $row['sets_contre'];
            $row['points'] = $row['victoires'] * $pointsVictoire + $row['defaites'] * $pointsDefaite;
        }
        unset($row);

        $rows = array_values($stats);
        usort($rows, static function (array $x, array $y): int {
            return $y['points'] <=> $x['points']
                ?: $y['diff_sets'] <=> $x['diff_sets']
                ?: $y['sets_pour'] <=> $x['sets_pour']
                ?: strcasecmp($x['nom'], $y['nom']);
        });

        foreach ($rows as $index => &$row) {
            $row['position'] = $index + 1;
        }
        unset($row);

        return $rows;
    }

    /**
     * Retire du classement les équipes données (typiquement des équipes déclarées forfait)
     * et renumérote les positions restantes en conséquence. Les matchs déjà comptabilisés
     * dans les stats des AUTRES équipes ne sont pas recalculés : seule la ligne de l'équipe
     * exclue disparaît, le reste du classement glisse simplement vers le haut.
     *
     * @param array<int, array{team_id:int, position:int, ...}> $rows lignes renvoyées par compute()
     * @param int[] $excludedTeamIds
     * @return array<int, array{team_id:int, position:int, ...}>
     */
    public static function excludeTeams(array $rows, array $excludedTeamIds): array
    {
        if (!$excludedTeamIds) {
            return $rows;
        }

        $excluded = array_flip($excludedTeamIds);
        $rows = array_values(array_filter($rows, static fn (array $row): bool => !isset($excluded[$row['team_id']])));

        foreach ($rows as $index => &$row) {
            $row['position'] = $index + 1;
        }
        unset($row);

        return $rows;
    }

    private static function emptyRow(int $teamId, string $nom): array
    {
        return [
            'team_id'     => $teamId,
            'nom'         => $nom,
            'joues'       => 0,
            'victoires'   => 0,
            'defaites'    => 0,
            'sets_pour'   => 0,
            'sets_contre' => 0,
            'diff_sets'   => 0,
            'points'      => 0,
        ];
    }
}
