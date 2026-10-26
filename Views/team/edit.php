<?php $title = 'Modifier l\'équipe'; ?>

<div class="page-heading">
    <div>
        <a class="back-link" href="index.php?controller=team&action=show&teamId=<?= (int) $team->id ?>&tournamentId=<?= (int) $tournamentId ?>">&larr; Retour à l'équipe</a>
        <p class="eyebrow">Gestion sportive</p>
        <h2>Modifier — <?= htmlspecialchars($team->nom) ?></h2>
    </div>
</div>

<?php if ($error ?? null): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form class="form-card" method="post">
    <?= \tournoi_enoa\Core\Csrf::field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="nom">Nom de l'équipe</label>
            <input class="form-control" type="text" id="nom" name="nom" maxlength="120" value="<?= htmlspecialchars($team->nom) ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="club">Club</label>
            <input class="form-control" type="text" id="club" name="club" maxlength="120" value="<?= htmlspecialchars($team->club ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="nombre_joueurs">Nombre de joueurs</label>
            <input class="form-control" type="number" id="nombre_joueurs" name="nombre_joueurs" min="1" value="<?= (int) $team->nombre_joueurs ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="prix_par_joueur">Prix par joueur</label>
            <input class="form-control" type="number" id="prix_par_joueur" name="prix_par_joueur" min="0" step="0.01" value="<?= (float) $team->prix_par_joueur ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="montant_a_payer">Montant à payer</label>
            <input class="form-control" type="text" id="montant_a_payer" readonly tabindex="-1">
            <div class="form-text">Calculé automatiquement : nombre de joueurs &times; prix par joueur.</div>
        </div>
        <div class="col-md-6">
            <label class="form-label d-block">Paiement déjà enregistré</label>
            <div class="form-control-plaintext">
                <?= number_format((float) $team->montant_paye, 2, ',', ' ') ?> € — <span id="statut_paiement_badge" class="payment-status"></span>
            </div>
            <div class="form-text">Le paiement se modifie depuis <a href="index.php?controller=team&action=editPayment&teamId=<?= (int) $team->id ?>&tournamentId=<?= (int) $tournamentId ?>">la page dédiée</a>. Réduire le montant à payer ci-dessus peut changer ce statut si le montant déjà payé le dépasse désormais.</div>
        </div>

        <div class="col-12">
            <button class="btn btn-enoa" type="submit">Enregistrer les modifications</button>
        </div>
    </div>
</form>

<script>
(function () {
    const montantDejaPaye = <?= json_encode((float) $team->montant_paye) ?>;
    const nombreJoueurs = document.getElementById('nombre_joueurs');
    const prixParJoueur = document.getElementById('prix_par_joueur');
    const montantAPayer = document.getElementById('montant_a_payer');
    const badge = document.getElementById('statut_paiement_badge');

    function formatMontant(valeur) {
        return valeur.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
    }

    function majStatut() {
        const joueurs = Math.max(1, parseInt(nombreJoueurs.value, 10) || 0);
        const prix = Math.max(0, parseFloat(prixParJoueur.value) || 0);
        const aPayer = joueurs * prix;
        const paye = Math.min(montantDejaPaye, aPayer);

        montantAPayer.value = formatMontant(aPayer);

        if (aPayer > 0 && paye >= aPayer) {
            badge.innerHTML = '<span class="badge bg-success">✅ Payé</span>';
        } else if (paye > 0) {
            badge.innerHTML = '<span class="badge bg-warning text-dark">⚠️ Partiel</span>';
        } else {
            badge.innerHTML = '<span class="badge bg-danger">⚠️ Non payé</span>';
        }
    }

    [nombreJoueurs, prixParJoueur].forEach(function (input) {
        input.addEventListener('input', majStatut);
    });

    majStatut();
})();
</script>
