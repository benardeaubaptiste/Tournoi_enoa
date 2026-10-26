<?php $title = 'Ajouter un joueur'; ?>

<div class="page-heading">
    <div>
        <a class="back-link" href="index.php?controller=team&action=players&teamId=<?= (int) $teamId ?>&tournamentId=<?= (int) $tournamentId ?>">&larr; Retour aux joueurs</a>
        <p class="eyebrow">Composition</p>
        <h2>Ajouter un joueur</h2>
    </div>
</div>

<?php if ($error ?? null): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form class="form-card" method="post">
    <?= \tournoi_enoa\Core\Csrf::field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="prenom">Prénom</label>
            <input class="form-control" type="text" id="prenom" name="prenom">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="nom">Nom</label>
            <input class="form-control" type="text" id="nom" name="nom" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="numero_maillot">Numéro de maillot</label>
            <input class="form-control" type="text" id="numero_maillot" name="numero_maillot" maxlength="20">
        </div>
        <div class="col-12">
            <button class="btn btn-enoa" type="submit">Ajouter le joueur</button>
        </div>
    </div>
</form>
