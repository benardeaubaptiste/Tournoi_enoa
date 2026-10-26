<?php
/**
 * Contenu de la section "Planning des matchs", réutilisé par pdf/planning.php (document
 * seul) et pdf/full.php (bilan complet).
 * @var \tournoi_enoa\Entities\MatchGame[] $planningMatches
 */
$phaseLabels = ['qualification' => 'Qualification', 'upper' => 'Upper bracket', 'lower' => 'Lower bracket', 'grand_final' => 'Grande finale'];
$statusLabels = ['pending' => 'À venir', 'scheduled' => 'Planifié', 'live' => 'En cours', 'finished' => 'Terminé', 'cancelled' => 'Annulé'];
?>
<h2>Planning des matchs</h2>

<?php if (!$planningMatches): ?>
    <p class="muted">Le planning n'a pas encore été généré.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>#</th><th>Phase</th><th>Terrain</th><th>Horaire</th><th>Affiche</th>
                <th class="text-center">Score</th><th>Statut</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($planningMatches as $match): ?>
                <tr>
                    <td><?= (int) $match->match_number ?></td>
                    <td><?= htmlspecialchars($match->round_name ?: ($phaseLabels[$match->phase] ?? $match->phase)) ?></td>
                    <td><?= htmlspecialchars($match->court_name ?? '—') ?></td>
                    <td><?= $match->scheduled_start ? date('d/m H:i', strtotime($match->scheduled_start)) : '—' ?></td>
                    <td><?= htmlspecialchars($match->team_a_name ?? 'À définir') ?> vs <?= htmlspecialchars($match->team_b_name ?? 'À définir') ?></td>
                    <td class="text-center"><?= (int) $match->score_team_a ?> - <?= (int) $match->score_team_b ?></td>
                    <td><?= htmlspecialchars($statusLabels[$match->status] ?? $match->status) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
