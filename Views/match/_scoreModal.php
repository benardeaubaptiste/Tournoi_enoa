<?php
/**
 * Fenêtre modale de saisie du score final d'un match, partagée par match/index.php,
 * pool/index.php (via pool/_results.php) et match/bracket.php : cliquer sur un match
 * (class="match-row", avec ses data-*) l'ouvre pré-remplie, sans quitter la page. On part
 * du principe que le score n'est saisi qu'une fois le match terminé (pas de suivi
 * "en cours") : la saisie envoie directement status=finished.
 *
 * Envoi asynchrone (fetch) : aucune navigation/redirection à la validation.
 * - $patchMode 'row' (par défaut, match/index.php) : seule la ligne du match éditée est
 *   mise à jour dans le tableau.
 * - $patchMode 'refresh' (pool/_results.php, match/bracket.php) : une saisie peut changer
 *   plus que la seule carte éditée (classements recalculés, équipe qualifiée pour le tour
 *   suivant...). On récupère alors en fond le HTML à jour de $refreshUrl et on remplace le
 *   contenu de $refreshTarget (sélecteur CSS), en conservant la position de défilement.
 * Dans tous les cas, si plusieurs matchs se terminent en même temps, l'admin peut
 * enchaîner les saisies sans être renvoyé en haut de page. En cas d'échec du fetch
 * (JS/réseau), le formulaire se soumet normalement en secours (redirection classique).
 */
$backTarget = $backTarget ?? 'match';
$patchMode = $patchMode ?? 'row';
$refreshUrl = $refreshUrl ?? null;
$refreshTarget = $refreshTarget ?? '.app-panel';
?>
<div class="modal fade" id="scoreModal" tabindex="-1" aria-labelledby="scoreModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" id="scoreModalForm">
                <?= \tournoi_enoa\Core\Csrf::field() ?>
                <input type="hidden" name="back" value="<?= htmlspecialchars($backTarget) ?>">
                <input type="hidden" name="status" value="finished">
                <div class="modal-header">
                    <h5 class="modal-title" id="scoreModalLabel">Match #<span id="scoreModalNumber"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div id="scoreModalError" class="alert alert-danger d-none" role="alert"></div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label" id="scoreModalTeamALabel" for="scoreModalScoreA">Équipe A</label>
                            <input class="form-control" type="number" name="score_team_a" id="scoreModalScoreA" min="0" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" id="scoreModalTeamBLabel" for="scoreModalScoreB">Équipe B</label>
                            <input class="form-control" type="number" name="score_team_b" id="scoreModalScoreB" min="0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-enoa" id="scoreModalSubmit">Enregistrer le score</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="scoreToast" class="position-fixed top-0 end-0 p-3" style="z-index:1080">
    <div class="toast align-items-center text-bg-success border-0" role="status" aria-live="polite" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="scoreToastBody">Score enregistré.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fermer"></button>
        </div>
    </div>
</div>

