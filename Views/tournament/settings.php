<?php
$title = 'Paramètres - ' . $tournament->titre;

// Les inputs type="time" attendent HH:MM ; la BDD renvoie HH:MM:SS.
$time = static fn (?string $value): string => $value ? substr($value, 0, 5) : '';
$duree = static fn (int $minutes): string => intdiv($minutes, 60) > 0 ? sprintf('%dh%02d', intdiv($minutes, 60), $minutes % 60) : $minutes . ' min';
?>

<div class="page-heading">
    <div>
        <a class="back-link" href="index.php?controller=home&action=index&id=<?= (int) $tournament->id ?>">&larr; Retour au tableau de bord</a>
        <p class="eyebrow">Administration</p>
        <h2>Paramètres — <?= htmlspecialchars($tournament->titre) ?></h2>
    </div>
</div>

<?php if (!$settings): ?>
    <section class="empty">
        <h3>Réglages indisponibles</h3>
        <p class="mb-0">Aucune configuration trouvée pour ce tournoi.</p>
    </section>
<?php else: ?>

<?php if ($errors): ?>
    <div class="alert alert-danger" role="alert">
        <p class="mb-1"><strong>Les paramètres n'ont pas été enregistrés :</strong></p>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php elseif (!$schedule['qualification']['faisable'] || (!$schedule['phase_finale']['faisable'] && $schedule['phase_finale']['nombre_matchs'] > 0)): ?>
    <div class="alert alert-warning" role="alert">
        La fenêtre horaire est trop courte pour caser tous les matchs prévus : élargis l'heure de début/fin du tournoi, ajoute des terrains, ou réduis le battement entre matchs.
    </div>
<?php endif; ?>

