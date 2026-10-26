<?php $title = 'Modifier le paiement'; ?>

<div class="page-heading">
    <div>
        <a class="back-link" href="index.php?controller=team&action=show&teamId=<?= (int) $team->id ?>&tournamentId=<?= (int) $tournamentId ?>">&larr; Retour à l'équipe</a>
        <p class="eyebrow">Gestion sportive</p>
        <h2>Paiement — <?= htmlspecialchars($team->nom) ?></h2>
    </div>
</div>

<form class="form-card" method="post">
    <?= \tournoi_enoa\Core\Csrf::field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Montant à payer</label>
            <input class="form-control" type="text" value="<?= number_format((float) $team->montant_a_payer, 2, ',', ' ') ?> €" readonly tabindex="-1">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="montant_paye">Montant payé</label>
            <input class="form-control" type="number" id="montant_paye" name="montant_paye" min="0" max="<?= (float) $team->montant_a_payer ?>" step="0.01" value="<?= (float) $team->montant_paye ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label d-block">Statut de paiement</label>
            <div id="statut_paiement_badge" class="payment-status"></div>
        </div>

        <div class="col-12">
            <button class="btn btn-enoa" type="submit">Enregistrer le paiement</button>
        </div>
    </div>
</form>

<script>
(function () {
    const aPayer = <?= json_encode((float) $team->montant_a_payer) ?>;
    const montantPaye = document.getElementById('montant_paye');
    const badge = document.getElementById('statut_paiement_badge');

    function formatMontant(valeur) {
        return valeur.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
    }

    function majStatut() {
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

    montantPaye.addEventListener('input', majStatut);
    majStatut();
})();
</script>
