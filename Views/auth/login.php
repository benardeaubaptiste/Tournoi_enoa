<?php $title = 'Connexion - Tournoi ENOA'; ?>

<div class="page-heading">
    <div>
        <p class="eyebrow">Accès réservé</p>
        <h2>Connexion administrateur</h2>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form class="form-card login-card" method="post">
    <?= \tournoi_enoa\Core\Csrf::field() ?>
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label" for="username">Identifiant</label>
            <input class="form-control" type="text" id="username" name="username" maxlength="80" required autofocus
                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="col-12">
            <label class="form-label" for="password">Mot de passe</label>
            <input class="form-control" type="password" id="password" name="password" required>
        </div>
        <div class="col-12">
            <button class="btn btn-enoa" type="submit">Se connecter</button>
        </div>
        <div class="col-12">
            <a href="index.php?controller=auth&action=register">Créer un compte</a>
        </div>
    </div>
</form>
