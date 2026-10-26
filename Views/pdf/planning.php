<?php
/** @var ?\tournoi_enoa\Entities\Tournament $tournament */
/** @var \tournoi_enoa\Entities\MatchGame[] $planningMatches */
$titre = $tournament ? htmlspecialchars($tournament->titre) : 'Tournoi';
$genere = date('d/m/Y H:i');
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Planning - <?= $titre ?></title>
<?php include __DIR__ . '/_style.php'; ?>
</head>
<body>
    <h1>Planning des matchs</h1>
    <p class="subtitle"><?= $titre ?> — généré le <?= $genere ?></p>
    <?php include __DIR__ . '/_planning_body.php'; ?>
</body>
</html>
