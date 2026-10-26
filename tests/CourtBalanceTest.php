<?php

/**
 * Vérifie que les terrains sont utilisés de façon équilibrée sur toute la planification —
 * signalement : avec 3 terrains et 12 matchs de qualification (2 poules de 4 équipes), le
 * Terrain 1 se retrouvait utilisé 6 fois et le Terrain 3 seulement 3 fois, au lieu de 4/4/4.
 *
 * Cause : QualificationModel::scheduleQualification() et BracketModel::scheduleFinalPhase()
 * répartissaient les matchs d'un même round par "vagues" de la taille du nombre de terrains
 * (array_chunk), et l'indice de terrain à l'intérieur d'une vague repartait de 0 à CHAQUE
 * vague/round. Dès qu'un round contient un nombre de matchs qui n'est pas multiple du nombre
 * de terrains, le terrain le plus à gauche récupère systématiquement le match "en trop" de
 * chaque round — six rounds avec un match en trop, six fois le même terrain en plus.
 *
 * Le correctif fait tourner un compteur de terrain CONTINU sur toute la planification
 * (jamais remis à 0), garanti sans double-réservation (une vague ne contient jamais plus de
 * matchs que de terrains) : ces tests vérifient le résultat sur une vraie base, terrain par
 * terrain, pas seulement l'absence de chevauchement (déjà couvert par
 * SchedulingIntegrationTest.php).
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

/**
 * Crée un tournoi de test entièrement réglé (horaires, terrains) avec des poules de tailles
 * données, génère la qualification, et renvoie le nombre de matchs de qualification par
 * terrain ({numero_terrain: nombre_de_matchs}). Nettoie le tournoi dans tous les cas.
 *
 * @param int[] $poolSizes taille de chaque poule (ex. [4, 4] = 2 poules de 4 équipes)
 */
function enoa_qualification_court_counts(array $poolSizes, int $nombreTerrains): array
{
    $pdo = DbConnect::createPdo();

    $tournamentModel = new TournamentModel();
    $totalTeams = array_sum($poolSizes);
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
        $tournamentModel->createSettings($tournamentId, [
            'prix_par_joueur' => 10,
            'heure_debut_tournoi' => '09:00',
            'heure_fin_tournoi' => '20:00',
        ]);

        $data = [
            'heure_debut_tournoi' => '09:00',
            'heure_fin_tournoi' => '20:00',
            'nombre_max_equipes' => max(8, $totalTeams),
            'nombre_poules' => max(2, count($poolSizes)),
            'mode_repartition' => 'ordre_inscription',
            'nombre_terrains' => $nombreTerrains,
            'temps_entre_matchs_qualification' => 5,
            'temps_entre_matchs_phase_finale' => 5,
            'duree_grande_finale' => 20,
            'prix_par_joueur' => 10,
            'max_matchs_consecutifs_qualifications' => 2,
            'max_matchs_consecutifs_phase_finale' => 1,
            'points_victoire' => 3,
            'points_defaite' => 1,
            'nombre_equipes_upper' => 0,
            'nombre_equipes_lower' => 0,
        ];
        $ref = new ReflectionMethod(TournamentController::class, 'computeSchedule');
        $ref->setAccessible(true);
        $schedule = $ref->invoke(new TournamentController(), $data);
        $data['heure_debut_qualifications'] = $schedule['heure_debut_qualifications'];
        $data['heure_fin_qualifications'] = $schedule['heure_fin_qualifications'];
        $data['heure_debut_phase_finale'] = $schedule['heure_debut_phase_finale'];
        $data['heure_fin_phase_finale'] = $schedule['heure_fin_phase_finale'];
        $data['duree_match_qualification'] = $schedule['qualification']['duree_match'];
        $data['duree_match_phase_finale'] = $schedule['phase_finale']['fenetre_minutes'] > 0 ? $schedule['phase_finale']['duree_match'] : null;
        $tournamentModel->updateSettings($tournamentId, $data);
        (new CourtModel())->syncForTournament($tournamentId, $nombreTerrains);

        $teamModel = new TeamModel();
        for ($i = 1; $i <= $totalTeams; $i++) {
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

        $poolModel = new PoolModel();
        $poolTeamIds = [];
        $offset = 0;
        foreach ($poolSizes as $index => $size) {
            $poolId = $poolModel->create($tournamentId, $index + 1, 'Poule ' . chr(65 + $index), 'validee');
            $ids = array_slice($teamIds, $offset, $size);
            $poolModel->syncTeams($poolId, $ids);
            $poolTeamIds[$poolId] = $ids;
            $offset += $size;
        }

        (new QualificationModel())->generate($tournamentId, $poolTeamIds);

        $stmt = $pdo->prepare(
            "SELECT c.numero, COUNT(*) AS n FROM matches m JOIN courts c ON c.id = m.court_id
             WHERE m.tournament_id = :t AND m.phase = 'qualification' GROUP BY c.numero"
        );
        $stmt->execute(['t' => $tournamentId]);

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(int) $row->numero] = (int) $row->n;
        }
        return $counts;
    } finally {
        $tournamentModel->delete($tournamentId);
    }
}

