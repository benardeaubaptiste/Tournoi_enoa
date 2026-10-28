<?php

/**
 * Point d'entrée de la batterie de tests fonctionnels PHP.
 *
 *   php tests/run.php
 *
 * Le test du minuteur (JS réellement exécuté, pas réimplémenté) est séparé :
 *   node tests/timer.test.js
 *
 * SchedulingIntegrationTest.php a besoin d'une vraie base MySQL "tournoi_enoa" accessible
 * avec les identifiants DB_* du fichier .env (voir .env.example) ; il crée son propre tournoi de
 * test (titre préfixé TEST-FUNC-) et le supprime dans tous les cas (bloc finally), sans
 * toucher aux tournois existants.
 */

require __DIR__ . '/bootstrap.php';

require __DIR__ . '/ScheduleCalculatorTest.php';
require __DIR__ . '/RoundRobinTest.php';
require __DIR__ . '/BracketAlgorithmTest.php';
require __DIR__ . '/BracketPreliminaryRoundTest.php';
require __DIR__ . '/CourtBalanceTest.php';
require __DIR__ . '/SchedulingIntegrationTest.php';
require __DIR__ . '/FullTournament16Test.php';
require __DIR__ . '/DemoEchecTest.php';

exit(\tournoi_enoa\Tests\MiniTest::summary());
