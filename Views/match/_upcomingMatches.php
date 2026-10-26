<?php
/**
 * Contenu du bloc "Prochains matchs" du minuteur (voir _scheduleWidget.php), extrait dans
 * son propre partial pour pouvoir être rafraîchi seul en AJAX depuis la page de suivi live
 * (MatchController::liveUpcoming(), appelée à intervalle régulier) sans jamais toucher au
 * minuteur qui l'entoure : son décompte ne vit que côté client, un remplacement du HTML
 * autour le remettrait à zéro.
 *
 * @var ?object[] $upcomingMatches MatchModel::upcoming() — déjà limité aux matchs pas
 *                                 encore joués, triés par horaire.
 */
?>
<h4 class="schedule-widget__title">📋 Prochains matchs</h4>
<?php if (!$upcomingMatches): ?>
    <p class="schedule-widget__empty mb-0">Aucun match à venir pour l'instant.</p>
<?php else: ?>
    <div class="schedule-widget__upcoming-grid">
        <?php foreach ($upcomingMatches as $match): ?>
            <div class="schedule-widget__match">
                <span class="schedule-widget__match-top">
                    <span class="schedule-widget__match-court"><?= htmlspecialchars($match->court_name ?? 'Terrain ?') ?></span>
                    <span class="schedule-widget__match-time"><?= $match->scheduled_start ? date('H:i', strtotime($match->scheduled_start)) : '—' ?></span>
                </span>
                <span class="schedule-widget__match-teams"><?= htmlspecialchars($match->team_a_name ?? 'À définir') ?> <em>vs</em> <?= htmlspecialchars($match->team_b_name ?? 'À définir') ?></span>
                <span class="schedule-widget__match-phase"><?= htmlspecialchars($match->round_name ?: $match->phase) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
