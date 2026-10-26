<?php
/**
 * Suivi live : reprend le minuteur complet (_scheduleWidget.php, en version agrandie) et
 * affiche les prochains matchs à jouer dans ce même bloc, autant que de terrains configurés
 * (ex. 3 terrains -> 3 prochains matchs), triés par horaire — voir MatchController::live() /
 * MatchModel::upcoming(). Pensée pour être affichée sur un grand écran (bord de terrain) :
 * le bloc minuteur est volontairement plus gros que sur les autres pages ($liveMode côté
 * widget), sur la largeur de page habituelle. Accessible depuis la nav sans tournoi précisé :
 * celui-ci est alors résolu côté contrôleur (tournoi en cours, ou le plus récent à défaut).
 */
$title = 'Suivi live' . ($tournament ? ' - ' . $tournament->titre : '');
$liveMode = true;

// Le premier prochain match donne une idée fiable de l'étape en cours pour le minuteur
// (qualification tant qu'il en reste à jouer, sinon phase finale).
$timerPhase = ($upcomingMatches && $upcomingMatches[0]->phase !== 'qualification') ? 'phase_finale' : 'qualification';
?>

<div class="page-heading">
    <div>
        <?php if ($tournament): ?>
            <a class="back-link" href="index.php?controller=home&action=index&id=<?= (int) $tournamentId ?>">&larr; Retour au tournoi</a>
        <?php endif; ?>
        <p class="eyebrow">Suivi live</p>
        <h2><?= $tournament ? htmlspecialchars($tournament->titre) : 'Suivi live' ?></h2>
    </div>
    <?php if ($tournament): ?>
        <a class="btn btn-outline-primary btn-sm" href="index.php?controller=match&action=index&tournamentId=<?= (int) $tournamentId ?>">Voir tout le planning</a>
    <?php endif; ?>
</div>

<?php if (!$tournament): ?>

    <section class="empty">
        <h3>Aucun tournoi</h3>
        <p class="mb-0">Créez un tournoi pour accéder au suivi live.</p>
    </section>

<?php else: ?>

    <?php include dirname(__DIR__) . '/_scheduleWidget.php'; ?>

<?php endif; ?>