/**
 * Même principe pour la phase finale : upper/lower de tailles données, renvoie le nombre de
 * matchs de bracket (upper+lower, PAS la grande finale — toujours volontairement sur le
 * premier terrain, un seul match, non représentatif d'un déséquilibre) par terrain.
 */
function enoa_bracket_court_counts(int $upperCount, int $lowerCount, int $nombreTerrains): array
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
        $tournamentModel->createSettings($tournamentId, [
            'prix_par_joueur' => 10,
            'heure_debut_tournoi' => '09:00',
            'heure_fin_tournoi' => '20:00',
        ]);

        $totalTeams = $upperCount + $lowerCount;
        $data = [
            'heure_debut_tournoi' => '09:00',
            'heure_fin_tournoi' => '20:00',
            'nombre_max_equipes' => max(8, $totalTeams),
            'nombre_poules' => 2,
            'mode_repartition' => 'ordre_inscription',
            'nombre_terrains' => $nombreTerrains,
            'temps_entre_matchs_qualification' => 5,
            'temps_entre_matchs_phase_finale' => 5,
            'duree_grande_finale' => 20,
            'prix_par_joueur' => 10,
            'max_matchs_consecutifs_qualifications' => 2,
            'max_matchs_consecutifs_phase_finale' => 1,
            'points_victoire' => 3,
            'points_defaite' => 1,
            'nombre_equipes_upper' => $upperCount,
            'nombre_equipes_lower' => $lowerCount,
        ];
        $ref = new ReflectionMethod(TournamentController::class, 'computeSchedule');
        $ref->setAccessible(true);
        $schedule = $ref->invoke(new TournamentController(), $data);
        $data['heure_debut_qualifications'] = $schedule['heure_debut_qualifications'];
        $data['heure_fin_qualifications'] = $schedule['heure_fin_qualifications'];
        $data['heure_debut_phase_finale'] = $schedule['heure_debut_phase_finale'];
        $data['heure_fin_phase_finale'] = $schedule['heure_fin_phase_finale'];
        $data['duree_match_qualification'] = $schedule['qualification']['duree_match'];
        $data['duree_match_phase_finale'] = $schedule['phase_finale']['fenetre_minutes'] > 0 ? $schedule['phase_finale']['duree_match'] : null;
        $tournamentModel->updateSettings($tournamentId, $data);
        (new CourtModel())->syncForTournament($tournamentId, $nombreTerrains);

        $teamModel = new TeamModel();
        for ($i = 1; $i <= $totalTeams; $i++) {
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

        $stmt = $pdo->prepare(
            "SELECT c.numero, COUNT(*) AS n FROM matches m JOIN courts c ON c.id = m.court_id
             WHERE m.tournament_id = :t AND m.phase IN ('upper', 'lower') GROUP BY c.numero"
        );
        $stmt->execute(['t' => $tournamentId]);

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(int) $row->numero] = (int) $row->n;
        }
        return $counts;
    } finally {
        $tournamentModel->delete($tournamentId);
    }
}

MiniTest::suite('Répartition des terrains — équilibrée sur toute la planification, pas juste round par round');

MiniTest::test(
    'BUG signalé : 2 poules de 4 équipes, 3 terrains, 12 matchs de qualification -> 4/4/4, jamais 6/3/3',
    function () {
        $counts = enoa_qualification_court_counts([4, 4], 3);
        MiniTest::assertSame(3, count($counts), 'les 3 terrains doivent tous avoir reçu au moins un match');
        MiniTest::assertSame([1 => 4, 2 => 4, 3 => 4], $counts);
    }
);

MiniTest::test(
    '3 poules de 4 équipes (18 matchs), 3 terrains -> parfaitement 6/6/6',
    function () {
        $counts = enoa_qualification_court_counts([4, 4, 4], 3);
        MiniTest::assertSame([1 => 6, 2 => 6, 3 => 6], $counts);
    }
);

MiniTest::test(
    '2 poules de 5 équipes (20 matchs), 3 terrains -> non divisible, mais écart maximal de 1 (7/7/6), jamais 6/3 d\'écart',
    function () {
        $counts = enoa_qualification_court_counts([5, 5], 3);
        MiniTest::assertSame(3, count($counts));
        MiniTest::assertSame(20, array_sum($counts), 'aucun match ne doit se perdre en route');
        $max = max($counts);
        $min = min($counts);
        MiniTest::assertLessOrEqual(1, $max - $min, "écart entre le terrain le plus utilisé ({$max}) et le moins utilisé ({$min})");
    }
);

MiniTest::test(
    'un seul terrain configuré : tous les matchs y passent forcément (pas de division par zéro, pas de crash)',
    function () {
        $counts = enoa_qualification_court_counts([4, 4], 1);
        MiniTest::assertSame([1 => 12], $counts);
    }
);

MiniTest::test(
    'phase finale : upper=4/lower=4 (6 matchs de bracket, hors grande finale), 3 terrains -> 2/2/2',
    function () {
        $counts = enoa_bracket_court_counts(4, 4, 3);
        MiniTest::assertSame([1 => 2, 2 => 2, 3 => 2], $counts);
    }
);
