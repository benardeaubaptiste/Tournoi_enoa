<?php
/**
 * Contenu de la section "Classements", réutilisé par pdf/standings.php (document seul)
 * et pdf/full.php (bilan complet).
 * @var \tournoi_enoa\Entities\Pool[] $pools
 * @var array<int,array> $standingsByPool
 * @var array $globalStandings
 * @var array $finalRanking
 */
// Les points peuvent être décimaux (réglage "points par victoire/défaite" du tournoi).
$fmtPoints = static function ($points): string {
    $points = (float) $points;
    return floor($points) == $points ? (string) (int) $points : number_format($points, 1, ',', '');
};

$standingsTable = static function (array $rows) use ($fmtPoints): void {
    ?>
    <table>
        <thead><tr><th>#</th><th>Équipe</th><th class="text-center">J</th><th class="text-center">V</th><th class="text-center">D</th><th class="text-center">Sets +/-</th><th class="text-center">Diff</th><th class="text-center">Pts</th></tr></thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= (int) $row['position'] ?></td>
                    <td><?= htmlspecialchars($row['nom']) ?></td>
                    <td class="text-center"><?= (int) $row['joues'] ?></td>
                    <td class="text-center"><?= (int) $row['victoires'] ?></td>
                    <td class="text-center"><?= (int) $row['defaites'] ?></td>
                    <td class="text-center"><?= (int) $row['sets_pour'] ?> / <?= (int) $row['sets_contre'] ?></td>
                    <td class="text-center"><?= $row['diff_sets'] > 0 ? '+' : '' ?><?= (int) $row['diff_sets'] ?></td>
                    <td class="text-center"><strong><?= $fmtPoints($row['points']) ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
};
?>
<h2>Classements</h2>

<?php if (!$pools): ?>
    <p class="muted">Aucune poule composée.</p>
<?php else: ?>
    <?php foreach ($pools as $pool): ?>
        <h3>Poule <?= (int) $pool->numero ?> — <?= htmlspecialchars($pool->nom) ?></h3>
        <?php $standingsTable($standingsByPool[$pool->id] ?? []); ?>
    <?php endforeach; ?>

    <h3>Classement général — toutes poules</h3>
    <?php if (!$globalStandings): ?>
        <p class="muted">Aucun match terminé pour l'instant.</p>
    <?php else: ?>
        <?php $standingsTable($globalStandings); ?>
    <?php endif; ?>
<?php endif; ?>

<?php if ($finalRanking): ?>
    <h3>Classement final</h3>
    <table>
        <thead><tr><th>#</th><th>Équipe</th></tr></thead>
        <tbody>
            <?php foreach ($finalRanking as $row): ?>
                <tr><td><?= (int) $row->position ?></td><td><?= htmlspecialchars($row->nom) ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
