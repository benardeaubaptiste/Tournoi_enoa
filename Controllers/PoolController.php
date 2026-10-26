<?php

namespace tournoi_enoa\Controllers;

use tournoi_enoa\Core\Auth;
use tournoi_enoa\Models\AdminModel;
use tournoi_enoa\Models\PoolModel;
use tournoi_enoa\Models\QualificationModel;
use tournoi_enoa\Models\TeamModel;
use tournoi_enoa\Models\TournamentModel;

class PoolController extends Controller
{
    public function index(int $tournamentId = 0): void
    {
        $poolModel = new PoolModel();
        $teams = (new TeamModel())->byTournament($tournamentId);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $validTeamIds = array_map(static fn ($team) => (int) $team->id, $teams);
            $this->save($tournamentId, $poolModel, $validTeamIds);

            $savedPools = $poolModel->byTournament($tournamentId);
            $tournamentModel = new TournamentModel();
            if ($savedPools) {
                $tournamentModel->advanceStatut($tournamentId, 'poules_a_generer');
                $allValidated = true;
                foreach ($savedPools as $pool) {
                    if ($pool->statut !== 'validee' || (int) $pool->team_count < 2) {
                        $allValidated = false;
                        break;
                    }
                }
                if ($allValidated) {
                    $tournamentModel->advanceStatut($tournamentId, 'poules_validees');
                }
            }

            header('Location: index.php?controller=pool&action=index&tournamentId=' . $tournamentId . '&saved=1');
            exit;
        }

        $ranking = $this->refreshQualificationRankings($tournamentId);
        $pools = $ranking['pools'];
        $assignments = $ranking['assignments'];
        $qualMatches = $ranking['qualMatches'];
        $matchesByPool = $ranking['matchesByPool'];
        $standingsByPool = $ranking['standingsByPool'];
        $globalStandings = $ranking['globalStandings'];
        $saved = isset($_GET['saved']);

        $pendingQualCount = 0;
        foreach ($qualMatches as $match) {
            if ($match->status !== 'finished' && $match->status !== 'cancelled') {
                $pendingQualCount++;
            }
        }

        $bracketError = $_GET['bracketError'] ?? null;
        $qualGenerated = isset($_GET['qualGenerated']);
        $qualError = $_GET['qualError'] ?? null;
        $hasQualMatches = count($qualMatches) > 0;
        $settings = (new TournamentModel())->settings($tournamentId);

        $data = compact(
            'pools',
            'teams',
            'assignments',
            'tournamentId',
            'saved',
            'matchesByPool',
            'standingsByPool',
            'globalStandings',
            'pendingQualCount',
            'bracketError',
            'qualGenerated',
            'qualError',
            'hasQualMatches',
            'settings'
        );