<script>
(function () {
    var patchMode = <?= json_encode($patchMode) ?>;
    var tournamentId = <?= (int) $tournamentId ?>;
    var refreshUrl = <?= json_encode($refreshUrl) ?>;
    var refreshTarget = <?= json_encode($refreshTarget) ?>;

    function init() {
        var modalEl = document.getElementById('scoreModal');
        if (!modalEl) {
            return;
        }
        var modal = new bootstrap.Modal(modalEl);
        var toast = new bootstrap.Toast(document.getElementById('scoreToast').querySelector('.toast'), { delay: 3000 });
        var form = document.getElementById('scoreModalForm');
        var errorBox = document.getElementById('scoreModalError');
        var submitBtn = document.getElementById('scoreModalSubmit');

        var statusBadgeClasses = {
            pending: 'bg-secondary',
            scheduled: 'bg-info text-dark',
            live: 'bg-warning text-dark',
            finished: 'bg-success',
            cancelled: 'bg-danger',
        };

        var currentRow = null;

        document.querySelectorAll('.match-row').forEach(function (row) {
            row.style.cursor = 'pointer';
            row.addEventListener('click', function (event) {
                if (event.target.closest('a, button')) {
                    return;
                }

                currentRow = row;
                var d = row.dataset;
                form.action = 'index.php?controller=match&action=score&id=' + d.matchId + '&tournamentId=' + tournamentId;
                errorBox.classList.add('d-none');
                document.getElementById('scoreModalNumber').textContent = d.matchNumber;
                document.getElementById('scoreModalTeamALabel').textContent = d.teamAName;
                document.getElementById('scoreModalTeamBLabel').textContent = d.teamBName;
                document.getElementById('scoreModalScoreA').value = d.scoreA;
                document.getElementById('scoreModalScoreB').value = d.scoreB;

                modal.show();
            });
        });

        function patchRow(match) {
            var row = currentRow;
            row.dataset.scoreA = match.score_team_a;
            row.dataset.scoreB = match.score_team_b;
            row.dataset.status = match.status;

            var scoreCell = row.querySelector('[data-role="score-cell"]');
            if (scoreCell) {
                var scoreText = match.score_team_a + ' - ' + match.score_team_b;
                var strongEl = scoreCell.querySelector('strong');
                if (strongEl) {
                    strongEl.textContent = scoreText;
                } else {
                    scoreCell.textContent = scoreText;
                }
            }
            var statusBadge = row.querySelector('[data-role="status-cell"] .badge');
            if (statusBadge) {
                statusBadge.className = 'badge ' + (statusBadgeClasses[match.status] || 'bg-secondary');
                statusBadge.textContent = match.status;
            }

            modal.hide();
            document.getElementById('scoreToastBody').textContent = 'Score enregistré : ' + match.score_team_a + ' - ' + match.score_team_b + '.';
            toast.show();
        }

        function executeScripts(container) {
            container.querySelectorAll('script').forEach(function (oldScript) {
                var newScript = document.createElement('script');
                Array.prototype.forEach.call(oldScript.attributes, function (attr) {
                    newScript.setAttribute(attr.name, attr.value);
                });
                newScript.textContent = oldScript.textContent;
                oldScript.parentNode.replaceChild(newScript, oldScript);
            });
        }

        function refreshSection() {
            if (!refreshUrl) {
                return;
            }
            var scrollY = window.scrollY;
            fetch(refreshUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (response) { return response.text(); })
                .then(function (html) {
                    var el = document.querySelector(refreshTarget);
                    if (!el) {
                        return;
                    }
                    el.innerHTML = html;
                    executeScripts(el); // les scripts injectés via innerHTML ne s'exécutent pas tout seuls
                    window.scrollTo(0, scrollY);
                });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (!currentRow) {
                return;
            }

            submitBtn.disabled = true;
            errorBox.classList.add('d-none');

            fetch(form.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    submitBtn.disabled = false;

                    if (!result.ok || !result.data.success) {
                        errorBox.textContent = (result.data && result.data.message) || "Une erreur est survenue.";
                        errorBox.classList.remove('d-none');
                        return;
                    }

                    if (patchMode === 'refresh') {
                        modalEl.addEventListener('hidden.bs.modal', function onHidden() {
                            modalEl.removeEventListener('hidden.bs.modal', onHidden);
                            refreshSection();
                        });
                        modal.hide();
                    } else {
                        patchRow(result.data.match);
                    }
                })
                .catch(function () {
                    // Pas de réseau / fetch indisponible : on retombe sur une soumission
                    // classique (rechargement de page avec redirection), pour ne jamais bloquer
                    // la saisie. form.submit() ne redéclenche pas l'évènement "submit" (donc pas
                    // de boucle avec ce gestionnaire).
                    submitBtn.disabled = false;
                    form.submit();
                });
        });
    }

    // Ce script est affiché avant le <script> de Bootstrap (chargé tout en bas de
    // base.php) au premier chargement de la page : on attend alors DOMContentLoaded.
    // Mais après un rafraîchissement partiel (refreshSection ci-dessus), ce bloc est
    // réinjecté et ré-exécuté alors que DOMContentLoaded est déjà passé depuis longtemps
    // et ne se redéclenchera jamais : dans ce cas on initialise immédiatement.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
