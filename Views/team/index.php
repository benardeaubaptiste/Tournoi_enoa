<?php
$title = 'Équipes';
$quotaAtteint = $maxEquipes > 0 && count($teams) >= $maxEquipes;
?>

<div class="page-heading">
    <div>
        <a class="back-link" href="index.php?controller=home&action=index&id=<?= (int) $tournamentId ?>">&larr; Retour au tournoi</a>
        <p class="eyebrow">Gestion sportive<?= $maxEquipes > 0 ? ' — ' . count($teams) . ' / ' . $maxEquipes . ' équipes' : '' ?></p>
        <h2>Équipes inscrites</h2>
    </div>
    <div class="d-flex gap-2">
        <?php if ($quotaAtteint): ?>
            <span class="btn btn-enoa disabled" aria-disabled="true" title="Le nombre maximum d'équipes (<?= $maxEquipes ?>) est atteint">+ Inscrire une équipe</span>
        <?php else: ?>
            <a class="btn btn-enoa" href="index.php?controller=team&action=add&tournamentId=<?= (int) $tournamentId ?>">+ Inscrire une équipe</a>
        <?php endif; ?>
        <?php if ($teams): ?>
            <form method="post" action="index.php?controller=pool&action=autoGenerate&tournamentId=<?= (int) $tournamentId ?>">
                <?= \tournoi_enoa\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-navy" onclick="return confirm('Composer automatiquement les poules d\'après le nombre de poules et le mode de répartition réglés pour ce tournoi, puis générer directement les matchs de qualification ?');">
                    🪄 Générer les poules
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!$teams): ?>

    <section class="empty">
        <h3>Aucune équipe</h3>
        <p class="mb-0">Inscrivez la première équipe de ce tournoi.</p>
    </section>

<?php else: ?>

    <div class="table-wrap">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Équipe</th>
                        <th>Club</th>
                        <th>Joueurs</th>
                        <th>Paiement</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teams as $team): ?>
                        <tr class="clickable-row" data-href="index.php?controller=team&action=show&teamId=<?= (int) $team->id ?>&tournamentId=<?= (int) $tournamentId ?>">
                            <td><strong><a href="index.php?controller=team&action=show&teamId=<?= (int) $team->id ?>&tournamentId=<?= (int) $tournamentId ?>"><?= htmlspecialchars($team->nom) ?></a></strong></td>
                            <td><?= htmlspecialchars($team->club ?? 'Indépendante') ?></td>
                            <td><?= (int) $team->nombre_joueurs ?></td>
                            <td><?= number_format((float) $team->montant_paye, 2, ',', ' ') ?> / <?= number_format((float) $team->montant_a_payer, 2, ',', ' ') ?> €</td>
                            <td>
                                <?php $reste = max(0, (float) $team->montant_a_payer - (float) $team->montant_paye); ?>
                                <?php if ($team->statutPaiementReel() === 'paye'): ?>
                                    <span class="badge bg-success">✅ Payé</span>
                                <?php elseif ($team->statutPaiementReel() === 'partiel'): ?>
                                    <span class="badge bg-warning text-dark">⚠️ Partiel — reste <?= number_format($reste, 2, ',', ' ') ?> €</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">⚠️ Non payé — reste <?= number_format($reste, 2, ',', ' ') ?> €</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="index.php?controller=team&action=players&teamId=<?= (int) $team->id ?>&tournamentId=<?= (int) $tournamentId ?>">Joueurs</a>
                                <a class="btn btn-sm btn-outline-secondary" href="index.php?controller=team&action=edit&teamId=<?= (int) $team->id ?>&tournamentId=<?= (int) $tournamentId ?>">Modifier</a>
                                <form method="post" action="index.php?controller=team&action=delete&teamId=<?= (int) $team->id ?>&tournamentId=<?= (int) $tournamentId ?>" class="d-inline">
                                    <?= \tournoi_enoa\Core\Csrf::field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer définitivement l\'équipe « <?= htmlspecialchars(addslashes($team->nom)) ?> » et ses joueurs ?');">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.querySelectorAll('.clickable-row').forEach(function (row) {
            row.style.cursor = 'pointer';
            row.addEventListener('click', function (event) {
                if (event.target.closest('a, button')) {
                    return;
                }
                window.location.href = row.dataset.href;
            });
        });
    </script>

<?php endif; ?>
