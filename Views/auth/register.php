<?php $title = 'Créer un compte - Tournoi ENOA'; ?>

<div class="page-heading">
    <div>
        <p class="eyebrow">Nouveau compte</p>
        <h2>Créer un compte gestionnaire</h2>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form class="form-card login-card" method="post">
    <?= \tournoi_enoa\Core\Csrf::field() ?>
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label" for="display_name">Nom affiché</label>
            <input class="form-control" type="text" id="display_name" name="display_name" maxlength="120" required autofocus
                value="<?= htmlspecialchars($_POST['display_name'] ?? '') ?>">
        </div>
        <div class="col-12">
            <label class="form-label" for="username">Identifiant</label>
            <input class="form-control" type="text" id="username" name="username" maxlength="80" required
                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="col-12">
            <label class="form-label" for="password">Mot de passe</label>
            <input class="form-control" type="password" id="password" name="password" minlength="8" required>
        </div>
        <div class="col-12">
            <label class="form-label" for="password_confirm">Confirmer le mot de passe</label>
            <input class="form-control" type="password" id="password_confirm" name="password_confirm" minlength="8" required>
        </div>
        <div class="col-12">
            <label class="form-label" for="registration_code">Code d'inscription</label>
            <input class="form-control" type="text" id="registration_code" name="registration_code" required
                autocomplete="off">
            <div class="form-text">Communiqué par l'organisateur du tournoi.</div>
        </div>
        <div class="col-12">
            <button class="btn btn-enoa" type="submit">Créer mon compte</button>
        </div>
        <div class="col-12">
            <a href="index.php?controller=auth&action=login">J'ai déjà un compte</a>
        </div>
    </div>
</form>
