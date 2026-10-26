<?php

/**
 * Vérifie qu'aucune équipe ne peut jamais être qualifiée pour la suite sans avoir joué
 * (exempt/bye) : quand l'effectif d'un bracket n'est pas une puissance de 2, les moins bien
 * classées doivent disputer un vrai tour préliminaire, jamais une case vide (voir
 * BracketModel::generateSingleElim). Testé sur une vraie base (tournoi jetable, nettoyé dans
 * tous les cas) car generateFinalPhase() écrit directement en base — voir aussi
 * SchedulingIntegrationTest.php pour la même vérification dans un scénario de bout en bout.
 */

use tournoi_enoa\Core\DbConnect;
use tournoi_enoa\Models\BracketModel;
use tournoi_enoa\Models\TeamModel;
use tournoi_enoa\Models\TournamentModel;
use tournoi_enoa\Tests\MiniTest;

/** @return array{matches: object[], teamIds: int[]} */
function enoa_generate_bracket_for_counts(int $upperCount, int $lowerCount): array
{
    $pdo = DbConnect::createPdo();

    $tournamentModel = new TournamentModel();
    $tournamentId = $tournamentModel->create([
        'numero' => $tournamentModel->nextNumero(),
        'titre' => 'TEST-FUNC-' . uniqid(),
        'date_tournoi' => date('Y-m-d'),
        'date_limite_inscriptions' => null,
        'date_limite_paiement' => null,
        'statut' => 'draft',
        'created_by' => null,
    ]);

    try {
        $teamModel = new TeamModel();
        $total = $upperCount + $lowerCount;
        for ($i = 1; $i <= $total; $i++) {
            $teamModel->create([
                'tournament_id' => $tournamentId,
                'numero_inscription' => $i,
                'nom' => 'Equipe ' . $i,
                'club' => null,
                'nombre_joueurs' => 6,
                'prix_par_joueur' => 10,
                'montant_a_payer' => 60,
                'statut_paiement' => 'impaye',
                'montant_paye' => 0,
            ]);
        }
        $teamIds = array_map(static fn ($t) => (int) $t->id, $teamModel->byTournament($tournamentId));

        $upperIds = array_slice($teamIds, 0, $upperCount);
        $lowerIds = array_slice($teamIds, $upperCount, $lowerCount);
        (new BracketModel())->generateFinalPhase($tournamentId, $upperIds, $lowerIds);

        $query = $pdo->prepare("SELECT * FROM matches WHERE tournament_id = :t ORDER BY phase, round_number, match_number");
        $query->execute(['t' => $tournamentId]);

        return ['matches' => $query->fetchAll(), 'teamIds' => $teamIds];
    } finally {
        $tournamentModel->delete($tournamentId);
    }
}

function enoa_assert_no_exempt(array $matches, string $label): void
{
    foreach ($matches as $m) {
        $hasA = $m->team_a_id !== null;
        $hasB = $m->team_b_id !== null;
        $isExempt = $m->status === 'finished' && ($hasA !== $hasB);
        MiniTest::assertFalse($isExempt, "{$label} : match #{$m->match_number} ({$m->phase}, round {$m->round_number}) qualifie une équipe sans qu'elle ait joué");
    }
}

MiniTest::suite('Phase finale — plus aucun exempt, même avec un effectif qui n\'est pas une puissance de 2');

foreach ([
    'upper=6 lower=6 (le scénario historique à 2 exempts par bracket)' => [6, 6],
    'upper=3 lower=5 (effectifs impairs, tailles différentes)' => [3, 5],
    'upper=7 lower=7 (juste sous une puissance de 2)' => [7, 7],
    'upper=8 lower=8 (déjà une puissance de 2 : doit rester inchangé, aucun tour préliminaire)' => [8, 8],
    'upper=2 lower=1 (cas limite : lower a une seule équipe, qualifiée sans bracket)' => [2, 1],
] as $label => [$upperCount, $lowerCount]) {
    MiniTest::test($label, function () use ($upperCount, $lowerCount, $label) {
        $result = enoa_generate_bracket_for_counts($upperCount, $lowerCount);
        enoa_assert_no_exempt($result['matches'], $label);

        foreach (['upper' => $upperCount, 'lower' => $lowerCount] as $phase => $count) {
            if ($count < 2) {
                continue; // 1 équipe : qualifiée directement pour la grande finale, aucun match à générer.
            }
            $phaseMatches = array_filter($result['matches'], static fn ($m) => $m->phase === $phase);
            MiniTest::assertSame($count - 1, count($phaseMatches), "{$label} : le bracket {$phase} ({$count} équipes) doit avoir exactement {$count}-1 matchs, pas plus (aucune ligne d'exempt)");

            // Tout match de round_number=1 doit être un vrai match (2 équipes connues dès le
            // départ) : c'est soit le tableau principal (déjà une puissance de 2, rien à
            // préqualifier), soit le tour préliminaire — jamais une case vide.
            foreach ($phaseMatches as $m) {
                if ((int) $m->round_number === 1) {
                    MiniTest::assertTrue($m->team_a_id !== null && $m->team_b_id !== null, "{$label} : round 1 du bracket {$phase}, match #{$m->match_number} doit opposer deux vraies équipes");
                }
            }
        }
    });
}

MiniTest::test('upper=6 : le tour préliminaire porte bien le nom "Tour préliminaire" et se joue avant le reste', function () {
    $result = enoa_generate_bracket_for_counts(6, 0);
    $upperMatches = array_values(array_filter($result['matches'], static fn ($m) => $m->phase === 'upper'));

    $preliminary = array_values(array_filter($upperMatches, static fn ($m) => str_starts_with($m->round_name, 'Tour préliminaire')));
    MiniTest::assertSame(2, count($preliminary), '6 équipes -> 2 matchs de tour préliminaire (4 des 6 équipes, les moins bien classées)');
    foreach ($preliminary as $m) {
        MiniTest::assertSame(1, (int) $m->round_number, 'le tour préliminaire doit être le round 1 (avant le tableau principal)');
    }

    $mainRounds = array_map(static fn ($m) => (int) $m->round_number, array_filter($upperMatches, static fn ($m) => !str_starts_with($m->round_name, 'Tour préliminaire')));
    MiniTest::assertSame(2, min($mainRounds), 'le tableau principal doit commencer au round 2, juste après le tour préliminaire');
});
