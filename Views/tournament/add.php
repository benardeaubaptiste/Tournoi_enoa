<?php $title = 'Nouveau tournoi'; ?>

<div class="page-heading">
    <div>
        <a class="back-link" href="index.php?controller=tournament&action=index">&larr; Retour aux tournois</a>
        <p class="eyebrow">Administration</p>
        <h2>Nouveau tournoi</h2>
    </div>
</div>

<form class="form-card" method="post">
    <?= \tournoi_enoa\Core\Csrf::field() ?>
    <div class="row g-3">

        <div class="col-md-6">
            <label class="form-label" for="titre">Titre</label>
            <input class="form-control" type="text" id="titre" name="titre" maxlength="150" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="date_tournoi">Date du tournoi</label>
            <input class="form-control" type="date" id="date_tournoi" name="date_tournoi" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="prix_joueur">Prix par joueur</label>
            <input class="form-control" type="number" id="prix_joueur" name="prix_joueur" min="0" step="0.01" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="statut">Statut</label>
            <select class="form-select" id="statut" name="statut">
                <option value="draft">Brouillon</option>
                <option value="inscriptions">Inscriptions</option>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="date_limite_inscriptions">Date limite d'inscription</label>
            <input class="form-control" type="date" id="date_limite_inscriptions" name="date_limite_inscriptions" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="date_limite_paiement">Date limite de paiement</label>
            <input class="form-control" type="date" id="date_limite_paiement" name="date_limite_paiement" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="heure_debut_tournoi">Heure de début du tournoi</label>
            <input class="form-control" type="time" id="heure_debut_tournoi" name="heure_debut_tournoi" value="09:00" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="heure_fin_tournoi">Heure de fin du tournoi</label>
            <input class="form-control" type="time" id="heure_fin_tournoi" name="heure_fin_tournoi" value="18:00" required>
        </div>
        <div class="col-12">
            <p class="form-text text-muted mb-0">Les horaires de qualifications et de phase finale se déduisent automatiquement de ce début/fin de journée. Les autres réglages (nombre de poules, points...) sont pré-remplis avec des valeurs par défaut et modifiables ensuite depuis la page Paramètres du tournoi.</p>
        </div>
        <div class="col-12">
            <button class="btn btn-enoa" type="submit">Créer le tournoi</button>
        </div>
    </div>
</form>
