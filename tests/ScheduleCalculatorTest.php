<?php

/**
 * Tests fonctionnels de tournoi_enoa\Core\ScheduleCalculator : le calcul de la fenêtre
 * horaire du tournoi (heure de début/fin) et la répartition qualifications / phase finale.
 * Aucune base de données requise, cette classe est pure.
 */

use tournoi_enoa\Core\ScheduleCalculator;
use tournoi_enoa\Tests\MiniTest;

MiniTest::suite('ScheduleCalculator — fenêtre horaire (heure début/fin de tournoi)');

MiniTest::test('windowMinutes : plage normale dans la même journée (09:00 -> 18:00 = 9h)', function () {
    MiniTest::assertSame(540, ScheduleCalculator::windowMinutes('09:00', '18:00'));
});

MiniTest::test('windowMinutes : gère les secondes (09:00:00 -> 18:00:00)', function () {
    MiniTest::assertSame(540, ScheduleCalculator::windowMinutes('09:00:00', '18:00:00'));
});

MiniTest::test('windowMinutes : tournoi à cheval sur minuit (20:00 -> 00:00 = 4h), pas une erreur', function () {
    MiniTest::assertSame(240, ScheduleCalculator::windowMinutes('20:00', '00:00'));
});

MiniTest::test('windowMinutes : fin avant début dans la journée (20:00 -> 02:00 = 6h après minuit)', function () {
    MiniTest::assertSame(360, ScheduleCalculator::windowMinutes('20:00', '02:00'));
});

MiniTest::test('windowMinutes : heure de début manquante -> 0 (pas de crash)', function () {
    MiniTest::assertSame(0, ScheduleCalculator::windowMinutes(null, '18:00'));
});

MiniTest::test('windowMinutes : heure de fin manquante -> 0 (pas de crash)', function () {
    MiniTest::assertSame(0, ScheduleCalculator::windowMinutes('09:00', null));
});

MiniTest::test('windowMinutes : chaîne invalide -> 0 (pas de crash, pas de warning fatal)', function () {
    MiniTest::assertSame(0, ScheduleCalculator::windowMinutes('pas une heure', '18:00'));
});

MiniTest::test('windowMinutes : ATTENTION début == fin est interprété comme 24h, pas 0h', function () {
    // Documente un comportement piégeux : si l'utilisateur saisit la même heure de
    // début et de fin par erreur (au lieu de laisser vide), le calculateur ne le
    // détecte pas comme une fenêtre nulle mais comme "le tournoi dure jusqu'au
    // lendemain à la même heure" (1440 minutes). Un formulaire qui n'empêche pas
    // heure_debut_tournoi === heure_fin_tournoi laisserait donc planifier un
    // tournoi de 24h au lieu de rejeter la saisie.
    MiniTest::assertSame(1440, ScheduleCalculator::windowMinutes('09:00', '09:00'));
});

MiniTest::suite('ScheduleCalculator — durée de match calculée');

MiniTest::test('resolveMatchDuration : cas simple, tout tient sans reste', function () {
    // 10 matchs, 2 terrains -> 5 créneaux ; 100 min dispo, 5 min de battement entre créneaux.
    $r = ScheduleCalculator::resolveMatchDuration(120, 10, 2, 5);
    MiniTest::assertSame(5, $r['creneaux']);
    // (120 - 4*5) / 5 = 100/5 = 20
    MiniTest::assertSame(20, $r['duree_match']);
    MiniTest::assertTrue($r['faisable']);
});

MiniTest::test('resolveMatchDuration : fenêtre trop courte -> infaisable, jamais de durée négative', function () {
    $r = ScheduleCalculator::resolveMatchDuration(10, 10, 1, 5);
    MiniTest::assertFalse($r['faisable']);
    MiniTest::assertGreaterOrEqual(0, $r['duree_match']);
});

MiniTest::test('resolveMatchDuration : aucun match -> faisable par convention, rien à caser', function () {
    $r = ScheduleCalculator::resolveMatchDuration(0, 0, 3, 5);
    MiniTest::assertSame(0, $r['creneaux']);
    MiniTest::assertTrue($r['faisable']);
});

MiniTest::test('resolveMatchDuration : la réserve (ex. grande finale) réduit bien la fenêtre exploitable', function () {
    $sansReserve = ScheduleCalculator::resolveMatchDuration(100, 5, 1, 0, 0);
    $avecReserve = ScheduleCalculator::resolveMatchDuration(100, 5, 1, 0, 20);
    MiniTest::assertTrue($avecReserve['duree_match'] < $sansReserve['duree_match'], 'la réserve doit raccourcir chaque match');
});

MiniTest::test('resolveMatchDuration : 0 terrain est traité comme 1 terrain (pas de division par zéro)', function () {
    $r = ScheduleCalculator::resolveMatchDuration(100, 5, 0, 5);
    MiniTest::assertTrue($r['duree_match'] >= 0);
});

MiniTest::suite('ScheduleCalculator — répartition qualifications / phase finale');

MiniTest::test('splitWindow : répartition strictement proportionnelle au nombre de créneaux', function () {
    $r = ScheduleCalculator::splitWindow(300, 3, 1); // 3 créneaux qual, 1 créneau finale sur 4 -> 75%/25%
    MiniTest::assertSame(225, $r['qualification']);
    MiniTest::assertSame(75, $r['phase_finale']);
});

MiniTest::test('splitWindow : aucun créneau des deux côtés -> tout revient à la qualification', function () {
    $r = ScheduleCalculator::splitWindow(300, 0, 0);
    MiniTest::assertSame(300, $r['qualification']);
    MiniTest::assertSame(0, $r['phase_finale']);
});

MiniTest::suite('ScheduleCalculator — nombre de matchs (poules, bracket)');

MiniTest::test('qualificationMatchCount : 8 équipes / 2 poules égales (4+4) = 2 x C(4,2) = 12', function () {
    MiniTest::assertSame(12, ScheduleCalculator::qualificationMatchCount(8, 2));
});

MiniTest::test('qualificationMatchCount : effectif non multiple du nombre de poules (répartition au plus juste)', function () {
    // 10 équipes / 3 poules -> tailles 4/3/3 -> C(4,2)+C(3,2)+C(3,2) = 6+3+3 = 12
    MiniTest::assertSame(12, ScheduleCalculator::qualificationMatchCount(10, 3));
});

MiniTest::test('qualificationMatchCount : 0 poule ou 0/1 équipe -> 0 match, pas de crash', function () {
    MiniTest::assertSame(0, ScheduleCalculator::qualificationMatchCount(8, 0));
    MiniTest::assertSame(0, ScheduleCalculator::qualificationMatchCount(1, 2));
    MiniTest::assertSame(0, ScheduleCalculator::qualificationMatchCount(0, 2));
});

MiniTest::test('finalPhaseMatchCount : upper 8 + lower 8 -> 7 + 7 matchs de bracket, grande finale activée', function () {
    $r = ScheduleCalculator::finalPhaseMatchCount(8, 8);
    MiniTest::assertSame(14, $r['bracket']);
    MiniTest::assertTrue($r['grande_finale']);
});

MiniTest::test('finalPhaseMatchCount : une seule moitié peuplée -> pas de grande finale (personne en face)', function () {
    $r = ScheduleCalculator::finalPhaseMatchCount(8, 0);
    MiniTest::assertSame(7, $r['bracket']);
    MiniTest::assertFalse($r['grande_finale']);
});
