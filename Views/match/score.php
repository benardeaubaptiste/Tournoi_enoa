<?php
$title = 'Saisir un score';

// Pré-remplit avec le score/statut déjà enregistré du match ; si le formulaire vient
// d'être soumis avec une erreur (égalité, statut invalide...), on garde ce que l'admin a saisi.
$defaultScoreA = $_POST['score_team_a'] ?? ($match->score_team_a ?? 0);
$defaultScoreB = $_POST['score_team_b'] ?? ($match->score_team_b ?? 0);
$defaultStatus = $_POST['status'] ?? ($match->status ?? 'live');
?>

<div class="page-heading">
    <div>
        <a class="back-link" href="index.php?controller=match&action=index&tournamentId=<?= (int) $tournamentId ?>">&larr; Retour aux matchs</a>
        <p class="eyebrow">Compétition</p>
        <h2>Saisir le score</h2>
    </div>
</div>

<?php if (!$match): ?>
    <section class="empty">
        <h3>Match introuvable</h3>
    </section>
<?php else: ?>

    <p class="text-muted mb-3">Match #<?= (int) $match->match_number ?> — <?= htmlspecialchars($match->team_a_name ?? 'À définir') ?> (équipe #<?= (int) $match->team_a_id ?>) <small class="text-muted">vs</small> <?= htmlspecialchars($match->team_b_name ?? 'À définir') ?> (équipe #<?= (int) $match->team_b_id ?>)</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form class="form-card" method="post">
        <?= \tournoi_enoa\Core\Csrf::field() ?>
        <input type="hidden" name="team_a_id" value="<?= (int) $match->team_a_id ?>">
        <input type="hidden" name="team_b_id" value="<?= (int) $match->team_b_id ?>">
        <input type="hidden" name="back" value="match">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="score_team_a"><?= htmlspecialchars($match->team_a_name ?? 'Équipe A') ?></label>
                <input class="form-control" type="number" id="score_team_a" name="score_team_a" min="0" value="<?= htmlspecialchars((string) $defaultScoreA) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="score_team_b"><?= htmlspecialchars($match->team_b_name ?? 'Équipe B') ?></label>
                <input class="form-control" type="number" id="score_team_b" name="score_team_b" min="0" value="<?= htmlspecialchars((string) $defaultScoreB) ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label" for="status">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="live" <?= $defaultStatus === 'live' ? 'selected' : '' ?>>En cours</option>
                    <option value="finished" <?= $defaultStatus === 'finished' ? 'selected' : '' ?>>Terminé</option>
                    <option value="cancelled" <?= $defaultStatus === 'cancelled' ? 'selected' : '' ?>>Annulé</option>
                </select>
            </div>
            <div class="col-12">
                <button class="btn btn-enoa" type="submit">Enregistrer le score</button>
            </div>
        </div>
    </form>

<?php endif; ?>
