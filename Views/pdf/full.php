<?php
/**
 * Bilan complet du tournoi en un seul PDF : équipes, planning, classements, arbre de la
 * phase finale, chaque section démarrant sur une nouvelle page (.pdf-section, voir
 * _style.php). Réutilise les mêmes fragments que les documents individuels.
 * @var ?\tournoi_enoa\Entities\Tournament $tournament
 */
$titre = $tournament ? htmlspecialchars($tournament->titre) : 'Tournoi';
$genere = date('d/m/Y H:i');
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Bilan du tournoi - <?= $titre ?></title>
<?php include __DIR__ . '/_style.php'; ?>
</head>
<body>
    <h1>Bilan du tournoi</h1>
    <p class="subtitle"><?= $titre ?> — généré le <?= $genere ?></p>

    <div class="pdf-section"><?php include __DIR__ . '/_teams_body.php'; ?></div>
    <div class="pdf-section"><?php include __DIR__ . '/_planning_body.php'; ?></div>
    <div class="pdf-section"><?php include __DIR__ . '/_standings_body.php'; ?></div>
    <div class="pdf-section"><?php include __DIR__ . '/_bracket_body.php'; ?></div>
</body>
</html>
