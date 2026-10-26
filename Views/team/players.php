<?php $title = 'Joueurs'; ?>

<div class="page-heading">
    <div>
        <a class="back-link" href="index.php?controller=team&action=index&tournamentId=<?= (int) $tournamentId ?>">&larr; Retour aux équipes</a>
        <p class="eyebrow">Composition</p>
        <h2>Joueurs de l'équipe</h2>
    </div>
    <a class="btn btn-enoa" href="index.php?controller=team&action=addPlayer&teamId=<?= (int) $teamId ?>&tournamentId=<?= (int) $tournamentId ?>">+ Ajouter un joueur</a>
</div>

<?php if (!$players): ?>

    <section class="empty">
        <h3>Aucun joueur</h3>
        <p class="mb-0">Ajoutez le premier joueur de cette équipe.</p>
    </section>

<?php else: ?>

    <div class="table-wrap">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Prénom</th>
                        <th>Nom</th>
                        <th>Maillot</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($players as $player): ?>
                        <tr>
                            <td><?= htmlspecialchars($player->prenom ?? '') ?></td>
                            <td><strong><?= htmlspecialchars($player->nom) ?></strong></td>
                            <td><?= htmlspecialchars($player->numero_maillot ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>
