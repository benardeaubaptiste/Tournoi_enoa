<?php
/** @var ?\tournoi_enoa\Entities\Tournament $tournament */
$titre = $tournament ? htmlspecialchars($tournament->titre) : 'Tournoi';
$genere = date('d/m/Y H:i');
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Équipes - <?= $titre ?></title>
<?php include __DIR__ . '/_style.php'; ?>
</head>
<body>
    <h1>Liste des équipes</h1>
    <p class="subtitle"><?= $titre ?> — généré le <?= $genere ?></p>
    <?php include __DIR__ . '/_teams_body.php'; ?>
</body>
</html>