<form class="form-card" method="post">
    <?= \tournoi_enoa\Core\Csrf::field() ?>
    <h3 class="mb-3">Horaires du tournoi</h3>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <label class="form-label" for="heure_debut_tournoi">Heure de début</label>
            <input class="form-control" type="time" id="heure_debut_tournoi" name="heure_debut_tournoi" value="<?= htmlspecialchars($time($settings->heure_debut_tournoi)) ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="heure_fin_tournoi">Heure de fin</label>
            <input class="form-control" type="time" id="heure_fin_tournoi" name="heure_fin_tournoi" value="<?= htmlspecialchars($time($settings->heure_fin_tournoi)) ?>" required>
        </div>
        <div class="col-12">
            <p class="form-text text-muted mb-0">
                Les horaires de qualifications et de phase finale ci-dessous se déduisent automatiquement de ce début/fin de journée,
                au prorata du temps que prend chaque phase :
                qualifications <strong><?= htmlspecialchars($time($schedule['heure_debut_qualifications'])) ?> → <?= htmlspecialchars($time($schedule['heure_fin_qualifications'])) ?></strong>,
                puis phase finale <strong><?= htmlspecialchars($time($schedule['heure_debut_phase_finale'])) ?> → <?= htmlspecialchars($time($schedule['heure_fin_phase_finale'])) ?></strong>.
            </p>
        </div>
    </div>

    <h3 class="mb-3">Poules &amp; terrains</h3>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <label class="form-label" for="nombre_max_equipes">Nombre max d'équipes</label>
            <input class="form-control" type="number" id="nombre_max_equipes" name="nombre_max_equipes" min="8" max="20" value="<?= (int) $settings->nombre_max_equipes ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="nombre_poules">Nombre de poules</label>
            <input class="form-control" type="number" id="nombre_poules" name="nombre_poules" min="2" max="4" value="<?= (int) $settings->nombre_poules ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="mode_repartition">Répartition en poules</label>
            <select class="form-select" id="mode_repartition" name="mode_repartition">
                <option value="ordre_inscription" <?= $settings->mode_repartition === 'ordre_inscription' ? 'selected' : '' ?>>Ordre d'inscription</option>
                <option value="aleatoire" <?= $settings->mode_repartition === 'aleatoire' ? 'selected' : '' ?>>Aléatoire</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="nombre_terrains">Nombre de terrains</label>
            <input class="form-control" type="number" id="nombre_terrains" name="nombre_terrains" min="1" max="20" value="<?= (int) $settings->nombre_terrains ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="nombre_equipes_upper">Équipes en poule haute</label>
            <input class="form-control" type="number" id="nombre_equipes_upper" name="nombre_equipes_upper" min="0" value="<?= (int) $settings->nombre_equipes_upper ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="nombre_equipes_lower">Équipes en poule basse</label>
            <input class="form-control" type="number" id="nombre_equipes_lower" name="nombre_equipes_lower" min="0" value="<?= (int) $settings->nombre_equipes_lower ?>" required>
        </div>
    </div>

    <h3 class="mb-3">Qualifications</h3>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <label class="form-label">Heure de début</label>
            <input class="form-control" type="text" value="<?= htmlspecialchars($time($schedule['heure_debut_qualifications'])) ?>" disabled>
        </div>
        <div class="col-md-3">
            <label class="form-label">Heure de fin</label>
            <input class="form-control" type="text" value="<?= htmlspecialchars($time($schedule['heure_fin_qualifications'])) ?>" disabled>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="temps_entre_matchs_qualification">Battement entre matchs (min)</label>
            <input class="form-control" type="number" id="temps_entre_matchs_qualification" name="temps_entre_matchs_qualification" min="0" value="<?= (int) $settings->temps_entre_matchs_qualification ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="max_matchs_consecutifs_qualifications">Matchs consécutifs max par équipe</label>
            <input class="form-control" type="number" id="max_matchs_consecutifs_qualifications" name="max_matchs_consecutifs_qualifications" min="1" value="<?= (int) $settings->max_matchs_consecutifs_qualifications ?>" required>
        </div>
        <div class="col-12">
            <p class="form-text text-muted mb-0">
                Durée de match calculée automatiquement à partir de la fenêtre ci-dessus : fenêtre de <?= $duree($schedule['qualification']['fenetre_minutes']) ?>
                pour <?= $schedule['qualification']['nombre_matchs'] ?> matchs sur <?= (int) $settings->nombre_terrains ?> terrain(s)
                (<?= $schedule['qualification']['creneaux'] ?> créneau<?= $schedule['qualification']['creneaux'] > 1 ? 'x' : '' ?>)
                → <strong><?= $schedule['qualification']['duree_match'] ?> min par match</strong>.
            </p>
        </div>
    </div>

    <h3 class="mb-3">Phase finale</h3>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <label class="form-label">Heure de début</label>
            <input class="form-control" type="text" value="<?= htmlspecialchars($time($schedule['heure_debut_phase_finale'])) ?>" disabled>
        </div>
        <div class="col-md-3">
            <label class="form-label">Heure de fin</label>
            <input class="form-control" type="text" value="<?= htmlspecialchars($time($schedule['heure_fin_phase_finale'])) ?>" disabled>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="temps_entre_matchs_phase_finale">Battement entre matchs (min)</label>
            <input class="form-control" type="number" id="temps_entre_matchs_phase_finale" name="temps_entre_matchs_phase_finale" min="0" value="<?= (int) $settings->temps_entre_matchs_phase_finale ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="max_matchs_consecutifs_phase_finale">Matchs consécutifs max par équipe</label>
            <input class="form-control" type="number" id="max_matchs_consecutifs_phase_finale" name="max_matchs_consecutifs_phase_finale" min="1" value="<?= (int) $settings->max_matchs_consecutifs_phase_finale ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="duree_grande_finale">Durée de la grande finale (min)</label>
            <input class="form-control" type="number" id="duree_grande_finale" name="duree_grande_finale" min="1" value="<?= (int) $grandFinaleDuration ?>" required>
        </div>
        <div class="col-12">
            <p class="form-text text-muted mb-0">
                <?php if ($schedule['phase_finale']['nombre_matchs'] > 0): ?>
                    Durée de match calculée automatiquement : fenêtre de <?= $duree($schedule['phase_finale']['fenetre_minutes']) ?>
                    pour <?= $schedule['phase_finale']['nombre_matchs'] ?> matchs de bracket sur <?= (int) $settings->nombre_terrains ?> terrain(s)
                    (<?= $schedule['phase_finale']['creneaux'] ?> créneau<?= $schedule['phase_finale']['creneaux'] > 1 ? 'x' : '' ?>),
                    <?= $grandFinaleDuration ?> min réservées pour la grande finale
                    → <strong><?= $schedule['phase_finale']['duree_match'] ?> min par match</strong>.
                <?php else: ?>
                    Aucun match de bracket à jouer avec ces effectifs<?= $schedule['phase_finale']['grande_finale'] ? ' : place directement à la grande finale.' : '.' ?>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <h3 class="mb-3">Points &amp; tarifs</h3>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <label class="form-label" for="points_victoire">Points par victoire</label>
            <input class="form-control" type="number" id="points_victoire" name="points_victoire" min="0" step="0.5" value="<?= (float) $settings->points_victoire ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="points_defaite">Points par défaite</label>
            <input class="form-control" type="number" id="points_defaite" name="points_defaite" min="0" step="0.5" value="<?= (float) $settings->points_defaite ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="prix_par_joueur">Prix par joueur (€)</label>
            <input class="form-control" type="number" id="prix_par_joueur" name="prix_par_joueur" min="0" step="0.01" value="<?= (float) $settings->prix_par_joueur ?>" required>
        </div>
    </div>

    <button class="btn btn-enoa" type="submit">Enregistrer les paramètres</button>
</form>

<?php endif; ?>
