<?php
$title = 'Tournois';

$statutBadges = [
    'draft'             => 'bg-secondary',
    'inscriptions'      => 'bg-info text-dark',
    'poules_a_generer'  => 'bg-warning text-dark',
    'poules_validees'   => 'bg-warning text-dark',
    'planning_genere'   => 'bg-primary',
    'en_cours'          => 'bg-warning text-dark',
    'phase_finale'      => 'bg-primary',
    'termine'           => 'bg-success',
    'archive'           => 'bg-dark',
];
?>

<div class="page-heading">
    <div>
        <p class="eyebrow">Administration</p>
        <h2>Tournois</h2>
    </div>
    <a class="btn btn-enoa" href="index.php?controller=tournament&action=add">+ Nouveau tournoi</a>
</div>

<?php if (!$tournaments): ?>

    <section class="empty">
        <h3>Aucun tournoi</h3>
        <p class="mb-0">Créez votre premier tournoi pour commencer.</p>
    </section>

<?php else: ?>

    <div class="table-wrap">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Titre</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tournaments as $tournament): ?>
                        <tr class="row-link" role="link" tabindex="0" data-href="index.php?id=<?= (int) $tournament->id ?>">
                            <td><?= (int) $tournament->numero ?></td>
                            <td><strong><?= htmlspecialchars($tournament->titre) ?></strong></td>
                            <td><?= htmlspecialchars($tournament->date_tournoi) ?></td>
                            <td><span class="badge <?= $statutBadges[$tournament->statut] ?? 'bg-secondary' ?>"><?= htmlspecialchars($tournament->statut) ?></span></td>
                            <td class="text-end" onclick="event.stopPropagation()">
                                <a class="btn btn-sm btn-outline-primary" href="index.php?id=<?= (int) $tournament->id ?>">Ouvrir</a>
                                <form method="post" action="index.php?controller=tournament&action=delete&id=<?= (int) $tournament->id ?>" class="d-inline">
                                    <?= \tournoi_enoa\Core\Csrf::field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer définitivement le tournoi « <?= htmlspecialchars(addslashes($tournament->titre)) ?> » et toutes ses données (équipes, poules, matchs) ?');">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<script>
document.querySelectorAll('.table-wrap tbody tr.row-link').forEach(function (row) {
    row.addEventListener('click', function () {
        window.location = row.dataset.href;
    });
    row.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            window.location = row.dataset.href;
        }
    });
});
</script>
