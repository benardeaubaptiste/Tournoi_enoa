<?php
/**
 * Contenu de la section "Arbre de la phase finale", réutilisé par pdf/bracket.php
 * (document seul) et pdf/full.php (bilan complet).
 * @var \tournoi_enoa\Entities\MatchGame[] $bracketMatches
 * @var array $finalRanking
 */
$statusLabels = ['pending' => 'À venir', 'scheduled' => 'Planifié', 'live' => 'En cours', 'finished' => 'Terminé', 'cancelled' => 'Annulé'];
$phaseLabels = ['upper' => 'Upper Bracket', 'lower' => 'Lower Bracket', 'grand_final' => 'Grande finale'];

$byPhase = ['upper' => [], 'lower' => [], 'grand_final' => []];
foreach ($bracketMatches as $match) {
    $byPhase[$match->phase][] = $match;
}
?>
<h2>Arbre de la phase finale</h2>

<?php if (!$bracketMatches): ?>
    <p class="muted">Phase finale pas encore générée.</p>
<?php else: ?>
    <?php foreach (['upper', 'lower', 'grand_final'] as $phase): ?>
        <?php if (!$byPhase[$phase]) { continue; } ?>
        <h3><?= $phaseLabels[$phase] ?></h3>
        <table>
            <thead><tr><th>#</th><th>Tour</th><th>Affiche</th><th class="text-center">Score</th><th>Statut</th></tr></thead>
            <tbody>
                <?php foreach ($byPhase[$phase] as $match): ?>
                    <?php
                    $isWinnerA = $match->winner_team_id && (int) $match->winner_team_id === (int) $match->team_a_id;
                    $isWinnerB = $match->winner_team_id && (int) $match->winner_team_id === (int) $match->team_b_id;
                    $nomA = $match->team_a_name ?? ($match->team_a_id ? '—' : 'Exempt / en attente');
                    $nomB = $match->team_b_name ?? ($match->team_b_id ? '—' : 'Exempt / en attente');
                    ?>
                    <tr>
                        <td><?= (int) $match->match_number ?></td>
                        <td><?= htmlspecialchars($match->round_name ?? '—') ?></td>
                        <td>
                            <?= $isWinnerA ? '<strong>' . htmlspecialchars($nomA) . '</strong>' : htmlspecialchars($nomA) ?>
                            <span class="muted"> vs </span>
                            <?= $isWinnerB ? '<strong>' . htmlspecialchars($nomB) . '</strong>' : htmlspecialchars($nomB) ?>
                        </td>
                        <td class="text-center"><?= (int) $match->score_team_a ?> - <?= (int) $match->score_team_b ?></td>
                        <td><?= htmlspecialchars($statusLabels[$match->status] ?? $match->status) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>

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
<?php endif; ?>
