<?php

/**
 * Tests fonctionnels de bout en bout sur une VRAIE base (celle de WAMP, tournoi_enoa) :
 * on crée un tournoi de test dédié (titre préfixé TEST-FUNC-, jamais un tournoi existant),
 * on fait tourner exactement le pipeline de génération utilisé par l'appli
 * (TournamentController::computeSchedule -> QualificationModel::generate ->
 * BracketModel::generateFinalPhase), puis on vérifie le planning réellement écrit en base.
 * Le tournoi de test est supprimé (DELETE FROM tournaments, cascade sur teams/pools/matches/
 * courts/tournament_settings) que les assertions passent ou non, y compris si le script est
 * interrompu par une exception (bloc finally).
 *
 * Pourquoi contre une vraie base plutôt que des doublures : QualificationModel et
 * BracketModel n'acceptent aucune injection de connexion (DbConnect ouvre une vraie
 * connexion MySQL dans son constructeur), et c'est là que vit toute la logique
 * "un match par round, terrains tournants, heure de fin de qualif = pivot phase finale"
 * qu'on veut vérifier pour de vrai.
 */

use tournoi_enoa\Controllers\TournamentController;
use tournoi_enoa\Core\DbConnect;
use tournoi_enoa\Models\BracketModel;
use tournoi_enoa\Models\CourtModel;
use tournoi_enoa\Models\PoolModel;
use tournoi_enoa\Models\QualificationModel;
use tournoi_enoa\Models\TeamModel;
use tournoi_enoa\Models\TournamentModel;
use tournoi_enoa\Tests\MiniTest;

function enoa_compute_schedule(array $data): array
{
    $controller = new TournamentController();
    $ref = new ReflectionMethod(TournamentController::class, 'computeSchedule');
    $ref->setAccessible(true);
    return $ref->invoke($controller, $data);
}

/** Deux intervalles [debut, fin) se chevauchent-ils ? */
function enoa_overlaps(string $startA, string $endA, string $startB, string $endB): bool
{
    return strtotime($startA) < strtotime($endB) && strtotime($startB) < strtotime($endA);
}

/**
 * Plus longue série de rounds consécutifs (round_number croissants sans trou) où l'équipe
 * apparaît, à partir des lignes `matches` réellement écrites en base (team_a_id/team_b_id).
 */
function enoa_longest_consecutive_rounds(array $matches, int $teamId): int
{
    $roundsPlayed = [];
    foreach ($matches as $m) {
        if ((int) $m->team_a_id === $teamId || (int) $m->team_b_id === $teamId) {
            $roundsPlayed[(int) $m->round_number] = true;
        }
    }
    if (!$roundsPlayed) {
        return 0;
    }
    $rounds = array_keys($roundsPlayed);
    sort($rounds);

    $longest = 1;
    $current = 1;
    for ($i = 1; $i < count($rounds); $i++) {
        if ($rounds[$i] === $rounds[$i - 1] + 1) {
            $current++;
        } else {
            $current = 1;
        }
        $longest = max($longest, $current);
    }
    return $longest;
}

/**
 * Exécute tout le scénario dans un callback pour garantir le nettoyage (finally) même si
 * une assertion lève une exception au milieu.
 */
