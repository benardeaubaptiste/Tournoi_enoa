<?php
/**
 * Tableau de matchs, une ligne cliquable par match (ouvre la modale de saisie du score, voir
 * match/_scoreModal.php) — partagé par match/index.php (qualification + phase finale) et
 * match/bracket.php (liste des matchs de la phase finale, sous l'arbre).
 *
 * @var object[] $rows matchs à afficher (MatchModel::*ByTournament : team_a_name/team_b_name/
 *                      court_name déjà joints)
 */
$statusBadges = [
    'pending'   => 'bg-secondary',
    'scheduled' => 'bg-info text-dark',
    'live'      => 'bg-warning text-dark',
    'finished'  => 'bg-success',
    'cancelled' => 'bg-danger',
];

if (!$rows) {
    echo '<p class="text-muted">Aucun match.</p>';
    return;
}
?>
<div class="table-wrap mb-4">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Phase</th>
                    <th>Affiche</th>
                    <th>Terrain</th>
                    <th>Horaire</th>
                    <th>Score</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $match): ?>
                    <tr class="match-row"
                        data-match-id="<?= (int) $match->id ?>"
                        data-match-number="<?= (int) $match->match_number ?>"
                        data-team-a-id="<?= (int) $match->team_a_id ?>"
                        data-team-b-id="<?= (int) $match->team_b_id ?>"
                        data-team-a-name="<?= htmlspecialchars($match->team_a_name ?? 'Équipe A') ?>"
                        data-team-b-name="<?= htmlspecialchars($match->team_b_name ?? 'Équipe B') ?>"
                        data-score-a="<?= (int) $match->score_team_a ?>"
                        data-score-b="<?= (int) $match->score_team_b ?>"
                        data-status="<?= htmlspecialchars($match->status) ?>">
                        <td><?= (int) $match->match_number ?></td>
                        <td><?= htmlspecialchars($match->round_name ?: $match->phase) ?></td>
                        <td><?= htmlspecialchars($match->team_a_name ?? 'À définir') ?> <small class="text-muted">vs</small> <?= htmlspecialchars($match->team_b_name ?? 'À définir') ?></td>
                        <td><?= htmlspecialchars($match->court_name ?? '-') ?></td>
                        <td><?= $match->scheduled_start ? date('d/m H:i', strtotime($match->scheduled_start)) : '-' ?></td>
                        <td data-role="score-cell"><strong><?= (int) $match->score_team_a ?> - <?= (int) $match->score_team_b ?></strong></td>
                        <td data-role="status-cell"><span class="badge <?= $statusBadges[$match->status] ?? 'bg-secondary' ?>"><?= htmlspecialchars($match->status) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
