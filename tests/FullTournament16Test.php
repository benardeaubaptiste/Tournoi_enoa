<?php

/**
 * Circuit complet d'un tournoi à 16 équipes (vraie base MySQL tournoi_enoa), de bout en
 * bout comme le ferait un admin : 4 poules de 4 (round-robin complet), tous les scores de
 * qualification saisis, classement général calculé et persisté, phase finale (upper/lower
 * 8+8, sans tour préliminaire puisque 8 est déjà une puissance de 2) jouée intégralement
 * jusqu'à la grande finale via la même propagation de vainqueur que MatchController::score()
 * (MatchModel::updateScore), classement final persisté. Le tournoi de test (titre préfixé
 * TEST-FUNC16-) est supprimé dans tous les cas (bloc finally).
 *
 * Convention "à la 'meilleure équipe gagne toujours'" : dans chaque poule, l'équipe la
 * mieux classée (index le plus bas dans le tableau de la poule) bat systématiquement les
 * autres, avec un écart de score jamais nul (jamais d'égalité) ; en phase finale, l'équipe
 * la mieux classée au général de qualification bat systématiquement l'autre. Le résultat
 * de tout le tournoi est donc entièrement déterministe et vérifiable à la main, ce qui
 * permet de contrôler que Standings, RankingModel et la propagation de vainqueur du bracket
 * produisent tous le bon résultat, pas seulement "ça ne plante pas".
 */

use tournoi_enoa\Controllers\MatchController;
use tournoi_enoa\Controllers\PoolController;
use tournoi_enoa\Core\DbConnect;
use tournoi_enoa\Core\Standings;
use tournoi_enoa\Models\BracketModel;
use tournoi_enoa\Models\CourtModel;
use tournoi_enoa\Models\MatchModel;
use tournoi_enoa\Models\PoolModel;
use tournoi_enoa\Models\QualificationModel;
use tournoi_enoa\Models\RankingModel;
use tournoi_enoa\Models\TeamModel;
use tournoi_enoa\Models\TournamentModel;
use tournoi_enoa\Tests\MiniTest;

function enoa16_refresh_qual(int $tournamentId): array
{
    $ref = new ReflectionMethod(PoolController::class, 'refreshQualificationRankings');
    $ref->setAccessible(true);
    return $ref->invoke(new PoolController(), $tournamentId);
}

function enoa16_refresh_final(int $tournamentId): void
{
    $ref = new ReflectionMethod(MatchController::class, 'refreshFinalRanking');
    $ref->setAccessible(true);
    $ref->invoke(new MatchController(), $tournamentId);
}

/** Même méthode que MatchController::generateFinalPhase() utilise réellement pour décider la taille de l'upper bracket. */
function enoa16_resolve_upper_count(int $tournamentId, int $totalRanked): int
{
    $ref = new ReflectionMethod(MatchController::class, 'resolveUpperCount');
    $ref->setAccessible(true);
    return $ref->invoke(new MatchController(), $tournamentId, $totalRanked);
}

function enoa16_overlaps(string $startA, string $endA, string $startB, string $endB): bool
{
    return strtotime($startA) < strtotime($endB) && strtotime($startB) < strtotime($endA);
}

