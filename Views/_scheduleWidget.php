<?php
/**
 * Récap des durées calculées (qualifications, phase finale, grande finale) + minuteur de
 * match, partagé entre pool/index.php, match/index.php, match/bracket.php et match/live.php
 * (cette dernière en version agrandie, $liveMode, avec les prochains matchs en plus, voir
 * $upcomingMatches).
 *
 * Le minuteur propose un bouton par étape du tournoi (qualification / phase finale / grande
 * finale), chacun avec SA propre durée (calculée pour qualification/phase finale, réglée dans les paramètres pour la grande finale) : les trois
 * durées sont presque toujours différentes, et un admin peut avoir besoin de chronométrer
 * n'importe laquelle depuis n'importe quelle page (pas seulement celle qu'il consulte).
 *
 * @var ?object   $settings        tournament_settings du tournoi (TournamentModel::settings())
 * @var string    $timerPhase      'qualification' (par défaut) ou 'phase_finale' : quel bouton
 *                                 est sélectionné par défaut à l'arrivée sur cette page (les
 *                                 trois restent toujours disponibles, voir plus haut).
 * @var ?object[] $upcomingMatches Prochains matchs à afficher sous le minuteur, dans le même
 *                                 bloc (MatchModel::upcoming()) ; null (par défaut) masque
 *                                 entièrement cette section — seule match/live.php la remplit.
 * @var bool      $liveMode        true sur la page de suivi live (match/live.php) : masque le
 *                                 récap des durées calculées (pas utile sur cette page, pensée
 *                                 pour n'afficher qu'un gros minuteur + les prochains matchs),
 *                                 agrandit le reste du bloc (minuteur, matchs) pour un affichage
 *                                 sur grand écran, et fait rafraîchir automatiquement les
 *                                 prochains matchs (MatchController::liveUpcoming(), en AJAX,
 *                                 sans jamais toucher au minuteur) pour qu'un écran laissé
 *                                 ouvert sans interaction suive le tournoi. false partout ailleurs.
 * @var int|null  $tournamentId    Déjà dans la portée de la vue incluante (non extrait ici) :
 *                                 sert d'identifiant pour l'appel AJAX ci-dessus en mode live.
 */
$timerPhase = $timerPhase ?? 'qualification';
$upcomingMatches = $upcomingMatches ?? null;
$liveMode = $liveMode ?? false;

$time = static fn (?string $value): string => $value ? substr($value, 0, 5) : '—';
$duree = static fn (?int $minutes): string => $minutes ? $minutes . ' min' : '—';

// Le calcul n'a réellement eu lieu qu'une fois les réglages horaires enregistrés (voir
// TournamentController::computeSchedule) : avant ça, les colonnes duree_match_* ne sont
// que les valeurs par défaut de la base, pas une durée calculée pour ce tournoi. La grande
// finale, elle, a une durée réglée indépendamment de ce calcul (tournament_settings.duree_grande_finale) : toujours disponible.
$scheduleComputed = $settings && $settings->heure_debut_qualifications;
$qualDureeMin = $scheduleComputed ? (int) $settings->duree_match_qualification : 0;
$finaleDureeMin = $scheduleComputed && $settings->duree_match_phase_finale ? (int) $settings->duree_match_phase_finale : 0;
$grandFinaleDureeMin = $settings ? (int) $settings->duree_grande_finale : \tournoi_enoa\Core\ScheduleCalculator::GRAND_FINAL_DURATION_MINUTES;

$timerStages = [
    'qualification' => [
        'label'   => 'Qualification',
        'minutes' => $qualDureeMin,
        'hint'    => "Durée d'un match de qualification (" . $qualDureeMin . ' min), calculée automatiquement.',
    ],
    'phase_finale' => [
        'label'   => 'Phase finale',
        'minutes' => $finaleDureeMin,
        'hint'    => "Durée d'un match de phase finale (" . $finaleDureeMin . ' min), calculée automatiquement.',
    ],
    'grande_finale' => [
        'label'   => 'Grande finale',
        'minutes' => $grandFinaleDureeMin,
        'hint'    => 'Durée de la grande finale (' . $grandFinaleDureeMin . ' min), réglable dans les paramètres.',
    ],
];

