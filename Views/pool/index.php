<?php
$title = 'Poules';

$teamOptionsHtml = '<option value="">— Équipe —</option>';
foreach ($teams as $team) {
    $teamOptionsHtml .= sprintf('<option value="%d">%s</option>', (int) $team->id, htmlspecialchars($team->nom));
}
?>

<div class="page-heading">
    <div>
        <a class="back-link" href="index.php?controller=home&action=index&id=<?= (int) $tournamentId ?>">&larr; Retour au tournoi</a>
        <p class="eyebrow">Phase de qualification</p>
        <h2>Tableau des poules</h2>
    </div>
    <?php if ($teams): ?>
        <form method="post" action="index.php?controller=pool&action=autoGenerate&tournamentId=<?= (int) $tournamentId ?>">
            <?= \tournoi_enoa\Core\Csrf::field() ?>
            <button type="submit" class="btn btn-navy" onclick="return confirm('<?= $hasQualMatches ? 'Recomposer' : 'Composer' ?> automatiquement les poules d\'après le nombre de poules et le mode de répartition réglés pour ce tournoi, puis générer directement les matchs de qualification ? <?= $hasQualMatches ? 'Le tableau actuel sera remplacé.' : '' ?>');">
                🪄 Générer poules + matchs automatiquement
            </button>
        </form>
    <?php endif; ?>
</div>

<?php if ($saved): ?>
    <div class="alert alert-success">Le tableau des poules a été enregistré.</div>
<?php endif; ?>

<?php if ($qualError === 'not_enough_teams'): ?>
    <div class="alert alert-warning">Il faut au moins 2 équipes (hors forfaits) pour générer automatiquement les poules.</div>
<?php endif; ?>

<?php if (!$teams): ?>

    <section class="empty">
        <h3>Aucune équipe inscrite</h3>
        <p class="mb-0">Inscrivez des équipes avant de composer les poules de qualification.</p>
    </section>

