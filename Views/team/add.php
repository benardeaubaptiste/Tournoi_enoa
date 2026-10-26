<?php $title = 'Inscrire une équipe'; ?>

<div class="page-heading">
    <div>
        <a class="back-link" href="index.php?controller=team&action=index&tournamentId=<?= (int) $tournamentId ?>">&larr; Retour aux équipes</a>
        <p class="eyebrow">Gestion sportive</p>
        <h2>Inscrire une équipe</h2>
    </div>
</div>

<?php $quotaAtteint = $maxEquipes > 0 && $nombreEquipesActuel >= $maxEquipes; ?>

<?php if ($error ?? null): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php elseif ($quotaAtteint): ?>
    <div class="alert alert-danger">Le nombre maximum d'équipes pour ce tournoi (<?= $maxEquipes ?>) est déjà atteint. Modifiez ce réglage dans les paramètres du tournoi pour en inscrire davantage.</div>
<?php endif; ?>

<form class="form-card" method="post" <?= $quotaAtteint ? 'aria-disabled="true"' : '' ?>>
    <?= \tournoi_enoa\Core\Csrf::field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="nom">Nom de l'équipe</label>
            <input class="form-control" type="text" id="nom" name="nom" maxlength="120" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="club">Club</label>
            <input class="form-control" type="text" id="club" name="club" maxlength="120">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="nombre_joueurs">Nombre de joueurs</label>
            <input class="form-control" type="number" id="nombre_joueurs" name="nombre_joueurs" min="1" value="1" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="prix_par_joueur">Prix par joueur</label>
            <input class="form-control" type="text" id="prix_par_joueur" value="<?= number_format($prixParJoueur, 2, ',', ' ') ?> €" readonly tabindex="-1">
            <div class="form-text">Défini dans les réglages de ce tournoi.</div>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="montant_a_payer">Montant à payer</label>
            <input class="form-control" type="number" id="montant_a_payer" name="montant_a_payer" min="0" step="0.01" value="10" readonly tabindex="-1">
            <div class="form-text">Calculé automatiquement : nombre de joueurs &times; prix par joueur.</div>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="montant_paye">Montant déjà payé</label>
            <input class="form-control" type="number" id="montant_paye" name="montant_paye" min="0" step="0.01" value="0">
        </div>
        <div class="col-md-6">
            <label class="form-label d-block">Statut de paiement</label>
            <div id="statut_paiement_badge" class="payment-status"></div>
        </div>

        <div class="col-12">
            <button class="btn btn-enoa" type="submit" <?= $quotaAtteint ? 'disabled' : '' ?>>Enregistrer l'équipe</button>
        </div>
    </div>
</form>

<script>
(function () {
    const prix = <?= json_encode($prixParJoueur) ?>;
    const nombreJoueurs = document.getElementById('nombre_joueurs');
    const montantAPayer = document.getElementById('montant_a_payer');
    const montantPaye = document.getElementById('montant_paye');
    const badge = document.getElementById('statut_paiement_badge');

    function formatMontant(valeur) {
        return valeur.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
    }

    function majStatut() {
        const joueurs = Math.max(1, parseInt(nombreJoueurs.value, 10) || 0);
        const aPayer = joueurs * prix;

        montantAPayer.value = aPayer.toFixed(2);
        montantPaye.max = aPayer;

        let paye = parseFloat(montantPaye.value) || 0;
        if (paye > aPayer) {
            paye = aPayer;
            montantPaye.value = aPayer.toFixed(2);
        }

        const reste = Math.max(0, aPayer - paye);

        if (aPayer > 0 && paye >= aPayer) {
            badge.innerHTML = '<span class="badge bg-success">✅ Payé</span>';
        } else if (paye > 0) {
            badge.innerHTML = '<span class="badge bg-warning text-dark">⚠️ Partiel — reste ' + formatMontant(reste) + '</span>';
        } else {
            badge.innerHTML = '<span class="badge bg-danger">⚠️ Non payé — reste ' + formatMontant(reste) + '</span>';
        }
    }

    [nombreJoueurs, montantPaye].forEach(function (input) {
        input.addEventListener('input', majStatut);
    });

    majStatut();
})();
</script>