// Bouton actif par défaut à l'arrivée sur la page ($timerPhase), sauf si sa durée n'est pas
// encore calculable : on retombe alors sur la première étape disponible (la grande finale
// l'est toujours, sa durée étant fixe).
$defaultStage = $timerPhase === 'phase_finale' ? 'phase_finale' : 'qualification';
if ($timerStages[$defaultStage]['minutes'] <= 0) {
    foreach ($timerStages as $key => $stage) {
        if ($stage['minutes'] > 0) {
            $defaultStage = $key;
            break;
        }
    }
}
$hasAnyDuration = (bool) array_filter($timerStages, static fn (array $s): bool => $s['minutes'] > 0);
?>
<div class="schedule-widget<?= $liveMode ? ' schedule-widget--live' : '' ?>">
    <?php if (!$liveMode): ?>
    <div class="schedule-widget__durees">
        <h4 class="schedule-widget__title">⏱️ Durées calculées</h4>
        <?php if (!$scheduleComputed): ?>
            <p class="schedule-widget__empty mb-0">Enregistre les réglages du tournoi (horaires) pour calculer les durées de match.</p>
        <?php else: ?>
            <ul class="schedule-widget__list">
                <li><span>Qualifications</span><strong><?= $time($settings->heure_debut_qualifications) ?> → <?= $time($settings->heure_fin_qualifications) ?></strong><em><?= $duree($qualDureeMin) ?> / match</em></li>
                <li><span>Phase finale</span><strong><?= $time($settings->heure_debut_phase_finale) ?> → <?= $time($settings->heure_fin_phase_finale) ?></strong><em><?= $duree($finaleDureeMin ?: null) ?> / match</em></li>
                <li><span>Grande finale</span><em><?= $grandFinaleDureeMin ?> min</em></li>
            </ul>
        <?php endif; ?>
    </div>

    <div class="schedule-widget__sep-v"></div>
    <?php endif; ?>

    <div class="schedule-widget__timer-wrap">
        <h4 class="schedule-widget__title">⏳ Minuteur de match</h4>
        <?php if ($hasAnyDuration): ?>
            <div class="schedule-widget__timer-modes" role="group" aria-label="Étape à chronométrer">
                <?php foreach ($timerStages as $key => $stage): ?>
                    <button type="button"
                        class="btn btn-sm js-timer-mode <?= $key === $defaultStage ? 'btn-enoa active' : 'btn-outline-light' ?>"
                        data-stage="<?= $key ?>"
                        data-duration-seconds="<?= $stage['minutes'] * 60 ?>"
                        data-duration-hint="<?= htmlspecialchars($stage['hint']) ?>"
                        <?= $stage['minutes'] <= 0 ? 'disabled title="Pas encore calculable (réglages incomplets)"' : '' ?>>
                        <?= htmlspecialchars($stage['label']) ?><?= $stage['minutes'] > 0 ? ' (' . $stage['minutes'] . ' min)' : '' ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="schedule-widget__timer" id="qualTimer" data-duration-seconds="<?= $timerStages[$defaultStage]['minutes'] * 60 ?>">
                <div class="schedule-widget__timer-display" id="qualTimerDisplay"><?= sprintf('%02d:00', $timerStages[$defaultStage]['minutes']) ?></div>
                <div class="schedule-widget__timer-actions">
                    <button type="button" class="btn btn-enoa btn-sm" id="qualTimerToggle">▶ Démarrer</button>
                    <button type="button" class="btn btn-outline-light btn-sm" id="qualTimerReset">↺ Réinitialiser</button>
                </div>
                <p class="schedule-widget__timer-hint" id="qualTimerHint"><?= htmlspecialchars($timerStages[$defaultStage]['hint']) ?></p>
            </div>

            <div class="modal fade" id="matchEndModal" tabindex="-1" aria-labelledby="matchEndModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="matchEndModalLabel">⏱️ Fin du match</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0">Le temps de match est écoulé. Pensez à siffler la fin et à saisir le score.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-enoa" data-bs-dismiss="modal">Compris</button>
                        </div>
                    </div>
                </div>
            </div>
            <script>
            (function () {
                function init() {
                    var el = document.getElementById('qualTimer');
                    if (!el) {
                        return;
                    }
                    var totalSeconds = parseInt(el.dataset.durationSeconds, 10) || 0;
                    var remaining = totalSeconds;
                    var display = document.getElementById('qualTimerDisplay');
                    var hint = document.getElementById('qualTimerHint');
                    var toggleBtn = document.getElementById('qualTimerToggle');
                    var resetBtn = document.getElementById('qualTimerReset');
                    var modeButtons = Array.prototype.slice.call(document.querySelectorAll('.js-timer-mode'));
                    var endModalEl = document.getElementById('matchEndModal');
                    // Une seule instance, créée ici et réutilisée à chaque fin de match (comme
                    // _scoreModal.php) : bootstrap.Modal.getOrCreateInstance() n'existe qu'à
                    // partir de Bootstrap 5.2, or ce projet charge la 5.0.0-beta3 (base.php).
                    var endModal = (endModalEl && typeof bootstrap !== 'undefined') ? new bootstrap.Modal(endModalEl) : null;
                    var interval = null;

                    function showMatchEndModal() {
                        // bootstrap n'est chargé qu'en bas de base.php : sur un rafraîchissement
                        // partiel avant son chargement (jamais en pratique, mais on ne bloque pas
                        // le minuteur pour autant), on saute juste le popup plutôt que de planter.
                        if (!endModal) {
                            return;
                        }
                        endModal.show();
                    }

                    function render() {
                        var m = Math.floor(remaining / 60);
                        var s = remaining % 60;
                        display.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
                        display.classList.toggle('schedule-widget__timer-display--done', remaining === 0);
                    }

                    function pause() {
                        if (interval) {
                            clearInterval(interval);
                            interval = null;
                        }
                        toggleBtn.textContent = remaining === 0 ? '▶ Démarrer' : '▶ Reprendre';
                        toggleBtn.disabled = remaining === 0;
                    }

                    function start() {
                        if (interval || remaining === 0) {
                            return;
                        }
                        toggleBtn.textContent = '⏸ Pause';
                        interval = setInterval(function () {
                            remaining = Math.max(0, remaining - 1);
                            render();
                            if (remaining === 0) {
                                pause();
                                showMatchEndModal();
                            }
                        }, 1000);
                    }

                    toggleBtn.addEventListener('click', function () {
                        if (interval) {
                            pause();
                        } else {
                            start();
                        }
                    });

                    resetBtn.addEventListener('click', function () {
                        pause();
                        remaining = totalSeconds;
                        toggleBtn.disabled = false;
                        toggleBtn.textContent = '▶ Démarrer';
                        render();
                    });

                    // Un bouton par étape du tournoi (qualification / phase finale / grande
                    // finale), chacun avec sa propre durée : sans ça, le minuteur restait
                    // toujours calé sur une seule durée, quelle que soit l'étape à chronométrer.
                    modeButtons.forEach(function (btn) {
                        if (btn.disabled) {
                            return;
                        }
                        btn.addEventListener('click', function () {
                            pause();
                            totalSeconds = parseInt(btn.dataset.durationSeconds, 10) || 0;
                            remaining = totalSeconds;
                            toggleBtn.disabled = totalSeconds === 0;
                            toggleBtn.textContent = '▶ Démarrer';
                            if (hint) {
                                hint.textContent = btn.dataset.durationHint || '';
                            }
                            modeButtons.forEach(function (b) {
                                var isActive = b === btn;
                                b.classList.toggle('active', isActive);
                                b.classList.toggle('btn-enoa', isActive);
                                b.classList.toggle('btn-outline-light', !isActive);
                            });
                            render();
                        });
                    });

                    render();
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', init);
                } else {
                    init();
                }
            })();
            </script>
        <?php else: ?>
            <p class="schedule-widget__empty mb-0">Durée de match pas encore calculable (réglages incomplets).</p>
        <?php endif; ?>
    </div>

    <?php if ($upcomingMatches !== null): ?>
        <div class="schedule-widget__upcoming" id="scheduleWidgetUpcoming" data-tournament-id="<?= (int) ($tournamentId ?? 0) ?>">
            <?php include __DIR__ . '/match/_upcomingMatches.php'; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($liveMode && $upcomingMatches !== null): ?>
