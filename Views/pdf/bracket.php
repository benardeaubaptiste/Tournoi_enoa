<?php
/** @var ?\tournoi_enoa\Entities\Tournament $tournament */
/** @var \tournoi_enoa\Entities\MatchGame[] $bracketMatches */
/** @var array $finalRanking */
$titre = $tournament ? htmlspecialchars($tournament->titre) : 'Tournoi';
$genere = date('d/m/Y H:i');
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Arbre de la phase finale - <?= $titre ?></title>
<?php include __DIR__ . '/_style.php'; ?>
</head>
<body>
    <h1>Arbre de la phase finale</h1>
    <p class="subtitle"><?= $titre ?> — généré le <?= $genere ?></p>
    <?php include __DIR__ . '/_bracket_body.php'; ?>
</body>
</html>