<?php else: ?>

    <h3 class="mb-3">Composition des poules</h3>

    <div class="qual-board">
        <span class="qual-board__corner">Phase de<br>qualification</span>

        <form method="post" id="poolBoardForm">
            <?= \tournoi_enoa\Core\Csrf::field() ?>
            <input type="hidden" name="deleted_pool_ids" id="deletedPoolIds" value="">

            <div class="qual-board__grid" id="poolGrid">
                <?php foreach ($pools as $index => $pool): ?>
                    <?php $poolTeams = $assignments[$pool->id] ?? []; ?>
                    <div class="qual-pool" data-pool-id="<?= (int) $pool->id ?>" data-idx="<?= (int) $index ?>">
                        <button type="button" class="qual-pool__remove" title="Supprimer la poule" data-remove-pool>&times;</button>
                        <div class="qual-pool__header">
                            <span class="qual-pool__badge">Poule <?= (int) $pool->numero ?></span>
                            <input type="hidden" name="pools[<?= (int) $index ?>][id]" value="<?= (int) $pool->id ?>">
                            <input type="text" class="qual-pool__name" name="pools[<?= (int) $index ?>][nom]" value="<?= htmlspecialchars($pool->nom) ?>" maxlength="50" placeholder="Nom de la poule">
                        </div>

                        <div class="qual-pool__slots">
                            <?php foreach ($poolTeams as $slot): ?>
                                <div class="qual-slot">
                                    <span class="qual-slot__line"></span>
                                    <select class="qual-slot__select" name="pools[<?= (int) $index ?>][teams][]">
                                        <option value="">— Équipe —</option>
                                        <?php foreach ($teams as $team): ?>
                                            <option value="<?= (int) $team->id ?>" <?= ((int) $team->id === $slot['team_id']) ? 'selected' : '' ?>><?= htmlspecialchars($team->nom) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="qual-slot__remove" title="Retirer" data-remove-slot>&times;</button>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="button" class="qual-pool__add" data-add-slot>+ Ajouter une équipe</button>

                        <label class="qual-pool__footer qual-pool__valid">
                            <input type="checkbox" name="pools[<?= (int) $index ?>][validee]" value="1" <?= $pool->statut === 'validee' ? 'checked' : '' ?>>
                            <span class="qual-pool__medal">🏅</span>
                            <span>Poule validée</span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="qual-board__add-pool" id="addPoolBtn" <?= count($pools) >= 4 ? 'style="display:none"' : '' ?>>+ Ajouter une poule</button>

            <div class="qual-board__actions">
                <a class="btn btn-outline-primary" href="index.php?controller=match&action=index&tournamentId=<?= (int) $tournamentId ?>">Voir la liste des matchs</a>
                <button type="submit" class="btn btn-enoa">Enregistrer le tableau</button>
            </div>
        </form>
    </div>

    <?php $timerPhase = 'qualification'; ?>
    <?php include dirname(__DIR__) . '/_scheduleWidget.php'; ?>

    <?php if ($qualGenerated): ?>
        <div class="alert alert-success">Les matchs de qualification ont été générés et planifiés.</div>
    <?php elseif ($qualError === 'already_played'): ?>
        <div class="alert alert-warning">Des matchs de qualification ont déjà des résultats enregistrés : impossible de les régénérer par-dessus sans les perdre.</div>
    <?php endif; ?>

    <?php if (count($pools) > 0): ?>
        <div class="d-flex justify-content-end mb-4">
            <form method="post" action="index.php?controller=pool&action=generateMatches&tournamentId=<?= (int) $tournamentId ?>">
                <?= \tournoi_enoa\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-navy" onclick="return confirm('<?= $hasQualMatches ? 'Régénérer' : 'Générer' ?> le tour complet de chaque poule et replanifier les horaires ?');">
                    <?= $hasQualMatches ? 'Régénérer les matchs de qualification' : 'Générer les matchs de qualification' ?>
                </button>
            </form>
        </div>
    <?php endif; ?>

    <script>
    (function () {
        const teamOptionsHtml = <?= json_encode($teamOptionsHtml) ?>;
        const maxPools = <?= (int) \tournoi_enoa\Models\PoolModel::MAX_POOLS ?>;
        const grid = document.getElementById('poolGrid');
        const deletedInput = document.getElementById('deletedPoolIds');
        const addPoolBtn = document.getElementById('addPoolBtn');
        const deletedIds = [];
        let nextIdx = <?= count($pools) ?>;

        function poolCount() {
            return grid.querySelectorAll('.qual-pool').length;
        }

        function toggleAddPoolButton() {
            if (addPoolBtn) {
                addPoolBtn.style.display = poolCount() >= maxPools ? 'none' : '';
            }
        }

        function refreshDuplicates() {
            const selected = Array.from(grid.querySelectorAll('.qual-slot__select'))
                .map(function (s) { return s.value; })
                .filter(function (v) { return v !== ''; });

            grid.querySelectorAll('.qual-slot__select').forEach(function (select) {
                Array.from(select.options).forEach(function (opt) {
                    if (opt.value === '') return;
                    opt.disabled = selected.indexOf(opt.value) !== -1 && select.value !== opt.value;
                });
            });
        }

        function bindSlot(slot) {
            slot.querySelector('.qual-slot__remove').addEventListener('click', function () {
                slot.remove();
                refreshDuplicates();
            });
            slot.querySelector('.qual-slot__select').addEventListener('change', refreshDuplicates);
        }

        function addSlot(poolCard) {
            const idx = poolCard.dataset.idx;
            const slots = poolCard.querySelector('.qual-pool__slots');
            const div = document.createElement('div');
            div.className = 'qual-slot';
            div.innerHTML = '<span class="qual-slot__line"></span>' +
                '<select class="qual-slot__select" name="pools[' + idx + '][teams][]">' + teamOptionsHtml + '</select>' +
                '<button type="button" class="qual-slot__remove" title="Retirer" data-remove-slot>&times;</button>';
            slots.appendChild(div);
            bindSlot(div);
            refreshDuplicates();
        }

        function bindPool(poolCard) {
            poolCard.querySelectorAll('.qual-slot').forEach(bindSlot);
            poolCard.querySelector('[data-add-slot]').addEventListener('click', function () {
                addSlot(poolCard);
            });
            poolCard.querySelector('[data-remove-pool]').addEventListener('click', function () {
                const id = poolCard.dataset.poolId;
                if (id && id !== '0') {
                    deletedIds.push(id);
                    deletedInput.value = deletedIds.join(',');
                }
                poolCard.remove();
                toggleAddPoolButton();
                refreshDuplicates();
            });
        }

        grid.querySelectorAll('.qual-pool').forEach(bindPool);

        if (addPoolBtn) {
            addPoolBtn.addEventListener('click', function () {
                if (poolCount() >= maxPools) return;
                const idx = nextIdx++;
                const card = document.createElement('div');
                card.className = 'qual-pool';
                card.dataset.poolId = '0';
                card.dataset.idx = idx;
                card.innerHTML =
                    '<button type="button" class="qual-pool__remove" title="Supprimer la poule" data-remove-pool>&times;</button>' +
                    '<div class="qual-pool__header">' +
                        '<span class="qual-pool__badge">Nouvelle poule</span>' +
                        '<input type="hidden" name="pools[' + idx + '][id]" value="0">' +
                        '<input type="text" class="qual-pool__name" name="pools[' + idx + '][nom]" maxlength="50" placeholder="Nom de la poule">' +
                    '</div>' +
                    '<div class="qual-pool__slots"></div>' +
                    '<button type="button" class="qual-pool__add" data-add-slot>+ Ajouter une équipe</button>' +
                    '<label class="qual-pool__footer qual-pool__valid">' +
                        '<input type="checkbox" name="pools[' + idx + '][validee]" value="1">' +
                        '<span class="qual-pool__medal">🏅</span>' +
                        '<span>Poule validée</span>' +
                    '</label>';
                grid.appendChild(card);
                bindPool(card);
                addSlot(card);
                addSlot(card);
                toggleAddPoolButton();
            });
        }

        toggleAddPoolButton();
        refreshDuplicates();
    })();
    </script>

    <?php include dirname(__DIR__) . '/pool/_results.php'; ?>

<?php endif; ?>