<script>
(function () {
    // Écran de suivi live laissé ouvert sans interaction (bord de terrain) : sans ça, la
    // liste des prochains matchs reste figée sur la vague déjà jouée tant que personne ne
    // recharge la page, même après la saisie d'un score depuis une autre page. Ne touche
    // jamais au minuteur (élément voisin, pas concerné par ce remplacement).
    function init() {
        var container = document.getElementById('scheduleWidgetUpcoming');
        var tournamentId = container ? container.dataset.tournamentId : null;
        if (!container || !tournamentId) {
            return;
        }

        // Fait basculer le minuteur de qualification à phase finale tout seul, comme les
        // prochains matchs ci-dessus, quand le tournoi a changé d'étape entre deux
        // rafraîchissements — mais jamais pendant un match déjà chronométré (on ne veut pas
        // couper le décompte en cours d'un arbitre) ni vers la grande finale (toujours
        // choisie à la main, un seul match, pas de signal automatique fiable pour elle).
        function maybeSwitchTimerStage(timerPhase) {
            if (timerPhase !== 'qualification' && timerPhase !== 'phase_finale') {
                return;
            }
            var target = document.querySelector('.js-timer-mode[data-stage="' + timerPhase + '"]');
            var active = document.querySelector('.js-timer-mode.active');
            var toggleBtn = document.getElementById('qualTimerToggle');
            var isRunning = !!toggleBtn && toggleBtn.textContent.indexOf('⏸') !== -1;
            if (!target || target.disabled || isRunning || target === active) {
                return;
            }
            target.click();
        }

        function refresh() {
            fetch('index.php?controller=match&action=liveUpcoming&tournamentId=' + encodeURIComponent(tournamentId), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (data) {
                    if (data !== null) {
                        container.innerHTML = data.html;
                        maybeSwitchTimerStage(data.timerPhase);
                    }
                })
                .catch(function () {}); // une erreur réseau ponctuelle ne doit rien casser : le prochain tick réessaiera
        }

        setInterval(refresh, 20000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
<?php endif; ?>
