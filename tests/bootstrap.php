<?php

/**
 * Bootstrap minimal des tests : recharge l'autoloader de l'appli (pas de composer
 * autoload PSR-4 pour le code applicatif, juste vendor/) et fixe un fuseau horaire
 * stable pour que les calculs d'heures (strtotime, DateTimeImmutable) soient reproductibles
 * quelle que soit la machine qui exécute les tests.
 */

date_default_timezone_set('Europe/Paris');

require __DIR__ . '/../autoloader.php';
\tournoi_enoa\Autoloader::register();
\tournoi_enoa\Core\Env::load(); // identifiants BDD des tests d'intégration (.env)

require __DIR__ . '/MiniTest.php';
