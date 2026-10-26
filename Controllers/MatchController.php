<?php

namespace tournoi_enoa\Controllers;

use tournoi_enoa\Core\Auth;
use tournoi_enoa\Core\BracketTree;
use tournoi_enoa\Core\Standings;
use tournoi_enoa\Models\AdminModel;
use tournoi_enoa\Models\BracketModel;
use tournoi_enoa\Models\MatchModel;
use tournoi_enoa\Models\PoolModel;
use tournoi_enoa\Models\QualificationModel;
use tournoi_enoa\Models\RankingModel;
use tournoi_enoa\Models\TeamModel;
use tournoi_enoa\Models\TournamentModel;

class MatchController extends Controller
{
    /** Seules ces valeurs sont acceptées pour matches.status (colonne ENUM en base). */
    private const VALID_STATUSES = ['pending', 'scheduled', 'live', 'finished', 'cancelled'];

    public function index(int $tournamentId = 0): void
    {
        $matches = (new MatchModel())->byTournament($tournamentId);
        $bracketGenerated = isset($_GET['bracketGenerated']);
        $missingCourtsCount = 0;
        foreach ($matches as $match) {
            if ($match->court_id === null) {
                $missingCourtsCount++;
            }
        }
        $settings = (new TournamentModel())->settings($tournamentId);
        $this->render('match/index', compact('matches', 'tournamentId', 'bracketGenerated', 'missingCourtsCount', 'settings'));
    }

