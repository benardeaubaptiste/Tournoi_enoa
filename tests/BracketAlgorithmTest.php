<?php

/**
 * Tests fonctionnels des petites fonctions pures de BracketModel (ordre de placement du
 * tableau à élimination directe, nom des rounds) — via Reflection sur une instance créée
 * sans constructeur, donc sans connexion BDD. La génération complète (avec exempts,
 * enchaînement des rounds, écriture en base) est couverte par
 * tests/SchedulingIntegrationTest.php.
 */

use tournoi_enoa\Models\BracketModel;
use tournoi_enoa\Tests\MiniTest;

function enoa_bracket_instance(): BracketModel
{
    $ref = new ReflectionClass(BracketModel::class);
    return $ref->newInstanceWithoutConstructor();
}

function enoa_seed_order(int $size): array
{
    $ref = new ReflectionClass(BracketModel::class);
    $method = $ref->getMethod('seedOrder');
    $method->setAccessible(true);
    return $method->invoke(enoa_bracket_instance(), $size);
}

function enoa_round_name(int $matchesInRound, string $phase): string
{
    $ref = new ReflectionClass(BracketModel::class);
    $method = $ref->getMethod('roundName');
    $method->setAccessible(true);
    return $method->invoke(enoa_bracket_instance(), $matchesInRound, $phase);
}

MiniTest::suite('Bracket — ordre de placement (seedOrder)');

MiniTest::test('taille 2 : le tête de série 1 affronte le 2', function () {
    MiniTest::assertSame([1, 2], enoa_seed_order(2));
});

MiniTest::test('taille 4 : ordre standard 1-4-2-3 (le 1 n\'affronte le 4 qu\'en cas de parcours sans accroc)', function () {
    MiniTest::assertSame([1, 4, 2, 3], enoa_seed_order(4));
});

MiniTest::test('taille 8 : chaque seed apparaît une seule fois, de 1 à 8', function () {
    $order = enoa_seed_order(8);
    sort($order);
    MiniTest::assertSame(range(1, 8), $order);
});

MiniTest::suite('Bracket — nom des rounds affiché au front');

MiniTest::test('dernier match d\'un bracket -> "Finale (Upper/Lower bracket)" (distingue la finale de CE bracket de la grande finale)', function () {
    MiniTest::assertSame('Finale (Upper bracket)', enoa_round_name(1, 'upper'));
    MiniTest::assertSame('Finale (Lower bracket)', enoa_round_name(1, 'lower'));
});

MiniTest::test('avant-dernier round -> "Demi-finale", avec le bon suffixe de phase', function () {
    MiniTest::assertSame('Demi-finale (Upper bracket)', enoa_round_name(2, 'upper'));
    MiniTest::assertSame('Demi-finale (Lower bracket)', enoa_round_name(2, 'lower'));
});

MiniTest::test('rounds plus profonds : quart, huitième, puis fraction générique', function () {
    MiniTest::assertSame('Quart de finale (Upper bracket)', enoa_round_name(4, 'upper'));
    MiniTest::assertSame('Huitième de finale (Upper bracket)', enoa_round_name(8, 'upper'));
    MiniTest::assertSame('1/32 de finale (Upper bracket)', enoa_round_name(16, 'upper'));
});
