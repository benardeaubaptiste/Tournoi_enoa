<?php $title = 'Tableau de bord - Tournoi ENOA'; ?>

<div class="page-heading">
    <div>
        <p class="eyebrow">Tournoi ENOA</p>
        <h2>Tableau de bord </h2>
    </div>
    <a class="btn btn-enoa" href="index.php?controller=tournament&action=add">+ Nouveau tournoi</a>
</div>

<?php if (!$tournaments): ?>

    <section class="empty">
        <h3>Aucun tournoi</h3>
        <p class="mb-0">Créez votre premier tournoi pour commencer.</p>
    </section>

<?php else: ?>

    <form class="selector row g-2 align-items-center mb-4" method="get">
        <input type="hidden" name="controller" value="home">
        <input type="hidden" name="action" value="index">
        <div class="col-auto">
            <label for="id" class="col-form-label fw-bold">Tournoi actif</label>
        </div>
        <div class="col-auto">
            <select id="id" name="id" class="form-select" onchange="this.form.submit()">
                <?php foreach ($tournaments as $tournament): ?>
                    <option value="<?= (int) $tournament->id ?>" <?= ((int) $tournament->id === (int) $dashboard->id) ? 'selected' : '' ?>><?= htmlspecialchars($tournament->titre) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if ($dashboard): ?>

        <?php if ($nextStep): ?>
            <a class="btn btn-navy mb-4" href="<?= htmlspecialchars($nextStep['href']) ?>"><?= htmlspecialchars($nextStep['label']) ?> &rarr;</a>
        <?php endif; ?>

        <div class="row row-cols-2 row-cols-md-4 g-3 stats mb-4">
            <div class="col">
                <article>
                    <span>Équipes</span>
                    <strong><?= (int) $dashboard->nombre_equipes ?></strong>
                </article>
            </div>
            <div class="col">
                <article>
                    <span>Montant total</span>
                    <strong><?= number_format((float) $dashboard->montant_total, 2, ',', ' ') ?> €</strong>
                </article>
            </div>
            <div class="col">
                <article>
                    <span>Encaissé</span>
                    <strong><?= number_format((float) $dashboard->montant_encaisse, 2, ',', ' ') ?> €</strong>
                </article>
            </div>
            <div class="col">
                <article>
                    <span>À percevoir</span>
                    <strong><?= number_format((float) $dashboard->montant_restant, 2, ',', ' ') ?> €</strong>
                </article>
            </div>
        </div>

        <div class="row row-cols-1 row-cols-md-3 g-3 quick-links">
            <div class="col">
                <a href="index.php?controller=team&action=index&tournamentId=<?= (int) $dashboard->id ?>">
                    <b>Équipes</b>
                    <span>Inscriptions et joueurs →</span>
                </a>
            </div>
            <div class="col">
                <a href="index.php?controller=pool&action=index&tournamentId=<?= (int) $dashboard->id ?>">
                    <b>Poules</b>
                    <span>Répartition des équipes →</span>
                </a>
            </div>
            <div class="col">
                <a href="index.php?controller=match&action=index&tournamentId=<?= (int) $dashboard->id ?>">
                    <b>Matchs</b>
                    <span>Planning et scores →</span>
                </a>
            </div>
            <div class="col">
                <a href="index.php?controller=tournament&action=settings&id=<?= (int) $dashboard->id ?>">
                    <b>Paramètres</b>
                    <span>Configuration du tournoi →</span>
                </a>
            </div>
        </div>

    <?php endif; ?>

<?php endif; ?>