    /**
     * Attribue un terrain aux matchs qui n'en ont jamais reçu, quel que soit leur statut —
     * typiquement des matchs saisis avant que les horaires/terrains du tournoi ne soient
     * finalisés (la replanification automatique ne touche que les matchs encore en
     * attente). Ne déplace jamais un match qui a déjà un terrain.
     */
    public function assignMissingCourts(int $tournamentId = 0): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=match&action=index&tournamentId=' . $tournamentId);
            exit;
        }

        $count = (new QualificationModel())->assignMissingCourts($tournamentId)
            + (new BracketModel())->assignMissingCourts($tournamentId);

        if ($count > 0) {
            (new AdminModel())->logAction(
                Auth::id(),
                'assign_missing_courts',
                sprintf('Terrain attribué a posteriori à %d match(s) qui n\'en avaient pas', $count),
                $tournamentId
            );
        }

        header('Location: index.php?controller=match&action=index&tournamentId=' . $tournamentId . '&courtsAssigned=' . $count);
        exit;
    }

    /**
     * Suivi live : minuteur de match complet + prochains matchs à jouer (autant que de
     * terrains configurés, ex. 3 terrains -> 3 prochains matchs), triés par horaire. Accessible
     * depuis la nav sans préciser de tournoi : on retombe alors sur le dernier tournoi
     * effectivement consulté (mémorisé en session par Router, ex. via le sélecteur "Tournoi
     * actif" du tableau de bord), ou à défaut sur celui en cours / le plus récent.
     */
    public function live(int $tournamentId = 0): void
    {
        $tournamentModel = new TournamentModel();
        if ($tournamentId <= 0) {
            $tournamentId = (int) ($_SESSION['selectedTournamentId'] ?? 0);
        }
        $tournament = $tournamentId > 0 ? $tournamentModel->find($tournamentId) : $tournamentModel->currentOrLatest();

        if (!$tournament) {
            $this->render('match/live', ['tournament' => null, 'tournamentId' => 0, 'settings' => null, 'upcomingMatches' => []]);
            return;
        }

        $tournamentId = (int) $tournament->id;
        $settings = $tournamentModel->settings($tournamentId);
        $upcomingMatches = (new MatchModel())->upcoming($tournamentId, $this->upcomingMatchesLimit($settings));

        $this->render('match/live', compact('tournament', 'tournamentId', 'settings', 'upcomingMatches'));
    }

    /**
     * Fragment "prochains matchs" de la page de suivi live, interrogé en AJAX à intervalle
     * régulier par _scheduleWidget.php (mode live) : permet à un écran laissé ouvert sans
     * interaction de refléter les scores saisis entretemps depuis une autre page, sans jamais
     * recharger toute la page (ce qui perdrait le décompte du minuteur, dont l'état ne vit
     * que côté client).
     */
    public function liveUpcoming(int $tournamentId = 0): void
    {
        if ($tournamentId <= 0) {
            http_response_code(400);
            return;
        }

        $settings = (new TournamentModel())->settings($tournamentId);
        $upcomingMatches = (new MatchModel())->upcoming($tournamentId, $this->upcomingMatchesLimit($settings));

        // Même règle que match/live.php pour choisir l'étape par défaut du minuteur : le
        // prochain match donne une idée fiable de l'étape en cours. Renvoyée à chaque
        // rafraîchissement pour que _scheduleWidget.php (mode live) puisse faire basculer le
        // minuteur de qualification à phase finale tout seul, sans attendre un rechargement
        // de la page (voir le script JS "phase-widget" plus bas dans ce fichier).
        $timerPhase = ($upcomingMatches && $upcomingMatches[0]->phase !== 'qualification') ? 'phase_finale' : 'qualification';

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'html' => $this->renderPartial('match/_upcomingMatches', compact('upcomingMatches')),
            'timerPhase' => $timerPhase,
        ]);
    }

    /** Autant de prochains matchs affichés que de terrains configurés (un par terrain qui va se libérer). */
    private function upcomingMatchesLimit(?object $settings): int
    {
        return $settings ? max(1, (int) $settings->nombre_terrains) : 3;
    }

    /** Affichage en arbre de la phase finale (upper bracket / lower bracket / grande finale). */
    public function bracket(int $tournamentId = 0): void
    {
        $finalPhaseMatches = (new MatchModel())->finalPhaseByTournament($tournamentId);
        $tree = BracketTree::build($finalPhaseMatches);
        $finalRanking = (new RankingModel())->forPhase($tournamentId, null, 'final');
        $bracketGenerated = isset($_GET['bracketGenerated']);
        $settings = (new TournamentModel())->settings($tournamentId);
        $data = compact('tree', 'tournamentId', 'bracketGenerated', 'finalRanking', 'settings', 'finalPhaseMatches');

        // Rafraîchissement partiel demandé par la modale de saisie de score (une équipe
        // peut être promue au tour suivant après une saisie) : on ne renvoie que le
        // contenu de la page, à remplacer en place sans recharger toute la page.
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            header('Content-Type: text/html; charset=UTF-8');
            echo $this->renderPartial('match/bracket', $data);
            return;
        }

        $this->render('match/bracket', $data);
    }

    public function score(int $id = 0, int $tournamentId = 0): void
    {
        $error = null;
        // La modale de saisie rapide (clic sur un match) envoie ce header via fetch() pour
        // recevoir une réponse JSON au lieu d'une redirection : elle met à jour la ligne du
        // match sur place, sans recharger/faire défiler la page. La page dédiée /match/score
        // (accès direct, ou secours si fetch échoue) continue de fonctionner par redirection classique.
        $isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $scoreA = max(0, (int) ($_POST['score_team_a'] ?? 0));
            $scoreB = max(0, (int) ($_POST['score_team_b'] ?? 0));
            $status = (string) ($_POST['status'] ?? '');
            // D'où vient la soumission (liste des matchs, tableau des poules, ou arbre de la
            // phase finale), pour y renvoyer une fois enregistré.
            $backRoutes = ['match' => 'match&action=index', 'pool' => 'pool&action=index', 'bracket' => 'match&action=bracket'];
            $back = $backRoutes[$_POST['back'] ?? 'match'] ?? $backRoutes['match'];

            if (!in_array($status, self::VALID_STATUSES, true)) {
                $error = "Statut de match invalide.";
            } elseif ($status === 'finished' && $scoreA === $scoreB) {
                $error = "Un match terminé ne peut pas se solder par une égalité : le score le plus haut désigne le vainqueur.";
            } else {
                $matchModel = new MatchModel();
                $match = $matchModel->find($id);
                $matchModel->updateScore($id, $scoreA, $scoreB, $status);

                if ($match !== null) {
                    $tournamentModel = new TournamentModel();
                    if ($match->phase === 'qualification') {
                        if (in_array($status, ['live', 'finished'], true)) {
                            $tournamentModel->advanceStatut($tournamentId, 'en_cours');
                        }
                        $this->refreshQualificationRankings($tournamentId);
                    } elseif ($match->phase === 'grand_final' && $status === 'finished') {
                        $tournamentModel->advanceStatut($tournamentId, 'termine');
                        $this->refreshFinalRanking($tournamentId);
                    }
                    (new AdminModel())->logAction(
                        Auth::id(),
                        'score_match',
                        sprintf('Match #%d : %d - %d (%s)', $id, $scoreA, $scoreB, $status),
                        $tournamentId
                    );
                }

                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'match' => [
                            'id' => $id,
                            'score_team_a' => $scoreA,
                            'score_team_b' => $scoreB,
                            'status' => $status,
                        ],
                    ]);
                    exit;
                }

                header('Location: index.php?controller=' . $back . '&tournamentId=' . $tournamentId);
                exit;
            }

            if ($isAjax) {
                http_response_code(422);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $error]);
                exit;
            }
        }

        $match = (new MatchModel())->findWithTeams($id);
        $this->render('match/score', compact('id', 'tournamentId', 'error', 'match'));
    }

    /**
     * Génère (ou régénère) la phase finale à partir du classement général de qualification :
     * moitié haute du classement -> upper bracket, moitié basse -> lower bracket,
     * les deux vainqueurs se retrouvant en grande finale.
     */
    public function generateFinalPhase(int $tournamentId = 0): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=pool&action=index&tournamentId=' . $tournamentId);
            exit;
        }

        $poolModel = new PoolModel();
        $pools = $poolModel->byTournament($tournamentId);
        $assignments = $poolModel->teamAssignments($tournamentId);

        $qualifiedTeamNames = [];
        foreach ($pools as $pool) {
            foreach ($assignments[$pool->id] ?? [] as $slot) {
                $qualifiedTeamNames[$slot['team_id']] = $slot['nom'];
            }
        }

        $matchModel = new MatchModel();
        $qualMatches = $matchModel->qualificationByTournament($tournamentId);
        $standings = Standings::compute($qualMatches, $qualifiedTeamNames);

        // Une équipe déclarée forfait (à tout moment du tournoi, pas seulement avant la
        // génération des poules) ne doit jamais se retrouver dans la phase finale, même si
        // elle a déjà des résultats de qualification enregistrés.
        $forfaitTeamIds = $this->forfaitTeamIds($tournamentId);
        $standings = Standings::excludeTeams($standings, $forfaitTeamIds);
        $rankedTeamIds = array_column($standings, 'team_id');

        $bracketModel = new BracketModel();

        if (count($rankedTeamIds) < 2) {
            header('Location: index.php?controller=pool&action=index&tournamentId=' . $tournamentId . '&bracketError=not_enough_teams');
            exit;
        }

        if ($bracketModel->hasFinalPhaseResults($tournamentId)) {
            header('Location: index.php?controller=pool&action=index&tournamentId=' . $tournamentId . '&bracketError=already_played');
            exit;
        }

        $bracketModel->clearFinalPhase($tournamentId);

        $upperCount = $this->resolveUpperCount($tournamentId, count($rankedTeamIds));

        $bracketModel->generateFinalPhase(
            $tournamentId,
            array_slice($rankedTeamIds, 0, $upperCount),
            array_slice($rankedTeamIds, $upperCount)
        );

        (new TournamentModel())->advanceStatut($tournamentId, 'phase_finale');
        (new AdminModel())->logAction(Auth::id(), 'generate_final_phase', 'Génération de la phase finale', $tournamentId);

        header('Location: index.php?controller=match&action=bracket&tournamentId=' . $tournamentId . '&bracketGenerated=1');
        exit;
    }

    /**
     * Taille de l'upper bracket à utiliser pour $totalRanked équipes réellement classées :
     * respecte le réglage "Équipes en poule haute" (tournament_settings.nombre_equipes_upper)
     * uniquement s'il correspond exactement, avec nombre_equipes_lower, au nombre réel
     * d'équipes classées (sinon le réglage est périmé — forfait, effectif changé depuis son
     * réglage — et on retombe sur un partage strict en deux moitiés).
     */
    private function resolveUpperCount(int $tournamentId, int $totalRanked): int
    {
        $upperCount = (int) ceil($totalRanked / 2);

        $settings = (new TournamentModel())->settings($tournamentId);
        if ($settings) {
            $configuredUpper = (int) $settings->nombre_equipes_upper;
            $configuredLower = (int) $settings->nombre_equipes_lower;
            if ($configuredUpper > 0 && $configuredLower > 0 && ($configuredUpper + $configuredLower) === $totalRanked) {
                $upperCount = $configuredUpper;
            }
        }

        return $upperCount;
    }
}