function enoa16_run_full_tournament_scenario(): void
{
    $pdo = DbConnect::createPdo();

    $tournamentModel = new TournamentModel();
    $today = date('Y-m-d');
    $tournamentId = $tournamentModel->create([
        'numero' => $tournamentModel->nextNumero(),
        'titre' => 'TEST-FUNC16-' . uniqid(),
        'date_tournoi' => $today,
        'date_limite_inscriptions' => null,
        'date_limite_paiement' => null,
        'statut' => 'draft',
        'created_by' => null,
    ]);

    try {
        // --- Réglages : tournoi 08:00 -> 20:00, 16 équipes / 4 poules / 4 terrains, upper+lower = 8+8.
        $tournamentModel->createSettings($tournamentId, [
            'prix_par_joueur' => 10,
            'heure_debut_tournoi' => '08:00',
            'heure_fin_tournoi' => '20:00',
        ]);

        $data = [
            'heure_debut_tournoi' => '08:00',
            'heure_fin_tournoi' => '20:00',
            'nombre_max_equipes' => 16,
            'nombre_poules' => 4,
            'mode_repartition' => 'ordre_inscription',
            'nombre_terrains' => 4,
            'temps_entre_matchs_qualification' => 5,
            'temps_entre_matchs_phase_finale' => 5,
            'duree_grande_finale' => 20,
            'prix_par_joueur' => 10,
            'max_matchs_consecutifs_qualifications' => 2,
            'max_matchs_consecutifs_phase_finale' => 1,
            'points_victoire' => 3,
            'points_defaite' => 1,
            'nombre_equipes_upper' => 8,
            'nombre_equipes_lower' => 8,
        ];

        $schedule = enoa_compute_schedule($data);
        MiniTest::assertTrue($schedule['qualification']['faisable'], 'scénario 16 équipes censé être faisable (qualification)');
        MiniTest::assertTrue($schedule['phase_finale']['faisable'], 'scénario 16 équipes censé être faisable (phase finale)');

        $data['heure_debut_qualifications'] = $schedule['heure_debut_qualifications'];
        $data['heure_fin_qualifications'] = $schedule['heure_fin_qualifications'];
        $data['heure_debut_phase_finale'] = $schedule['heure_debut_phase_finale'];
        $data['heure_fin_phase_finale'] = $schedule['heure_fin_phase_finale'];
        $data['duree_match_qualification'] = $schedule['qualification']['duree_match'];
        $data['duree_match_phase_finale'] = $schedule['phase_finale']['fenetre_minutes'] > 0 ? $schedule['phase_finale']['duree_match'] : null;

        $tournamentModel->updateSettings($tournamentId, $data);
        (new CourtModel())->syncForTournament($tournamentId, 4);

        // --- 16 équipes.
        $teamModel = new TeamModel();
        for ($i = 1; $i <= 16; $i++) {
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
        MiniTest::assertSame(16, count($teamIds), '16 équipes créées');

        // --- 4 poules de 4.
        $poolModel = new PoolModel();
        $poolTeamIds = [];
        $poolNames = ['Poule A', 'Poule B', 'Poule C', 'Poule D'];
        for ($p = 0; $p < 4; $p++) {
            $poolId = $poolModel->create($tournamentId, $p + 1, $poolNames[$p], 'validee');
            $poolTeamIds[$poolId] = array_slice($teamIds, $p * 4, 4);
        }
        foreach ($poolTeamIds as $poolId => $ids) {
            $poolModel->syncTeams($poolId, $ids);
        }

        // "Meilleure équipe de la poule" = celle dont l'index dans le tableau de la poule est
        // le plus bas ; sert à décider systématiquement le vainqueur de chaque match de poule.
        $poolRankOf = [];
        foreach ($poolTeamIds as $poolId => $ids) {
            foreach ($ids as $rank => $teamId) {
                $poolRankOf[$teamId] = $rank;
            }
        }

        // ================= QUALIFICATION =================
        (new QualificationModel())->generate($tournamentId, $poolTeamIds);

        $matchModel = new MatchModel();

        MiniTest::suite('Circuit complet 16 équipes — qualification (4 poules de 4, 4 terrains)');

        $qualQuery = $pdo->prepare("SELECT * FROM matches WHERE tournament_id = :t AND phase = 'qualification' ORDER BY scheduled_start, court_id");
        $qualQuery->execute(['t' => $tournamentId]);
        $qualMatches = $qualQuery->fetchAll();

        MiniTest::test('24 matchs générés (4 poules x C(4,2)=6)', function () use ($qualMatches) {
            MiniTest::assertSame(24, count($qualMatches));
        });

        MiniTest::test('tous les matchs ont un horaire et un terrain', function () use ($qualMatches) {
            foreach ($qualMatches as $m) {
                MiniTest::assertTrue($m->scheduled_start !== null && $m->scheduled_end !== null && $m->court_id !== null, "match #{$m->match_number} sans horaire/terrain");
            }
        });

        MiniTest::test('aucun chevauchement sur un même terrain', function () use ($qualMatches) {
            $byCourt = [];
            foreach ($qualMatches as $m) {
                $byCourt[$m->court_id][] = $m;
            }
            foreach ($byCourt as $courtId => $onCourt) {
                for ($i = 0; $i < count($onCourt); $i++) {
                    for ($j = $i + 1; $j < count($onCourt); $j++) {
                        MiniTest::assertFalse(
                            enoa16_overlaps($onCourt[$i]->scheduled_start, $onCourt[$i]->scheduled_end, $onCourt[$j]->scheduled_start, $onCourt[$j]->scheduled_end),
                            "terrain {$courtId} : matchs #{$onCourt[$i]->match_number} et #{$onCourt[$j]->match_number} se chevauchent"
                        );
                    }
                }
            }
        });

        MiniTest::test("aucune équipe n'est double-réservée", function () use ($qualMatches, $teamIds) {
            foreach ($teamIds as $teamId) {
                $own = array_values(array_filter($qualMatches, static fn ($m) => (int) $m->team_a_id === $teamId || (int) $m->team_b_id === $teamId));
                for ($i = 0; $i < count($own); $i++) {
                    for ($j = $i + 1; $j < count($own); $j++) {
                        MiniTest::assertFalse(
                            enoa16_overlaps($own[$i]->scheduled_start, $own[$i]->scheduled_end, $own[$j]->scheduled_start, $own[$j]->scheduled_end),
                            "équipe {$teamId} : matchs #{$own[$i]->match_number} et #{$own[$j]->match_number} se chevauchent"
                        );
                    }
                }
            }
        });

        MiniTest::test('CORRIGÉ : une équipe avec des matchs générés (même "pending", pas encore joués) est protégée contre la suppression', function () use ($teamModel, $teamIds) {
            MiniTest::assertTrue($teamModel->hasScheduledMatches($teamIds[0]), 'équipe avec des matchs de qualification générés : protégée');
        });

        // --- Saisie de tous les scores de qualification : la mieux classée de la poule gagne toujours,
        // jamais d'égalité, un écart qui varie par match_number pour ne jamais recréer d'égalité au général.
        foreach ($qualMatches as $m) {
            $teamA = (int) $m->team_a_id;
            $teamB = (int) $m->team_b_id;
            $aIsBetter = $poolRankOf[$teamA] < $poolRankOf[$teamB];
            $loserScore = max(0, 21 - 5 - ((int) $m->match_number % 7));
            $scoreA = $aIsBetter ? 21 : $loserScore;
            $scoreB = $aIsBetter ? $loserScore : 21;
            $matchModel->updateScore((int) $m->id, $scoreA, $scoreB, 'finished');
        }

        $qualRefresh = enoa16_refresh_qual($tournamentId);
        $standingsByPool = $qualRefresh['standingsByPool'];
        $globalStandings = $qualRefresh['globalStandings'];

        MiniTest::test('classement de chaque poule : la mieux classée gagne tout (3V/9pts), la dernière perd tout (0V/3pts)', function () use ($standingsByPool, $poolTeamIds) {
            foreach ($standingsByPool as $poolId => $rows) {
                MiniTest::assertSame(4, count($rows), "poule {$poolId} : 4 lignes de classement");
                $expectedOrder = $poolTeamIds[$poolId];
                foreach ($rows as $i => $row) {
                    MiniTest::assertSame($expectedOrder[$i], $row['team_id'], "poule {$poolId} position " . ($i + 1));
                }
                MiniTest::assertSame(3, $rows[0]['victoires'], "poule {$poolId} : 1ère invaincue");
                MiniTest::assertSame(9.0, $rows[0]['points'], "poule {$poolId} : 1ère à 9 points");
                MiniTest::assertSame(0, $rows[3]['victoires'], "poule {$poolId} : dernière sans victoire");
                MiniTest::assertSame(3.0, $rows[3]['points'], "poule {$poolId} : dernière à 3 points (défaites)");
            }
        });

        MiniTest::test('classement général : 16 équipes, positions 1..16 uniques, tri points/diff_sets/sets_pour/nom respecté', function () use ($globalStandings) {
            MiniTest::assertSame(16, count($globalStandings));
            $positions = array_column($globalStandings, 'position');
            sort($positions);
            MiniTest::assertSame(range(1, 16), $positions, 'positions = permutation de 1..16');

            for ($i = 0; $i < count($globalStandings) - 1; $i++) {
                $cur = $globalStandings[$i];
                $next = $globalStandings[$i + 1];
                $cmp = ($next['points'] <=> $cur['points'])
                    ?: ($next['diff_sets'] <=> $cur['diff_sets'])
                    ?: ($next['sets_pour'] <=> $cur['sets_pour'])
                    ?: strcasecmp($cur['nom'], $next['nom']);
                MiniTest::assertTrue($cmp <= 0, "ordre rompu entre position {$cur['position']} ({$cur['nom']}) et " . ($cur['position'] + 1) . " ({$next['nom']})");
            }
        });

        $rankingRows = (new RankingModel())->forPhase($tournamentId, null, 'global');
        MiniTest::test("le classement général est bien persisté dans 'rankings' (pas seulement calculé à la volée)", function () use ($rankingRows) {
            MiniTest::assertSame(16, count($rankingRows));
        });

        // --- CORRIGÉ : une équipe déclarée forfait après coup (pas seulement avant la
        // composition des poules) doit disparaître du classement de sa poule et du classement
        // général. Testé en isolation ici, puis annulé immédiatement pour ne pas perturber le
        // scénario déterministe à 16 équipes qui suit.
        $forfaitCandidateId = (int) $globalStandings[15]['team_id'];
        $teamModel->updateStatus($forfaitCandidateId, 'forfait');
        $afterForfait = enoa16_refresh_qual($tournamentId);

        MiniTest::test("CORRIGÉ : une équipe déclarée forfait après les qualifications disparaît du classement général (et de sa poule)", function () use ($afterForfait, $forfaitCandidateId) {
            MiniTest::assertSame(15, count($afterForfait['globalStandings']), 'classement général : 15 équipes restantes (16 - 1 forfait)');
            foreach ($afterForfait['globalStandings'] as $row) {
                MiniTest::assertFalse($row['team_id'] === $forfaitCandidateId, 'équipe forfait absente du classement général');
            }
            $positions = array_column($afterForfait['globalStandings'], 'position');
            sort($positions);
            MiniTest::assertSame(range(1, 15), $positions, 'positions renumérotées sans trou après exclusion');

            foreach ($afterForfait['standingsByPool'] as $rows) {
                foreach ($rows as $row) {
                    MiniTest::assertFalse($row['team_id'] === $forfaitCandidateId, 'équipe forfait absente du classement de sa poule');
                }
            }
        });

        // Annule le forfait et recalcule : la suite du scénario (phase finale déterministe à
        // 16 équipes) a besoin des 16 équipes qualifiées.
        $teamModel->updateStatus($forfaitCandidateId, 'inscrite');
        $qualRefresh = enoa16_refresh_qual($tournamentId);
        $globalStandings = $qualRefresh['globalStandings'];
        MiniTest::test('le forfait annulé restaure bien les 16 équipes au classement général', function () use ($globalStandings) {
            MiniTest::assertSame(16, count($globalStandings));
        });

        // ================= PHASE FINALE =================
        MiniTest::suite('Circuit complet 16 équipes — phase finale (upper 8 / lower 8, sans tour préliminaire)');

        $rankedTeamIds = array_column($globalStandings, 'team_id');

        // --- CORRIGÉ : le réglage "Équipes en poule haute/basse" (tournament_settings.
        // nombre_equipes_upper/nombre_equipes_lower) est maintenant lu par
        // MatchController::resolveUpperCount() (utilisé par generateFinalPhase()) — mais
        // seulement quand il correspond exactement au nombre réel d'équipes classées, sinon
        // c'est un réglage périmé (forfait, effectif changé depuis) et on garde le filet de
        // sécurité ceil(n/2)/floor(n/2) d'avant.
        $tournamentModel->updateSettings($tournamentId, array_merge($data, ['nombre_equipes_upper' => 10, 'nombre_equipes_lower' => 6]));
        MiniTest::test('CORRIGÉ : réglage 10/6 cohérent avec les 16 équipes classées (10+6=16) -> désormais respecté par la phase finale', function () use ($tournamentId) {
            MiniTest::assertSame(10, enoa16_resolve_upper_count($tournamentId, 16));
        });

        $tournamentModel->updateSettings($tournamentId, array_merge($data, ['nombre_equipes_upper' => 4, 'nombre_equipes_lower' => 4]));
        MiniTest::test('filet de sécurité : réglage périmé (4+4=8 ne correspond plus aux 16 équipes classées) -> retombe sur un partage strict en 2 moitiés', function () use ($tournamentId) {
            MiniTest::assertSame(8, enoa16_resolve_upper_count($tournamentId, 16));
        });

        // Remet 8/8 (cohérent avec les 16 équipes) pour la génération réelle qui suit.
        $tournamentModel->updateSettings($tournamentId, $data);

        $upperCount = enoa16_resolve_upper_count($tournamentId, count($rankedTeamIds));
        $upperIds = array_slice($rankedTeamIds, 0, $upperCount);
        $lowerIds = array_slice($rankedTeamIds, $upperCount);

        MiniTest::test('découpage upper/lower réellement utilisé : 8 et 8 (16 équipes, réglage 8/8 cohérent)', function () use ($upperIds, $lowerIds) {
            MiniTest::assertSame(8, count($upperIds));
            MiniTest::assertSame(8, count($lowerIds));
        });

        (new BracketModel())->generateFinalPhase($tournamentId, $upperIds, $lowerIds);

        $finalQuery = $pdo->prepare("SELECT * FROM matches WHERE tournament_id = :t AND phase IN ('upper','lower','grand_final') ORDER BY phase, round_number, match_number");
        $finalQuery->execute(['t' => $tournamentId]);
        $finalMatches = $finalQuery->fetchAll();

        MiniTest::test('15 matchs générés (7 upper + 7 lower + 1 grande finale), aucun exempt', function () use ($finalMatches) {
            MiniTest::assertSame(15, count($finalMatches));
            foreach ($finalMatches as $m) {
                $isExempt = $m->status === 'finished' && (($m->team_a_id !== null) !== ($m->team_b_id !== null));
                MiniTest::assertFalse($isExempt, "match #{$m->match_number} exempt (byes interdits depuis le tour préliminaire) — ne devrait plus jamais se produire à 8 équipes");
            }
        });

        MiniTest::test('les 8 quarts de finale (upper+lower round 1) ont déjà leurs deux équipes ; les rounds suivants attendent', function () use ($finalMatches) {
            $round1 = array_values(array_filter($finalMatches, static fn ($m) => in_array($m->phase, ['upper', 'lower'], true) && (int) $m->round_number === 1));
            MiniTest::assertSame(8, count($round1));
            foreach ($round1 as $m) {
                MiniTest::assertTrue($m->team_a_id !== null && $m->team_b_id !== null, "quart #{$m->match_number} devrait avoir ses 2 équipes dès la génération");
            }
            $later = array_values(array_filter($finalMatches, static fn ($m) => $m->phase !== 'grand_final' && (int) $m->round_number > 1));
            foreach ($later as $m) {
                MiniTest::assertTrue($m->team_a_id === null && $m->team_b_id === null, "match #{$m->match_number} (round {$m->round_number}) ne devrait pas encore avoir d'équipe");
            }
        });

        // --- Auto-play : classement global comme référence absolue de "qui doit gagner"
        // (rang le plus bas = meilleure équipe, y compris grande finale upper vs lower).
        $globalRankIndex = array_flip($rankedTeamIds);

        $safetyCounter = 0;
        do {
            $pendingQuery = $pdo->prepare(
                "SELECT * FROM matches WHERE tournament_id = :t AND phase IN ('upper','lower','grand_final')
                 AND status = 'pending' AND team_a_id IS NOT NULL AND team_b_id IS NOT NULL"
            );
            $pendingQuery->execute(['t' => $tournamentId]);
            $playable = $pendingQuery->fetchAll();

            foreach ($playable as $m) {
                $teamA = (int) $m->team_a_id;
                $teamB = (int) $m->team_b_id;
                $aIsBetter = $globalRankIndex[$teamA] < $globalRankIndex[$teamB];
                $loserScore = max(0, 21 - 3 - ((int) $m->match_number % 10));
                $scoreA = $aIsBetter ? 21 : $loserScore;
                $scoreB = $aIsBetter ? $loserScore : 21;
                $matchModel->updateScore((int) $m->id, $scoreA, $scoreB, 'finished');
            }

            $safetyCounter++;
        } while (count($playable) > 0 && $safetyCounter < 20);

        MiniTest::test("l'auto-play de la phase finale converge (pas de blocage dans la propagation de vainqueur)", function () use ($safetyCounter) {
            MiniTest::assertLessOrEqual(10, $safetyCounter, 'nombre de vagues de propagation raisonnable pour 4 rounds (15 matchs)');
        });

        $finalQuery->execute(['t' => $tournamentId]);
        $finalMatchesPlayed = $finalQuery->fetchAll();

        MiniTest::test('les 15 matchs de phase finale sont tous terminés (aucun bloqué)', function () use ($finalMatchesPlayed) {
            foreach ($finalMatchesPlayed as $m) {
                MiniTest::assertSame('finished', $m->status, "match #{$m->match_number} ({$m->phase}) toujours pas terminé");
            }
        });

        $grandFinal = null;
        foreach ($finalMatchesPlayed as $m) {
            if ($m->phase === 'grand_final') {
                $grandFinal = $m;
            }
        }

        MiniTest::test("la grande finale est bien gagnée par l'équipe #1 du classement général (upper) contre l'équipe #9 (lower)", function () use ($grandFinal, $rankedTeamIds) {
            MiniTest::assertTrue($grandFinal !== null, 'grande finale introuvable');
            MiniTest::assertSame($rankedTeamIds[0], (int) $grandFinal->winner_team_id, 'championne attendue = 1ère du classement général');
            $expectedRunnerUp = $rankedTeamIds[8];
            $actualOther = (int) $grandFinal->team_a_id === (int) $grandFinal->winner_team_id ? (int) $grandFinal->team_b_id : (int) $grandFinal->team_a_id;
            MiniTest::assertSame($expectedRunnerUp, $actualOther, 'finaliste attendue = 1ère du lower bracket (9e du classement général)');
        });

        enoa16_refresh_final($tournamentId);
        $finalRanking = (new RankingModel())->forPhase($tournamentId, null, 'final');

        MiniTest::test('classement final persisté : 16 équipes, positions 1..16 uniques, champion/finaliste en tête', function () use ($finalRanking, $rankedTeamIds) {
            MiniTest::assertSame(16, count($finalRanking));
            $positions = array_map('intval', array_column($finalRanking, 'position'));
            sort($positions);
            MiniTest::assertSame(range(1, 16), $positions);

            $byPosition = [];
            foreach ($finalRanking as $row) {
                $byPosition[(int) $row->position] = (int) $row->team_id;
            }
            MiniTest::assertSame($rankedTeamIds[0], $byPosition[1], 'position 1 = championne');
            MiniTest::assertSame($rankedTeamIds[8], $byPosition[2], 'position 2 = finaliste (1ère du lower bracket)');
        });

        // --- Recalcul indépendant du tour d'élimination de chaque équipe (même méthode que
        // Controller::refreshFinalRanking, ré-implémentée ici à partir des lignes brutes de
        // `matches`) pour vérifier que l'ordre PERSISTÉ dans `rankings` (positions 3..16)
        // respecte bien "tour d'élimination décroissant, puis classement de qualification
        // croissant à égalité" — et pas seulement que les 2 premières places sont bonnes.
        $eliminationRound = [];
        foreach ($finalMatchesPlayed as $m) {
            if ($m->phase === 'grand_final') {
                continue;
            }
            foreach (['team_a_id', 'team_b_id'] as $col) {
                if ($m->$col === null) {
                    continue;
                }
                $tid = (int) $m->$col;
                $round = (int) $m->round_number;
                if (!isset($eliminationRound[$tid]) || $round > $eliminationRound[$tid]) {
                    $eliminationRound[$tid] = $round;
                }
            }
        }
        $qualPosition = array_flip($rankedTeamIds);
        foreach ($qualPosition as &$p) {
            $p += 1;
        }
        unset($p);

        MiniTest::test('classement final (positions 3 à 16) : tour d\'élimination décroissant, puis classement qualif. croissant à égalité', function () use ($finalRanking, $eliminationRound, $qualPosition, $rankedTeamIds) {
            $championId = $rankedTeamIds[0];
            $runnerUpId = $rankedTeamIds[8];

            $rows = array_filter($finalRanking, static fn ($r) => (int) $r->team_id !== $championId && (int) $r->team_id !== $runnerUpId);
            usort($rows, static fn ($a, $b) => (int) $a->position <=> (int) $b->position);

            for ($i = 0; $i < count($rows) - 1; $i++) {
                $cur = (int) $rows[$i]->team_id;
                $next = (int) $rows[$i + 1]->team_id;
                $curRound = $eliminationRound[$cur] ?? 0;
                $nextRound = $eliminationRound[$next] ?? 0;
                $cmp = ($nextRound <=> $curRound) ?: (($qualPosition[$cur] ?? PHP_INT_MAX) <=> ($qualPosition[$next] ?? PHP_INT_MAX));
                MiniTest::assertTrue($cmp <= 0, "ordre rompu en position " . ((int) $rows[$i]->position) . "/" . ((int) $rows[$i + 1]->position) . " (équipes {$cur} et {$next})");
            }
        });
    } finally {
        $tournamentModel->delete($tournamentId);
    }
}

try {
    enoa16_run_full_tournament_scenario();
} catch (\Throwable $e) {
    MiniTest::suite('Circuit complet 16 équipes');
    MiniTest::test('scénario complet (création tournoi de test + circuit intégral)', function () use ($e) {
        throw $e;
    });
}
