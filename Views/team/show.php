<?php $title = 'Détails de l\'équipe'; ?>

<div class="page-heading">
    <div>
        <a class="back-link" href="index.php?controller=team&action=index&tournamentId=<?= (int) $tournamentId ?>">&larr; Retour aux équipes</a>
        <p class="eyebrow">Gestion sportive</p>
        <h2><?= htmlspecialchars($team->nom) ?></h2>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-primary" href="index.php?controller=team&action=edit&teamId=<?= (int) $team->id ?>&tournamentId=<?= (int) $tournamentId ?>">✏️ Modifier l'équipe</a>
        <a class="btn btn-enoa" href="index.php?controller=team&action=editPayment&teamId=<?= (int) $team->id ?>&tournamentId=<?= (int) $tournamentId ?>">✏️ Modifier le paiement</a>
        <form method="post" action="index.php?controller=team&action=delete&teamId=<?= (int) $team->id ?>&tournamentId=<?= (int) $tournamentId ?>">
            <?= \tournoi_enoa\Core\Csrf::field() ?>
            <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Supprimer définitivement l\'équipe « <?= htmlspecialchars(addslashes($team->nom)) ?> » et ses joueurs ?');">🗑️ Supprimer</button>
        </form>
    </div>
</div>

<?php if (($_GET['deleteError'] ?? null) === 'already_played'): ?>
    <div class="alert alert-warning">Impossible de supprimer cette équipe : elle a déjà des matchs générés (joués ou non). Retire-la des poules et régénère la qualification, ou déclare-la forfait, si tu veux vraiment l'écarter du tournoi.</div>
<?php endif; ?>

<?php
$teamStatusBadges = [
    'inscrite'  => 'bg-secondary',
    'confirmee' => 'bg-success',
    'forfait'   => 'bg-danger',
    'elimine'   => 'bg-dark',
];
$teamStatusLabels = [
    'inscrite'  => 'Inscrite',
    'confirmee' => 'Confirmée',
    'forfait'   => 'Forfait',
    'elimine'   => 'Éliminée',
];
?>
<div class="d-flex align-items-center gap-3 mb-3">
    <span>Statut sportif : <span class="badge <?= $teamStatusBadges[$team->statut] ?? 'bg-secondary' ?>"><?= htmlspecialchars($teamStatusLabels[$team->statut] ?? $team->statut) ?></span></span>
    <form method="post" action="index.php?controller=team&action=updateStatus&teamId=<?= (int) $team->id ?>&tournamentId=<?= (int) $tournamentId ?>" class="d-flex align-items-center gap-2">
        <?= \tournoi_enoa\Core\Csrf::field() ?>
        <select name="statut" class="form-select form-select-sm" style="width:auto">
            <?php foreach ($teamStatusLabels as $value => $label): ?>
                <option value="<?= $value ?>" <?= $team->statut === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-sm btn-outline-primary">Changer le statut</button>
    </form>
</div>

<div class="table-wrap">
    <div class="p-3">
        <dl class="row mb-0">
            <dt class="col-sm-4">Club</dt>
            <dd class="col-sm-8"><?= htmlspecialchars($team->club ?? 'Indépendante') ?></dd>

            <dt class="col-sm-4">Nombre de joueurs</dt>
            <dd class="col-sm-8"><?= (int) $team->nombre_joueurs ?></dd>

            <dt class="col-sm-4">Prix par joueur</dt>
            <dd class="col-sm-8"><?= number_format((float) $team->prix_par_joueur, 2, ',', ' ') ?> €</dd>

            <dt class="col-sm-4">Montant à payer</dt>
            <dd class="col-sm-8"><?= number_format((float) $team->montant_a_payer, 2, ',', ' ') ?> €</dd>

            <dt class="col-sm-4">Montant payé</dt>
            <dd class="col-sm-8"><?= number_format((float) $team->montant_paye, 2, ',', ' ') ?> €</dd>

            <dt class="col-sm-4">Statut de paiement</dt>
            <dd class="col-sm-8">
                <?php $reste = max(0, (float) $team->montant_a_payer - (float) $team->montant_paye); ?>
                <?php if ($team->statutPaiementReel() === 'paye'): ?>
                    <span class="badge bg-success">✅ Payé</span>
                <?php elseif ($team->statutPaiementReel() === 'partiel'): ?>
                    <span class="badge bg-warning text-dark">⚠️ Partiel — reste <?= number_format($reste, 2, ',', ' ') ?> €</span>
                <?php else: ?>
                    <span class="badge bg-danger">⚠️ Non payé — reste <?= number_format($reste, 2, ',', ' ') ?> €</span>
                <?php endif; ?>
            </dd>
        </dl>
    </div>
</div>

<div class="mt-3">
    <a class="btn btn-outline-primary" href="index.php?controller=team&action=players&teamId=<?= (int) $team->id ?>&tournamentId=<?= (int) $tournamentId ?>">Voir les joueurs</a>
</div>
