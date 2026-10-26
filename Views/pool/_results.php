<?php
/**
 * Bloc "Résultats de la phase de qualification" : résultats des matchs par poule tout en
 * haut (ce qu'on consulte/modifie le plus souvent pendant le tournoi), puis classements
 * par poule et classement général plus bas — chaque poule garde la même couleur entre les
 * deux sections pour s'y retrouver. Inclus tout en haut de pool/index.php (avant le
 * tableau de composition des poules) et rafraîchi seul après une saisie de score (voir
 * _scoreModal.php, patchMode='refresh') sans toucher à ce tableau, qui peut être en cours
 * d'édition côté client. Auto-suffisant (défini ses propres $statusBadges/$fmtPoints)
 * puisqu'il peut être rendu seul via renderPartial().
 */
$statusBadges = [
    'pending'   => 'bg-secondary',
    'scheduled' => 'bg-info text-dark',
    'live'      => 'bg-warning text-dark',
    'finished'  => 'bg-success',
    'cancelled' => 'bg-danger',
];

// Les points peuvent être décimaux (réglage "points par victoire/défaite" du tournoi,
// pas de step="0.5") : n'affiche une décimale que si elle est significative.
$fmtPoints = static function ($points): string {
    $points = (float) $points;
    return floor($points) == $points ? (string) (int) $points : number_format($points, 1, ',', '');
};

