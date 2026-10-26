<?php

/**
 * Tests fonctionnels de l'algorithme de poule (méthode du cercle) utilisé par
 * QualificationModel::generate() — QualificationModel::circleRoundRobin() est privée et
 * pure (aucun accès BDD), on l'appelle donc via Reflection sur une instance construite
 * SANS passer par le constructeur (qui, lui, ouvre une vraie connexion MySQL) : ces tests
 * n'ont donc besoin d'aucune base de données.
 *
 * Le deuxième bloc documente un vrai écart fonctionnel : les réglages
 * `max_matchs_consecutifs_qualifications` / `max_matchs_consecutifs_phase_finale`
 * (visibles dans le formulaire des réglages du tournoi, par défaut 2 et 1) ne sont lus
 * nulle part dans QualificationModel ni BracketModel — voir tests/SchedulingIntegrationTest.php
 * pour la confirmation de bout en bout sur un vrai planning généré.
 */

use tournoi_enoa\Models\QualificationModel;
use tournoi_enoa\Tests\MiniTest;

function enoa_call_circle_round_robin(array $teamIds): array
{
    $ref = new ReflectionClass(QualificationModel::class);
    $instance = $ref->newInstanceWithoutConstructor();
    $method = $ref->getMethod('circleRoundRobin');
    $method->setAccessible(true);
    return $method->invoke($instance, $teamIds);
}

/** Pour chaque round, la liste des équipes qui jouent (les deux membres de chaque paire). */
function enoa_playing_teams_by_round(array $rounds): array
{
    return array_map(function (array $round) {
        $teams = [];
        foreach ($round as [$a, $b]) {
            $teams[] = $a;
            $teams[] = $b;
        }
        return $teams;
    }, $rounds);
}

/** Plus long nombre de rounds consécutifs où une équipe donnée a joué (aucun bye au milieu). */
function enoa_longest_consecutive_run(array $playingByRound, int $teamId): int
{
    $longest = 0;
    $current = 0;
    foreach ($playingByRound as $teams) {
        if (in_array($teamId, $teams, true)) {
            $current++;
            $longest = max($longest, $current);
        } else {
            $current = 0;
        }
    }
    return $longest;
}

MiniTest::suite('Round-robin (poule) — algorithme de la méthode du cercle');

MiniTest::test('4 équipes : 3 rounds, chaque paire possible jouée exactement une fois', function () {
    $rounds = enoa_call_circle_round_robin([1, 2, 3, 4]);
    MiniTest::assertSame(3, count($rounds), 'nombre de rounds pour 4 équipes');

    $pairsSeen = [];
    foreach ($rounds as $round) {
        MiniTest::assertSame(2, count($round), 'chaque round doit avoir 2 matchs pour 4 équipes');
        foreach ($round as [$a, $b]) {
            $key = min($a, $b) . '-' . max($a, $b);
            MiniTest::assertFalse(isset($pairsSeen[$key]), "la paire {$key} ne doit être jouée qu'une fois");
            $pairsSeen[$key] = true;
        }
    }
    MiniTest::assertSame(6, count($pairsSeen), 'C(4,2) = 6 rencontres au total');
});

MiniTest::test('effectif impair (5 équipes) : un bye par round, réparti équitablement (chaque équipe une seule fois)', function () {
    $teamIds = [10, 20, 30, 40, 50];
    $rounds = enoa_call_circle_round_robin($teamIds);
    MiniTest::assertSame(5, count($rounds), 'n équipes impaires -> n rounds (avec un fantôme)');

    $playingByRound = enoa_playing_teams_by_round($rounds);
    $byeCounts = array_fill_keys($teamIds, 0);
    foreach ($playingByRound as $teams) {
        MiniTest::assertSame(4, count($teams), 'un bye par round -> 2 matchs -> 4 joueurs actifs');
        foreach ($teamIds as $teamId) {
            if (!in_array($teamId, $teams, true)) {
                $byeCounts[$teamId]++;
            }
        }
    }
    foreach ($byeCounts as $teamId => $count) {
        MiniTest::assertSame(1, $count, "l'équipe {$teamId} doit avoir exactement 1 bye sur 5 rounds");
    }
});

MiniTest::test('toutes les équipes se rencontrent exactement une fois, quel que soit l\'effectif (6 équipes)', function () {
    $teamIds = [1, 2, 3, 4, 5, 6];
    $rounds = enoa_call_circle_round_robin($teamIds);

    $matchCounts = [];
    foreach ($teamIds as $t) {
        $matchCounts[$t] = 0;
    }
    $pairsSeen = [];
    foreach ($rounds as $round) {
        foreach ($round as [$a, $b]) {
            $matchCounts[$a]++;
            $matchCounts[$b]++;
            $key = min($a, $b) . '-' . max($a, $b);
            MiniTest::assertFalse(isset($pairsSeen[$key]), "la paire {$key} ne doit être jouée qu'une fois");
            $pairsSeen[$key] = true;
        }
    }
    foreach ($matchCounts as $teamId => $count) {
        MiniTest::assertSame(5, $count, "l'équipe {$teamId} doit jouer exactement n-1 = 5 matchs");
    }
});

MiniTest::test('moins de 2 équipes -> aucun round généré (pas de crash sur une poule vide/à 1 équipe)', function () {
    MiniTest::assertSame([], enoa_call_circle_round_robin([]));
    MiniTest::assertSame([], enoa_call_circle_round_robin([42]));
});

MiniTest::suite('Round-robin — écart fonctionnel : "matchs consécutifs max" (réglage non appliqué)');

MiniTest::test("dès 4 équipes en poule (effectif pair, aucun bye), une équipe joue TOUS les rounds d'affilée", function () {
    // Réglage par défaut max_matchs_consecutifs_qualifications = 2 (schema.sql). Avec un
    // effectif pair, le round-robin pur ne connaît pas la notion de repos : les 4 équipes
    // jouent les 3 rounds à la suite (round-robin classique, aucune équipe jamais au repos).
    $rounds = enoa_call_circle_round_robin([1, 2, 3, 4]);
    $playingByRound = enoa_playing_teams_by_round($rounds);

    foreach ([1, 2, 3, 4] as $teamId) {
        $run = enoa_longest_consecutive_run($playingByRound, $teamId);
        MiniTest::assertSame(3, $run, "l'équipe {$teamId} devrait jouer 3 rounds d'affilée avec l'algorithme actuel");
        MiniTest::assertTrue(
            $run > 2,
            "cette série ({$run}) dépasse déjà le max_matchs_consecutifs_qualifications par défaut (2) : "
            . "le réglage existe en base/formulaire mais n'est lu par aucun code de génération/planification."
        );
    }
});

MiniTest::test("poule à 8 équipes : une équipe peut enchaîner ses 7 matchs sans le moindre round de repos", function () {
    $teamIds = range(1, 8);
    $rounds = enoa_call_circle_round_robin($teamIds);
    $playingByRound = enoa_playing_teams_by_round($rounds);

    $worst = 0;
    foreach ($teamIds as $teamId) {
        $worst = max($worst, enoa_longest_consecutive_run($playingByRound, $teamId));
    }
    // Avec 8 équipes (nombre pair), il n'y a jamais de bye du tout : toutes les équipes
    // jouent tous les 7 rounds. La pire série possible est donc 7, très loin du réglage
    // par défaut (2) — confirmation que rien ne vient jamais forcer un round de repos.
    MiniTest::assertSame(7, $worst);
});
