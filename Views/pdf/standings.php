<?php
/** @var ?\tournoi_enoa\Entities\Tournament $tournament */
/** @var \tournoi_enoa\Entities\Pool[] $pools */
/** @var array<int,array> $standingsByPool */
/** @var array $globalStandings */
/** @var array $finalRanking */
$titre = $tournament ? htmlspecialchars($tournament->titre) : 'Tournoi';
$genere = date('d/m/Y H:i');
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Classements - <?= $titre ?></title>
<?php include __DIR__ . '/_style.php'; ?>
</head>
<body>
    <h1>Classements du tournoi</h1>
    <p class="subtitle"><?= $titre ?> — généré le <?= $genere ?></p>
    <?php include __DIR__ . '/_standings_body.php'; ?>
</body>
</html>
