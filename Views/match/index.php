<?php
$title = 'Matchs';

$qualificationMatches = [];
$finalPhaseMatches = [];
foreach ($matches as $match) {
    if ($match->phase === 'qualification') {
        $qualificationMatches[] = $match;
    } else {
        $finalPhaseMatches[] = $match;
    }
}

// Étape par défaut du minuteur : cette page affiche à la fois les matchs de qualification
// ET (une fois le bracket généré) ceux de phase finale, contrairement à pool/index.php ou
// match/bracket.php qui n'affichent qu'une seule étape. Le minuteur doit donc suivre
// l'avancée réelle du tournoi plutôt que de rester figé sur la qualification : dès que les
// qualifications sont terminées et qu'il y a des matchs de phase finale à jouer, on bascule
// dessus (sinon un admin qui chronomètre en phase finale reste coincé sur l'ancienne durée,
// souvent bien plus courte, des matchs de poule).
$qualificationsDone = $qualificationMatches !== [] && !array_filter(
    $qualificationMatches,
    static fn ($m): bool => !in_array($m->status, ['finished', 'cancelled'], true)
);
$timerPhase = ($finalPhaseMatches !== [] && $qualificationsDone) ? 'phase_finale' : 'qualification';

// Tableau de matchs partagé avec match/bracket.php (liste sous l'arbre) : voir _matchTable.php.
$renderMatchTable = static function (array $rows): void {
    include dirname(__DIR__) . '/match/_matchTable.php';
};
?>

<div class="page-heading">
    <div>
        <a class="back-link" href="index.php?controller=home&action=index&id=<?= (int) $tournamentId ?>">&larr; Retour au tournoi</a>
        <p class="eyebrow">Compétition</p>
        <h2>Planning des matchs</h2>
    </div>
    <a class="btn btn-outline-primary btn-sm" href="index.php?controller=match&action=bracket&tournamentId=<?= (int) $tournamentId ?>">Voir l'arbre de la phase finale</a>
</div>

<?php if ($bracketGenerated): ?>
    <div class="alert alert-success">La phase finale (upper bracket / lower bracket / grande finale) a été générée à partir du classement de qualification.</div>
<?php endif; ?>

<?php if (isset($_GET['courtsAssigned'])): ?>
    <?php $n = (int) $_GET['courtsAssigned']; ?>
    <?php if ($n > 0): ?>
        <div class="alert alert-success"><?= $n ?> match(s) ont reçu un terrain qui leur manquait.</div>
    <?php else: ?>
        <div class="alert alert-info">Aucun match sans terrain à corriger (ou aucun terrain actif configuré).</div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($missingCourtsCount > 0): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?= $missingCourtsCount ?> match(s) n'ont jamais reçu de terrain (souvent saisis avant que les horaires/terrains du tournoi ne soient finalisés).</span>
        <form method="post" action="index.php?controller=match&action=assignMissingCourts&tournamentId=<?= (int) $tournamentId ?>">
            <?= \tournoi_enoa\Core\Csrf::field() ?>
            <button type="submit" class="btn btn-navy btn-sm" onclick="return confirm('Attribuer un terrain aux matchs qui n\'en ont pas (sans toucher à ceux déjà planifiés) ?');">
                Attribuer les terrains manquants
            </button>
        </form>
    </div>
<?php endif; ?>

<?php if (!$matches): ?>

    <section class="empty">
        <h3>Aucun match</h3>
        <p class="mb-0">Le planning n'a pas encore été généré pour ce tournoi.</p>
    </section>

<?php else: ?>

    <?php include dirname(__DIR__) . '/_scheduleWidget.php'; ?>

    <p class="text-muted small mt-4 mb-2">Cliquez sur un match pour en modifier le score.</p>

    <h3 class="mb-3">Phase de qualification</h3>
    <?php $renderMatchTable($qualificationMatches); ?>

    <?php if (!$finalPhaseMatches): ?>
        <div class="d-flex justify-content-end mb-4">
            <form method="post" action="index.php?controller=match&action=generateFinalPhase&tournamentId=<?= (int) $tournamentId ?>">
                <?= \tournoi_enoa\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-navy" onclick="return confirm('Générer le tableau de la phase finale (upper bracket / lower bracket / grande finale) à partir du classement de qualification ?');">
                    Générer la phase finale
                </button>
            </form>
        </div>
    <?php endif; ?>

    <h3 class="mb-3">Phase finale</h3>
    <?php $renderMatchTable($finalPhaseMatches); ?>

    <?php include dirname(__DIR__) . '/match/_scoreModal.php'; ?>

<?php endif; ?>