function enoa_run_scheduling_integration_scenario(): void
{
    $pdo = DbConnect::createPdo();

    $tournamentModel = new TournamentModel();
    $today = date('Y-m-d');
    $tournamentId = $tournamentModel->create([
        'numero' => $tournamentModel->nextNumero(),
        'titre' => 'TEST-FUNC-' . uniqid(),
        'date_tournoi' => $today,
        'date_limite_inscriptions' => null,
        'date_limite_paiement' => null,
        'statut' => 'draft',
        'created_by' => null,
    ]);

    try {
        // --- Réglages : tournoi 09:00 -> 18:00, 12 équipes / 2 poules / 2 terrains, upper+lower = 6+6.
        $tournamentModel->createSettings($tournamentId, [
            'prix_par_joueur' => 10,
            'heure_debut_tournoi' => '09:00',
            'heure_fin_tournoi' => '18:00',
        ]);

        $data = [
            'heure_debut_tournoi' => '09:00',
            'heure_fin_tournoi' => '18:00',
            'nombre_max_equipes' => 12,
            'nombre_poules' => 2,
            'mode_repartition' => 'ordre_inscription',
            'nombre_terrains' => 2,
            'temps_entre_matchs_qualification' => 5,
            'temps_entre_matchs_phase_finale' => 5,
            'duree_grande_finale' => 20,
            'prix_par_joueur' => 10,
            'max_matchs_consecutifs_qualifications' => 2,
            'max_matchs_consecutifs_phase_finale' => 1,
            'points_victoire' => 3,
            'points_defaite' => 1,
            'nombre_equipes_upper' => 6,
            'nombre_equipes_lower' => 6,
        ];

        $schedule = enoa_compute_schedule($data);
        MiniTest::assertTrue($schedule['qualification']['faisable'], 'scénario de test censé être faisable (qualification) : ajuster les paramètres du scénario si ça casse');
        MiniTest::assertTrue($schedule['phase_finale']['faisable'], 'scénario de test censé être faisable (phase finale) : ajuster les paramètres du scénario si ça casse');

        $data['heure_debut_qualifications'] = $schedule['heure_debut_qualifications'];
        $data['heure_fin_qualifications'] = $schedule['heure_fin_qualifications'];
        $data['heure_debut_phase_finale'] = $schedule['heure_debut_phase_finale'];
        $data['heure_fin_phase_finale'] = $schedule['heure_fin_phase_finale'];
        $data['duree_match_qualification'] = $schedule['qualification']['duree_match'];
        $data['duree_match_phase_finale'] = $schedule['phase_finale']['fenetre_minutes'] > 0 ? $schedule['phase_finale']['duree_match'] : null;

        $tournamentModel->updateSettings($tournamentId, $data);
        (new CourtModel())->syncForTournament($tournamentId, 2);

        // --- 12 équipes.
        $teamModel = new TeamModel();
        for ($i = 1; $i <= 12; $i++) {
            $teamModel->create([
                'tournament_id' => $tournamentId,
                'numero_inscription' => $i,
                'nom' => 'Equipe Test ' . $i,
                'club' => null,
                'nombre_joueurs' => 6,
                'prix_par_joueur' => 10,
                'montant_a_payer' => 60,
                'statut_paiement' => 'impaye',
                'montant_paye' => 0,
            ]);
        }
        $teamIds = array_map(static fn ($t) => (int) $t->id, $teamModel->byTournament($tournamentId));
        MiniTest::assertSame(12, count($teamIds), '12 équipes créées');

        // --- 2 poules de 6 (comme PoolController::autoGenerate en mode "ordre_inscription").
        $poolModel = new PoolModel();
        $poolTeamIds = [
            $poolModel->create($tournamentId, 1, 'Poule A', 'validee') => array_slice($teamIds, 0, 6),
            $poolModel->create($tournamentId, 2, 'Poule B', 'validee') => array_slice($teamIds, 6, 6),
        ];
        foreach ($poolTeamIds as $poolId => $ids) {
            $poolModel->syncTeams($poolId, $ids);
        }

        // ================= QUALIFICATION =================
        (new QualificationModel())->generate($tournamentId, $poolTeamIds);

        $qualQuery = $pdo->prepare(
            "SELECT * FROM matches WHERE tournament_id = :t AND phase = 'qualification' ORDER BY scheduled_start, court_id"
        );
        $qualQuery->execute(['t' => $tournamentId]);
        $qualMatches = $qualQuery->fetchAll();

        MiniTest::test('qualification : 30 matchs générés (2 poules x C(6,2)=15)', function () use ($qualMatches) {
            MiniTest::assertSame(30, count($qualMatches));
        });

        MiniTest::test('qualification : tous les matchs ont un horaire et un terrain (réglages complets avant génération)', function () use ($qualMatches) {
            foreach ($qualMatches as $m) {
                MiniTest::assertTrue($m->scheduled_start !== null && $m->scheduled_end !== null && $m->court_id !== null, "match #{$m->match_number} sans horaire/terrain");
            }
        });

        MiniTest::test("qualification : aucun match ne commence avant l'heure de début du tournoi (09:00)", function () use ($qualMatches, $today) {
            $tournamentStart = strtotime($today . ' 09:00:00');
            foreach ($qualMatches as $m) {
                MiniTest::assertTrue(strtotime($m->scheduled_start) >= $tournamentStart, "match #{$m->match_number} démarre avant l'heure de début du tournoi");
            }
        });

        MiniTest::test("qualification : aucun match ne dépasse l'heure de fin de qualifications calculée (pivot phase finale)", function () use ($qualMatches, $data, $today) {
            $pivot = strtotime($today . ' ' . $data['heure_fin_qualifications']);
            foreach ($qualMatches as $m) {
                MiniTest::assertTrue(strtotime($m->scheduled_end) <= $pivot, "match #{$m->match_number} déborde sur la fenêtre de la phase finale");
            }
        });

        MiniTest::test('qualification : deux matchs sur le même terrain ne se chevauchent jamais', function () use ($qualMatches) {
            $byCourt = [];
            foreach ($qualMatches as $m) {
                $byCourt[$m->court_id][] = $m;
            }
            foreach ($byCourt as $courtId => $matchesOnCourt) {
                for ($i = 0; $i < count($matchesOnCourt); $i++) {
                    for ($j = $i + 1; $j < count($matchesOnCourt); $j++) {
                        $a = $matchesOnCourt[$i];
                        $b = $matchesOnCourt[$j];
                        MiniTest::assertFalse(
                            enoa_overlaps($a->scheduled_start, $a->scheduled_end, $b->scheduled_start, $b->scheduled_end),
                            "terrain {$courtId} : matchs #{$a->match_number} et #{$b->match_number} se chevauchent"
                        );
                    }
                }
            }
        });

        MiniTest::test("qualification : aucune équipe n'est double-réservée (deux matchs qui se chevauchent en même temps)", function () use ($qualMatches, $teamIds) {
            foreach ($teamIds as $teamId) {
                $ownMatches = array_values(array_filter($qualMatches, static fn ($m) => (int) $m->team_a_id === $teamId || (int) $m->team_b_id === $teamId));
                for ($i = 0; $i < count($ownMatches); $i++) {
                    for ($j = $i + 1; $j < count($ownMatches); $j++) {
                        $a = $ownMatches[$i];
                        $b = $ownMatches[$j];
                        MiniTest::assertFalse(
                            enoa_overlaps($a->scheduled_start, $a->scheduled_end, $b->scheduled_start, $b->scheduled_end),
                            "équipe {$teamId} : matchs #{$a->match_number} et #{$b->match_number} se chevauchent"
                        );
                    }
                }
            }
        });

        MiniTest::test(
            "ÉCART : max_matchs_consecutifs_qualifications=2 configuré, mais chaque équipe enchaîne bel et bien 5 rounds d'affilée sans repos",
            function () use ($qualMatches, $teamIds) {
                // 2 poules de 6 (effectif pair) : round-robin pur, 5 rounds, aucun bye -> chaque
                // équipe joue littéralement tous les rounds. Le réglage (par défaut 2) n'est lu
                // ni par QualificationModel::generate() ni par scheduleQualification() : rien ne
                // vient jamais insérer de round de repos, quelle que soit la valeur configurée.
                foreach ($teamIds as $teamId) {
                    $run = enoa_longest_consecutive_rounds($qualMatches, $teamId);
                    MiniTest::assertSame(5, $run, "équipe {$teamId} : série de rounds consécutifs");
                    MiniTest::assertTrue($run > 2, "équipe {$teamId} dépasse le max configuré (2) sans qu'aucune erreur/alerte ne soit levée");
                }
            }
        );

        // ================= PHASE FINALE =================
        $upperIds = array_slice($teamIds, 0, 6);
        $lowerIds = array_slice($teamIds, 6, 6);
        (new BracketModel())->generateFinalPhase($tournamentId, $upperIds, $lowerIds);

        $finalQuery = $pdo->prepare(
            "SELECT * FROM matches WHERE tournament_id = :t AND phase IN ('upper','lower','grand_final') ORDER BY scheduled_start, court_id"
        );
        $finalQuery->execute(['t' => $tournamentId]);
        $finalMatches = $finalQuery->fetchAll();

        MiniTest::test('phase finale : 6 équipes -> tour préliminaire (2 matchs) + tableau principal de 4 places (3 matchs) par bracket + grande finale = 11 lignes, aucun exempt', function () use ($finalMatches) {
            // 6 équipes par bracket : la plus grande puissance de 2 qui tient dedans est 4, pas
            // 8. Les 4 moins bien classées de chaque bracket jouent donc un vrai tour
            // préliminaire (2 matchs) pour prendre les 2 places restantes du tableau principal
            // (les 2 mieux classées y entrent directement) : 2 + 3 (2 rounds : demi-finale +
            // finale) = 5 lignes par bracket = count-1, jamais 7 (l'ancien comportement avec
            // 2 exempts par bracket). Total : 5 x2 (upper+lower) + 1 grande finale = 11 lignes.
            MiniTest::assertSame(11, count($finalMatches));

            foreach ($finalMatches as $m) {
                $isExempt = $m->status === 'finished' && (($m->team_a_id !== null) !== ($m->team_b_id !== null));
                MiniTest::assertFalse($isExempt, "match #{$m->match_number} qualifie une équipe sans adversaire (exempt) : c'est précisément ce qui ne doit plus jamais arriver");
            }
        });

        MiniTest::test("phase finale : les matchs réellement joués ont un horaire dans la fenêtre phase finale (pivot -> fin du tournoi)", function () use ($finalMatches, $data, $today) {
            $pivot = strtotime($today . ' ' . $data['heure_debut_phase_finale']);
            $tournamentEnd = strtotime($today . ' 18:00:00');
            foreach ($finalMatches as $m) {
                if ($m->scheduled_start === null) {
                    continue;
                }
                MiniTest::assertTrue(strtotime($m->scheduled_start) >= $pivot, "match #{$m->match_number} démarre avant le début de la phase finale");
                MiniTest::assertTrue(strtotime($m->scheduled_end) <= $tournamentEnd, "match #{$m->match_number} déborde après la fin du tournoi (18:00)");
            }
        });

        MiniTest::test('phase finale : la grande finale est planifiée après tous les autres matchs de bracket', function () use ($finalMatches) {
            $grandFinal = null;
            $lastBracketEnd = null;
            foreach ($finalMatches as $m) {
                if ($m->phase === 'grand_final') {
                    $grandFinal = $m;
                    continue;
                }
                if ($m->scheduled_end !== null) {
                    $lastBracketEnd = $lastBracketEnd === null ? $m->scheduled_end : max($lastBracketEnd, $m->scheduled_end);
                }
            }
            MiniTest::assertTrue($grandFinal !== null && $grandFinal->scheduled_start !== null, 'grande finale doit être planifiée');
            MiniTest::assertTrue(strtotime($grandFinal->scheduled_start) >= strtotime($lastBracketEnd), 'la grande finale doit démarrer après la fin du dernier match de bracket');
        });

        MiniTest::test('phase finale : deux matchs sur le même terrain ne se chevauchent jamais', function () use ($finalMatches) {
            $byCourt = [];
            foreach ($finalMatches as $m) {
                if ($m->court_id === null) {
                    continue;
                }
                $byCourt[$m->court_id][] = $m;
            }
            foreach ($byCourt as $courtId => $matchesOnCourt) {
                for ($i = 0; $i < count($matchesOnCourt); $i++) {
                    for ($j = $i + 1; $j < count($matchesOnCourt); $j++) {
                        $a = $matchesOnCourt[$i];
                        $b = $matchesOnCourt[$j];
                        MiniTest::assertFalse(
                            enoa_overlaps($a->scheduled_start, $a->scheduled_end, $b->scheduled_start, $b->scheduled_end),
                            "terrain {$courtId} : matchs #{$a->match_number} et #{$b->match_number} se chevauchent"
                        );
                    }
                }
            }
        });
    } finally {
        // Nettoyage garanti : cascade sur teams/pools/pool_teams/matches/courts/tournament_settings.
        $tournamentModel->delete($tournamentId);
    }
}

MiniTest::suite('Planning de bout en bout (vraie base tournoi_enoa) — fenêtre horaire, chevauchements, matchs consécutifs');

try {
    enoa_run_scheduling_integration_scenario();
} catch (\Throwable $e) {
    MiniTest::test('scénario d\'intégration (création tournoi de test + génération planning)', function () use ($e) {
        throw $e;
    });
}
