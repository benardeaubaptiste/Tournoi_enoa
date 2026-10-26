<?php

use tournoi_enoa\Core\BracketTree;

$title = 'Phase finale';
?>

<div class="page-heading">
    <div>
        <a class="back-link" href="index.php?controller=pool&action=index&tournamentId=<?= (int) $tournamentId ?>">&larr; Retour aux poules</a>
        <p class="eyebrow">Phase finale</p>
        <h2>Arbre du tournoi</h2>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-primary btn-sm" href="index.php?controller=match&action=index&tournamentId=<?= (int) $tournamentId ?>">Voir la liste des matchs</a>
        <a class="btn btn-enoa btn-sm" href="index.php?controller=export&action=full&tournamentId=<?= (int) $tournamentId ?>">📄 Télécharger le bilan complet (PDF)</a>
    </div>
</div>

<?php if ($bracketGenerated): ?>
    <div class="alert alert-success">La phase finale a été générée à partir du classement de qualification.</div>
<?php endif; ?>

<?php if (!$tree['grand_final']): ?>

    <section class="empty">
        <h3>Phase finale pas encore générée</h3>
        <p class="mb-0">Générez le tableau upper bracket / lower bracket depuis la page <a href="index.php?controller=pool&action=index&tournamentId=<?= (int) $tournamentId ?>">Poules</a>, une fois la qualification terminée.</p>
    </section>

<?php else: ?>

    <?php $timerPhase = 'phase_finale'; ?>
    <?php include dirname(__DIR__) . '/_scheduleWidget.php'; ?>

    <div class="bracket-wrap mt-4">
        <?= BracketTree::renderFull($tree) ?>
    </div>

    <p class="text-muted small mt-3">🏆 équipe qualifiée pour la suite · <em>En attente</em> = résultat du match précédent pas encore connu · ⓘ = détails du match.</p>

    <h3 class="mt-4 mb-3">Liste des matchs de la phase finale</h3>
    <p class="text-muted small mb-3">Cliquez sur un match pour en saisir ou modifier le score.</p>
    <?php $rows = $finalPhaseMatches; ?>
    <?php include dirname(__DIR__) . '/match/_matchTable.php'; ?>

    <?php if ($finalRanking): ?>
        <h3 class="mt-4 mb-3">Classement final</h3>
        <div class="table-wrap mb-4">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr><th>#</th><th>Équipe</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($finalRanking as $row): ?>
                            <tr>
                                <td>
                                    <?php $medailles = [1 => '🥇', 2 => '🥈', 3 => '🥉']; ?>
                                    <?= $medailles[(int) $row->position] ?? (int) $row->position ?>
                                </td>
                                <td><?= htmlspecialchars($row->nom) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php
    $backTarget = 'bracket';
    $patchMode = 'refresh';
    $refreshUrl = 'index.php?controller=match&action=bracket&tournamentId=' . (int) $tournamentId;
    $refreshTarget = '.app-panel';
    ?>
    <?php include dirname(__DIR__) . '/match/_scoreModal.php'; ?>

    <script>
        (function () {
            function initTooltips() {
                document.querySelectorAll('.bracket-wrap [data-bs-toggle="tooltip"]').forEach(function (el) {
                    new bootstrap.Tooltip(el);
                });
            }

            // Centre la carte "Grande finale" sur le vrai espace vertical entre upper et
            // lower bracket (pas sur la moitié de leur hauteur cumulée : un centrage naïf sur
            // le total ne coïncide avec le milieu de l'écart que si les deux brackets ont
            // exactement la même hauteur, ce qui n'est pas garanti dès que nombre_equipes_upper
            // et nombre_equipes_lower diffèrent). Sans ça, la grande finale peut déborder sur
            // le titre du bracket suivant (ou du bracket le plus haut des deux).
            function positionGrandFinal() {
                var finalCol = document.querySelector('.bracket-final-col');
                var finalSection = finalCol ? finalCol.querySelector('.bracket-phase--final') : null;
                var phases = document.querySelectorAll('.bracket-main > .bracket-phase');
                if (!finalCol || !finalSection) {
                    return;
                }
                finalSection.style.marginTop = '';
                if (phases.length < 2) {
                    return; // un seul bracket généré : rien entre lequel centrer, le centrage CSS par défaut suffit.
                }

                var colTop = finalCol.getBoundingClientRect().top;
                var gapMiddle = (phases[0].getBoundingClientRect().bottom + phases[1].getBoundingClientRect().top) / 2;
                var desiredTop = gapMiddle - finalSection.getBoundingClientRect().height / 2;
                finalSection.style.marginTop = Math.max(0, desiredTop - colTop) + 'px';
            }

            function init() {
                initTooltips();
                positionGrandFinal();
            }

            // Bootstrap n'est chargé qu'en bas de base.php, après ce bloc, au premier
            // chargement de la page : on attend alors DOMContentLoaded. Mais après un
            // rafraîchissement partiel de l'arbre (voir _scoreModal.php), ce bloc est
            // réinjecté et ré-exécuté alors que DOMContentLoaded est déjà passé et ne se
            // redéclenchera jamais : dans ce cas on initialise immédiatement.
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }

            // Les cartes peuvent changer de hauteur (retour à la ligne d'un nom d'équipe
            // long) si la fenêtre est redimensionnée : on repositionne la grande finale en
            // conséquence, sans réinitialiser les tooltips (inutile, coûteux avec beaucoup de matchs).
            var resizeTimer = null;
            window.addEventListener('resize', function () {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(positionGrandFinal, 150);
            });
        })();
    </script>

<?php endif; ?>