// Code couleur par poule (numero 1 à 4, cf. PoolModel::MAX_POOLS), repris à l'identique
// entre la section résultats et la section classements pour les associer visuellement.
$poolClass = static fn ($numero): string => 'pool-color-' . (((int) $numero - 1) % 4 + 1);
?>
<?php if ($pools): ?>

    <hr class="my-4">

    <div id="qualResultsSection">

        <h3 class="mt-4 mb-3">Résultats des matchs de qualification</h3>
        <p class="text-muted small mb-2">Cliquez sur un match pour en modifier le score.</p>

        <div class="row row-cols-1 row-cols-lg-2 g-3 mb-4">
            <?php foreach ($pools as $pool): ?>
                <?php $poolMatches = $matchesByPool[$pool->id] ?? []; ?>
                <div class="col">
                    <div class="table-wrap <?= $poolClass($pool->numero) ?>">
                        <div class="px-3 pt-3">
                            <h4 class="h6 mb-0"><span class="pool-dot <?= $poolClass($pool->numero) ?>"></span>Poule <?= (int) $pool->numero ?> — <?= htmlspecialchars($pool->nom) ?></h4>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Affiche</th>
                                        <th>Terrain</th>
                                        <th>Score</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$poolMatches): ?>
                                        <tr><td colspan="5" class="text-muted">Aucun match programmé.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($poolMatches as $match): ?>
                                            <tr class="match-row"
                                                data-match-id="<?= (int) $match->id ?>"
                                                data-match-number="<?= (int) $match->match_number ?>"
                                                data-team-a-id="<?= (int) $match->team_a_id ?>"
                                                data-team-b-id="<?= (int) $match->team_b_id ?>"
                                                data-team-a-name="<?= htmlspecialchars($match->team_a_name ?? 'Équipe A') ?>"
                                                data-team-b-name="<?= htmlspecialchars($match->team_b_name ?? 'Équipe B') ?>"
                                                data-score-a="<?= (int) $match->score_team_a ?>"
                                                data-score-b="<?= (int) $match->score_team_b ?>"
                                                data-status="<?= htmlspecialchars($match->status) ?>">
                                                <td><?= (int) $match->match_number ?></td>
                                                <td><?= htmlspecialchars($match->team_a_name ?? 'À définir') ?> <small class="text-muted">vs</small> <?= htmlspecialchars($match->team_b_name ?? 'À définir') ?></td>
                                                <td><?= htmlspecialchars($match->court_name ?? '-') ?></td>
                                                <td data-role="score-cell"><strong><?= (int) $match->score_team_a ?> - <?= (int) $match->score_team_b ?></strong></td>
                                                <td data-role="status-cell"><span class="badge <?= $statusBadges[$match->status] ?? 'bg-secondary' ?>"><?= htmlspecialchars($match->status) ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <h3 class="mt-4 mb-3">Classements de qualification</h3>

        <div class="row row-cols-1 row-cols-lg-2 g-3 mb-2">
            <?php foreach ($pools as $pool): ?>
                <div class="col">
                    <div class="table-wrap <?= $poolClass($pool->numero) ?>">
                        <div class="px-3 pt-3">
                            <h4 class="h6 mb-0"><span class="pool-dot <?= $poolClass($pool->numero) ?>"></span>Poule <?= (int) $pool->numero ?> — <?= htmlspecialchars($pool->nom) ?></h4>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th><th>Équipe</th><th>J</th><th>V</th><th>D</th><th>Sets +/-</th><th>Diff</th><th>Pts</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($standingsByPool[$pool->id] ?? [] as $row): ?>
                                        <tr>
                                            <td><?= (int) $row['position'] ?></td>
                                            <td><?= htmlspecialchars($row['nom']) ?></td>
                                            <td><?= (int) $row['joues'] ?></td>
                                            <td><?= (int) $row['victoires'] ?></td>
                                            <td><?= (int) $row['defaites'] ?></td>
                                            <td><?= (int) $row['sets_pour'] ?> / <?= (int) $row['sets_contre'] ?></td>
                                            <td><?= $row['diff_sets'] > 0 ? '+' : '' ?><?= (int) $row['diff_sets'] ?></td>
                                            <td><strong><?= $fmtPoints($row['points']) ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <h3 class="mt-4 mb-3">Classement général — toutes poules</h3>

        <div class="table-wrap mb-4">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th><th>Équipe</th><th>J</th><th>V</th><th>D</th><th>Sets +/-</th><th>Diff</th><th>Pts</th><th>Répartition indicative</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$globalStandings): ?>
                            <tr><td colspan="9" class="text-muted">Aucun match terminé pour l'instant.</td></tr>
                        <?php else: ?>
                            <?php $upperCount = (int) ceil(count($globalStandings) / 2); ?>
                            <?php foreach ($globalStandings as $i => $row): ?>
                                <tr>
                                    <td><?= (int) $row['position'] ?></td>
                                    <td><?= htmlspecialchars($row['nom']) ?></td>
                                    <td><?= (int) $row['joues'] ?></td>
                                    <td><?= (int) $row['victoires'] ?></td>
                                    <td><?= (int) $row['defaites'] ?></td>
                                    <td><?= (int) $row['sets_pour'] ?> / <?= (int) $row['sets_contre'] ?></td>
                                    <td><?= $row['diff_sets'] > 0 ? '+' : '' ?><?= (int) $row['diff_sets'] ?></td>
                                    <td><strong><?= $fmtPoints($row['points']) ?></strong></td>
                                    <td><span class="badge <?= $i < $upperCount ? 'bg-primary' : 'bg-secondary' ?>"><?= $i < $upperCount ? 'Upper bracket' : 'Lower bracket' ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="text-muted small">La répartition upper/lower ci-dessus est celle qu'utilisera le bouton ci-dessous : moitié haute du classement général → upper bracket, moitié basse → lower bracket, les deux vainqueurs se retrouvant en grande finale.</p>

        <?php if ($bracketError === 'not_enough_teams'): ?>
            <div class="alert alert-warning">Il faut au moins 2 équipes qualifiées (placées dans une poule) pour générer la phase finale.</div>
        <?php elseif ($bracketError === 'already_played'): ?>
            <div class="alert alert-warning">La phase finale a déjà des résultats enregistrés : impossible de la régénérer par-dessus sans les perdre.</div>
        <?php endif; ?>

        <?php if ($pendingQualCount > 0): ?>
            <div class="alert alert-info"><?= $pendingQualCount ?> match(s) de qualification ne sont pas encore terminés — le classement ci-dessus peut encore évoluer.</div>
        <?php endif; ?>

        <?php if ($globalStandings): ?>
            <form method="post" action="index.php?controller=match&action=generateFinalPhase&tournamentId=<?= (int) $tournamentId ?>" class="d-flex justify-content-end gap-2 mb-4">
                <?= \tournoi_enoa\Core\Csrf::field() ?>
                <a class="btn btn-outline-primary" href="index.php?controller=match&action=bracket&tournamentId=<?= (int) $tournamentId ?>">Voir l'arbre de la phase finale</a>
                <button type="submit" class="btn btn-navy" onclick="return confirm('Générer le tableau de la phase finale (upper bracket / lower bracket / grande finale) à partir du classement ci-dessus ?');">
                    Générer la phase finale
                </button>
            </form>
        <?php endif; ?>

        <?php
        $backTarget = 'pool';
        $patchMode = 'refresh';
        $refreshUrl = 'index.php?controller=pool&action=index&tournamentId=' . (int) $tournamentId;
        $refreshTarget = '#qualResultsSection';
        ?>
        <?php include dirname(__DIR__) . '/match/_scoreModal.php'; ?>

    </div>

<?php endif; ?>