        // Rafraîchissement partiel du bloc "Résultats" après une saisie de score depuis la
        // modale (voir _scoreModal.php, patchMode='refresh') : on ne renvoie que ce bloc,
        // pas tout le tableau des poules (qui peut être en cours d'édition côté client).
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            header('Content-Type: text/html; charset=UTF-8');
            echo $this->renderPartial('pool/_results', $data);
            return;
        }

        $this->render('pool/index', $data);
    }

    /**
     * Génère (ou régénère) les matchs de qualification à partir de la composition
     * actuelle des poules : tour complet aller simple par poule, planifié sur les
     * terrains disponibles d'après les horaires réglés pour le tournoi.
     */
    public function generateMatches(int $tournamentId = 0): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=pool&action=index&tournamentId=' . $tournamentId);
            exit;
        }

        $qualificationModel = new QualificationModel();

        if ($qualificationModel->hasResults($tournamentId)) {
            header('Location: index.php?controller=pool&action=index&tournamentId=' . $tournamentId . '&qualError=already_played');
            exit;
        }

        $poolModel = new PoolModel();
        $pools = $poolModel->byTournament($tournamentId);
        $assignments = $poolModel->teamAssignments($tournamentId);

        $poolTeamIds = [];
        foreach ($pools as $pool) {
            $poolTeamIds[$pool->id] = array_column($assignments[$pool->id] ?? [], 'team_id');
        }

        $qualificationModel->clear($tournamentId);
        $qualificationModel->generate($tournamentId, $poolTeamIds);

        (new TournamentModel())->advanceStatut($tournamentId, 'planning_genere');
        (new AdminModel())->logAction(Auth::id(), 'generate_qualification_matches', 'Génération des matchs de qualification', $tournamentId);

        header('Location: index.php?controller=pool&action=index&tournamentId=' . $tournamentId . '&qualGenerated=1');
        exit;
    }

    /**
     * Compose automatiquement les poules à partir des équipes inscrites (hors forfaits) et
     * des réglages du tournoi (nombre de poules, mode de répartition), puis enchaîne
     * directement sur la génération des matchs de qualification. Remplace le tableau de
     * poules existant s'il n'a pas encore de résultat enregistré.
     */
    public function autoGenerate(int $tournamentId = 0): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=pool&action=index&tournamentId=' . $tournamentId);
            exit;
        }

        $qualificationModel = new QualificationModel();
        if ($qualificationModel->hasResults($tournamentId)) {
            header('Location: index.php?controller=pool&action=index&tournamentId=' . $tournamentId . '&qualError=already_played');
            exit;
        }

        $tournamentModel = new TournamentModel();
        $settings = $tournamentModel->settings($tournamentId);
        $nombrePoules = $settings ? max(1, min(PoolModel::MAX_POOLS, (int) $settings->nombre_poules)) : 2;
        $modeRepartition = $settings->mode_repartition ?? 'ordre_inscription';

        $teams = (new TeamModel())->byTournament($tournamentId);
        $teamIds = array_values(array_map(
            static fn ($team) => (int) $team->id,
            array_filter($teams, static fn ($team) => $team->statut !== 'forfait')
        ));

        if (count($teamIds) < 2) {
            header('Location: index.php?controller=pool&action=index&tournamentId=' . $tournamentId . '&qualError=not_enough_teams');
            exit;
        }

        if ($modeRepartition === 'aleatoire') {
            shuffle($teamIds);
        }

        // Répartition "à la donne" (round-robin), équilibrée entre les poules quel que
        // soit le mode : par ordre d'inscription tel quel, ou après mélange aléatoire.
        $poolsTeamIds = array_fill(0, $nombrePoules, []);
        foreach ($teamIds as $index => $teamId) {
            $poolsTeamIds[$index % $nombrePoules][] = $teamId;
        }

        $poolModel = new PoolModel();

        // Aucun résultat n'existe encore (vérifié ci-dessus) : le tableau actuel peut être
        // remplacé sans rien perdre.
        foreach ($poolModel->byTournament($tournamentId) as $existingPool) {
            $poolModel->delete((int) $existingPool->id, $tournamentId);
        }

        $letters = range('A', 'D');
        $createdPoolIds = [];
        foreach ($poolsTeamIds as $index => $poolTeamIds) {
            if (!$poolTeamIds) {
                continue;
            }
            $numero = $index + 1;
            $poolId = $poolModel->create($tournamentId, $numero, 'Poule ' . ($letters[$index] ?? $numero), 'validee');
            $poolModel->syncTeams($poolId, $poolTeamIds);
            $createdPoolIds[$poolId] = $poolTeamIds;
        }

        $tournamentModel->advanceStatut($tournamentId, 'poules_validees');

        $qualificationModel->clear($tournamentId);
        $qualificationModel->generate($tournamentId, $createdPoolIds);
        $tournamentModel->advanceStatut($tournamentId, 'planning_genere');

        (new AdminModel())->logAction(
            Auth::id(),
            'auto_generate_pools',
            sprintf('Génération automatique de %d poule(s) et des matchs de qualification (%d équipes, mode %s)', count($createdPoolIds), count($teamIds), $modeRepartition),
            $tournamentId
        );

        header('Location: index.php?controller=pool&action=index&tournamentId=' . $tournamentId . '&qualGenerated=1');
        exit;
    }

    /** Enregistre le tableau des poules (création, renommage, suppression, composition). */
    private function save(int $tournamentId, PoolModel $poolModel, array $validTeamIds): void
    {
        $deletedIds = array_filter(array_map('intval', explode(',', (string) ($_POST['deleted_pool_ids'] ?? ''))));
        foreach ($deletedIds as $id) {
            $poolModel->delete($id, $tournamentId);
        }

        $usedTeamIds = [];
        $letters = range('A', 'D');

        foreach ((array) ($_POST['pools'] ?? []) as $poolData) {
            $poolId = (int) ($poolData['id'] ?? 0);
            if ($poolId > 0 && in_array($poolId, $deletedIds, true)) {
                continue;
            }

            $nom = trim((string) ($poolData['nom'] ?? ''));
            $statut = !empty($poolData['validee']) ? 'validee' : 'draft';

            if ($poolId > 0) {
                $poolModel->update($poolId, $nom !== '' ? $nom : 'Poule', $statut);
            } else {
                $numero = $poolModel->nextNumero($tournamentId);
                if ($numero === 0) {
                    continue; // limite de poules atteinte
                }
                $nom = $nom !== '' ? $nom : 'Poule ' . ($letters[$numero - 1] ?? $numero);
                $poolId = $poolModel->create($tournamentId, $numero, $nom, $statut);
            }

            $teamIds = [];
            foreach ((array) ($poolData['teams'] ?? []) as $teamId) {
                $teamId = (int) $teamId;
                if ($teamId > 0 && in_array($teamId, $validTeamIds, true) && !in_array($teamId, $usedTeamIds, true)) {
                    $teamIds[] = $teamId;
                    $usedTeamIds[] = $teamId;
                }
            }

            $poolModel->syncTeams($poolId, $teamIds);
        }
    }
}
