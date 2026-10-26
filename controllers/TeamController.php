<?php

namespace tournoi_enoa\Controllers;

use tournoi_enoa\Core\Auth;
use tournoi_enoa\Entities\Team;
use tournoi_enoa\Models\AdminModel;
use tournoi_enoa\Models\PlayerModel;
use tournoi_enoa\Models\TeamModel;
use tournoi_enoa\Models\TournamentModel;

class TeamController extends Controller
{
    /** @var string[] valeurs acceptées pour teams.statut (colonne ENUM en base) */
    private const VALID_STATUSES = ['inscrite', 'confirmee', 'forfait', 'elimine'];

    public function index(int $tournamentId = 0): void
    {
        $teams = (new TeamModel())->byTournament($tournamentId);
        $settings = (new TournamentModel())->settings($tournamentId);
        $maxEquipes = $settings ? (int) $settings->nombre_max_equipes : 0;
        $this->render('team/index', compact('teams', 'tournamentId', 'maxEquipes'));
    }

    public function add(int $tournamentId = 0): void
    {
        $tournamentModel = new TournamentModel();
        $prixParJoueur = $tournamentModel->prixParJoueur($tournamentId);
        $settings = $tournamentModel->settings($tournamentId);
        $maxEquipes = $settings ? (int) $settings->nombre_max_equipes : 0;
        $nombreEquipesActuel = count((new TeamModel())->byTournament($tournamentId));
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nom = trim((string) ($_POST['nom'] ?? ''));

            if ($maxEquipes > 0 && $nombreEquipesActuel >= $maxEquipes) {
                $error = "Le nombre maximum d'équipes pour ce tournoi ($maxEquipes) est déjà atteint. Modifiez ce réglage dans les paramètres du tournoi pour en inscrire davantage.";
            } elseif ($nom === '') {
                $error = "Le nom de l'équipe est obligatoire.";
            } else {
                $players = max(1, (int) ($_POST['nombre_joueurs'] ?? 1));
                $montantAPayer = $players * $prixParJoueur;
                $montantPaye = min($montantAPayer, max(0, (float) ($_POST['montant_paye'] ?? 0)));
                $statutPaiement = Team::calculerStatutPaiement($montantAPayer, $montantPaye);

                $model = new TeamModel();
                $model->create([
                    'tournament_id' => $tournamentId,
                    'numero_inscription' => $model->nextRegistrationNumber($tournamentId),
                    'nom' => $nom,
                    'club' => trim((string) ($_POST['club'] ?? '')) ?: null,
                    'nombre_joueurs' => $players,
                    'prix_par_joueur' => $prixParJoueur,
                    'montant_a_payer' => $montantAPayer,
                    'montant_paye' => $montantPaye,
                    'statut_paiement' => $statutPaiement,
                ]);
                (new AdminModel())->logAction(Auth::id(), 'add_team', 'Inscription de l\'équipe « ' . $nom . ' »', $tournamentId);
                header('Location: index.php?controller=team&action=index&tournamentId=' . $tournamentId);
                exit;
            }
        }
        $this->render('team/add', compact('tournamentId', 'prixParJoueur', 'error', 'maxEquipes', 'nombreEquipesActuel'));
    }

    /** Change le statut sportif d'une équipe (inscrite / confirmée / forfait / éliminée). */
    public function updateStatus(int $teamId = 0, int $tournamentId = 0): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $statut = (string) ($_POST['statut'] ?? '');
            if (in_array($statut, self::VALID_STATUSES, true)) {
                (new TeamModel())->updateStatus($teamId, $statut);
                (new AdminModel())->logAction(Auth::id(), 'update_team_status', "Équipe #$teamId → $statut", $tournamentId);
            }
        }
        header('Location: index.php?controller=team&action=show&teamId=' . $teamId . '&tournamentId=' . $tournamentId);
        exit;
    }

    public function show(int $teamId = 0, int $tournamentId = 0): void
    {
        $team = (new TeamModel())->find($teamId);
        if (!$team) {
            http_response_code(404);
            echo "Équipe introuvable";
            return;
        }
        $this->render('team/show', compact('team', 'tournamentId'));
    }

    /** Modifie le nom, le club, le nombre de joueurs et le prix/joueur d'une équipe déjà inscrite. */
    public function edit(int $teamId = 0, int $tournamentId = 0): void
    {
        $model = new TeamModel();
        $team = $model->find($teamId);
        if (!$team) {
            http_response_code(404);
            echo "Équipe introuvable";
            return;
        }

        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nom = trim((string) ($_POST['nom'] ?? ''));

            if ($nom === '') {
                $error = "Le nom de l'équipe est obligatoire.";
            } else {
                $players = max(1, (int) ($_POST['nombre_joueurs'] ?? 1));
                $prixParJoueur = max(0, (float) ($_POST['prix_par_joueur'] ?? 0));
                $montantAPayer = $players * $prixParJoueur;
                // Le montant déjà payé ne bouge pas de lui-même, mais ne peut pas dépasser
                // le nouveau montant dû (ex. si le nombre de joueurs est révisé à la baisse).
                $montantPaye = min($montantAPayer, (float) $team->montant_paye);
                $statutPaiement = Team::calculerStatutPaiement($montantAPayer, $montantPaye);

                $model->update($teamId, [
                    'nom' => $nom,
                    'club' => trim((string) ($_POST['club'] ?? '')) ?: null,
                    'nombre_joueurs' => $players,
                    'prix_par_joueur' => $prixParJoueur,
                    'montant_a_payer' => $montantAPayer,
                    'montant_paye' => $montantPaye,
                    'statut_paiement' => $statutPaiement,
                ]);
                (new AdminModel())->logAction(Auth::id(), 'edit_team', 'Modification de l\'équipe « ' . $nom . ' »', $tournamentId);
                header('Location: index.php?controller=team&action=show&teamId=' . $teamId . '&tournamentId=' . $tournamentId);
                exit;
            }
        }

        $this->render('team/edit', compact('team', 'tournamentId', 'error'));
    }

    /**
     * Supprime une équipe, sauf si elle a déjà un match généré (même pas encore joué) :
     * les matchs déjà planifiés se retrouveraient avec une équipe manquante (voir
     * TeamModel::hasScheduledMatches).
     */
    public function delete(int $teamId = 0, int $tournamentId = 0): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=team&action=index&tournamentId=' . $tournamentId);
            exit;
        }

        $model = new TeamModel();
        $team = $model->find($teamId);

        if ($team && $model->hasScheduledMatches($teamId)) {
            header('Location: index.php?controller=team&action=show&teamId=' . $teamId . '&tournamentId=' . $tournamentId . '&deleteError=already_played');
            exit;
        }

        if ($team) {
            $model->delete($teamId);
            (new AdminModel())->logAction(Auth::id(), 'delete_team', 'Suppression de l\'équipe « ' . $team->nom . ' »', $tournamentId);
        }

        header('Location: index.php?controller=team&action=index&tournamentId=' . $tournamentId);
        exit;
    }

    public function editPayment(int $teamId = 0, int $tournamentId = 0): void
    {
        $model = new TeamModel();
        $team = $model->find($teamId);
        if (!$team) {
            http_response_code(404);
            echo "Équipe introuvable";
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $montantAPayer = (float) $team->montant_a_payer;
            $montantPaye = min($montantAPayer, max(0, (float) ($_POST['montant_paye'] ?? 0)));
            $statutPaiement = Team::calculerStatutPaiement($montantAPayer, $montantPaye);

            $model->updatePayment($teamId, $montantPaye, $statutPaiement);
            header('Location: index.php?controller=team&action=show&teamId=' . $teamId . '&tournamentId=' . $tournamentId);
            exit;
        }

        $this->render('team/editPayment', compact('team', 'tournamentId'));
    }

    public function players(int $teamId = 0, int $tournamentId = 0): void
    {
        $players = (new PlayerModel())->byTeam($teamId);
        $this->render('team/players', compact('players', 'teamId', 'tournamentId'));
    }

    public function addPlayer(int $teamId = 0, int $tournamentId = 0): void
    {
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nom = trim((string) ($_POST['nom'] ?? ''));

            if ($nom === '') {
                $error = "Le nom du joueur est obligatoire.";
            } else {
                (new PlayerModel())->create([
                    'team_id' => $teamId,
                    'prenom' => trim((string) ($_POST['prenom'] ?? '')),
                    'nom' => $nom,
                    'numero_maillot' => trim((string) ($_POST['numero_maillot'] ?? '')) ?: null,
                ]);
                header('Location: index.php?controller=team&action=players&teamId=' . $teamId . '&tournamentId=' . $tournamentId);
                exit;
            }
        }
        $this->render('team/addPlayer', compact('teamId', 'tournamentId', 'error'));
    }
}
