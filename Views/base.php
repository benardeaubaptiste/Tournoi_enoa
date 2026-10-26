<?php $currentAdmin = \tournoi_enoa\Core\Auth::user(); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Affichage dynamique de la variable $title -->
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-eOJMYsd53ii+scO/bJGFsiCZc+5NDVN2yr8+0RDqr0Ql0h+rP48ckxlpbzKgwra6" crossorigin="anonymous">

    <link rel="stylesheet" href="style.css">
    <script src="https://kit.fontawesome.com/cff33ecd93.js" crossorigin="anonymous"></script>
</head>


<body>
    <div class="container">
        <header class="site-header">
            <div class="header-image-frame">
                <img src="images/tournoi-volley-header.png" alt="Tournoi de Volley ENOA" class="header-image">
            </div>
        </header>

        <nav class="navbar navbar-expand-lg navbar-dark navbar-enoa">
            <div class="container-fluid">
                <a class="navbar-brand" href="index.php">🏐 Tournoi ENOA</a>
                <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown"
                aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNavDropdown">
                    <?php if ($currentAdmin): ?>
                    <ul class="navbar-nav ms-auto align-items-lg-center">
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="index.php?controller=home&action=index">Tableau de bord</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?controller=tournament&action=index">Tournois</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?controller=match&action=live">🔴 Suivi live</a>
                        </li>
                        <li class="nav-item account-chip">
                            <span>👤 <?= htmlspecialchars($currentAdmin->display_name) ?>
                                <?php if (($currentAdmin->role ?? null) === 'gestionnaire'): ?>
                                    <small class="text-white-50">(gestionnaire)</small>
                                <?php endif; ?>
                            </span>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-sm btn-outline-light" href="index.php?controller=auth&action=logout">Déconnexion</a>
                        </li>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </nav>

        <main>
            <div class="app-panel">
                <!-- Affichage dynamique de la variable $content -->
                <?= $content ?>
            </div>
        </main>

        <footer class="text-center">
            <p>Gestion de tournoi &copy; 2026</p>
        </footer>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js" 
    integrity="sha384-JEW9xMcG8R+pH31jmWH6WWP0WintQrMb4s7ZOdauHnUtxwoG2vI5DkLtS3qm9Ekf" crossorigin="anonymous"></script>
</body>

</html>