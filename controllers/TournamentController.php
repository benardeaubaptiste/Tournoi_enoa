<?php

namespace tournoi_enoa\Controllers;

use tournoi_enoa\Core\Auth;
use tournoi_enoa\Core\ScheduleCalculator;
use tournoi_enoa\Models\AdminModel;
use tournoi_enoa\Models\BracketModel;
use tournoi_enoa\Models\CourtModel;
use tournoi_enoa\Models\QualificationModel;
use tournoi_enoa\Models\TeamModel;
use tournoi_enoa\Models\TournamentModel;

class TournamentController extends Controller
{
    public function index(): void
    {
        $tournaments = (new TournamentModel())->all();
        $this->render('tournament/index', compact('tournaments'));
    }

    public function add(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $model = new TournamentModel();
            $tournamentId = $model->create([
                'numero' => $model->nextNumero(),
                'titre' => trim($_POST['titre']),
                'date_tournoi' => $_POST['date_tournoi'],
                'date_limite_inscriptions' => $_POST['date_limite_inscriptions'] ?: null,
                'date_limite_paiement' => $_POST['date_limite_paiement'] ?: null,
                'statut' => $_POST['statut'] ?? 'draft',
                'created_by' => Auth::id(),
            ]);
            $model->createSettings($tournamentId, [
                'prix_par_joueur' => (float) $_POST['prix_joueur'],
                'heure_debut_tournoi' => $_POST['heure_debut_tournoi'],
                'heure_fin_tournoi' => $_POST['heure_fin_tournoi'],
            ]);

            // Crée les terrains correspondant au nombre par défaut, sans quoi aucun match
            // ne pourra jamais se voir attribuer de terrain/horaire pour ce tournoi.
            $settings = $model->settings($tournamentId);
            (new CourtModel())->syncForTournament($tournamentId, (int) $settings->nombre_terrains);

            (new AdminModel())->logAction(Auth::id(), 'create_tournament', 'Création du tournoi « ' . trim($_POST['titre']) . ' »', $tournamentId);

            // Enchaîne directement sur les réglages détaillés du tournoi fraîchement créé :
            // sans terrains/poules/horaires réglés, aucune équipe ne peut être planifiée.
            header('Location: index.php?controller=tournament&action=settings&id=' . $tournamentId);
            exit;
        }
        $this->render('tournament/add');
    }

    public function settings(int $id): void
    {
        $model = new TournamentModel();
        $tournament = $model->find($id);
        if (!$tournament) {
            http_response_code(404);
            echo "Tournoi introuvable";
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'heure_debut_tournoi' => $_POST['heure_debut_tournoi'],
                'heure_fin_tournoi' => $_POST['heure_fin_tournoi'],
                'nombre_max_equipes' => (int) $_POST['nombre_max_equipes'],
                'nombre_poules' => (int) $_POST['nombre_poules'],
                'mode_repartition' => $_POST['mode_repartition'],
                'nombre_terrains' => (int) $_POST['nombre_terrains'],
                'temps_entre_matchs_qualification' => (int) $_POST['temps_entre_matchs_qualification'],
                'temps_entre_matchs_phase_finale' => (int) $_POST['temps_entre_matchs_phase_finale'],
                'duree_grande_finale' => (int) $_POST['duree_grande_finale'],
                'prix_par_joueur' => (float) $_POST['prix_par_joueur'],
                'max_matchs_consecutifs_qualifications' => (int) $_POST['max_matchs_consecutifs_qualifications'],
                'max_matchs_consecutifs_phase_finale' => (int) $_POST['max_matchs_consecutifs_phase_finale'],
                'points_victoire' => (float) $_POST['points_victoire'],
                'points_defaite' => (float) $_POST['points_defaite'],
                'nombre_equipes_upper' => (int) $_POST['nombre_equipes_upper'],
                'nombre_equipes_lower' => (int) $_POST['nombre_equipes_lower'],
            ];

            $grandFinaleDuration = $data['duree_grande_finale'];
            $errors = $this->validateSettingsRanges($data);

            // Les heures de qualifications/phase finale et la durée d'un match ne se
            // saisissent plus : elles se déduisent du début/fin du tournoi, au prorata du
            // temps que prend chaque phase (nombre de matchs, terrains, battement).
            $schedule = $this->computeSchedule($data);
            if (!$schedule['qualification']['faisable']) {
                $errors[] = "La fenêtre du tournoi est trop courte pour caser les matchs de qualification (durée de match calculée à 0 min) : élargis l'heure de début/fin du tournoi, ajoute des terrains, réduis le nombre d'équipes/poules, ou réduis le battement entre matchs.";
            }
            if (!$schedule['phase_finale']['faisable'] && $schedule['phase_finale']['nombre_matchs'] > 0) {
                $errors[] = "La fenêtre du tournoi est trop courte pour caser les matchs de la phase finale (durée de match calculée à 0 min) : élargis l'heure de début/fin du tournoi, ajoute des terrains, réduis le nombre d'équipes upper/lower, ou réduis le battement entre matchs.";
            }

            if ($errors) {
                $settings = (object) $data;
                $this->render('tournament/settings', compact('tournament', 'settings', 'schedule', 'grandFinaleDuration', 'errors'));
                return;
            }

            $data['heure_debut_qualifications'] = $schedule['heure_debut_qualifications'];
            $data['heure_fin_qualifications'] = $schedule['heure_fin_qualifications'];
            $data['heure_debut_phase_finale'] = $schedule['heure_debut_phase_finale'];
            $data['heure_fin_phase_finale'] = $schedule['heure_fin_phase_finale'];
            $data['duree_match_qualification'] = $schedule['qualification']['duree_match'];
            $data['duree_match_phase_finale'] = $schedule['phase_finale']['fenetre_minutes'] > 0
                ? $schedule['phase_finale']['duree_match']
                : null;

            $model->updateSettings($id, $data);

            // Fait correspondre les terrains réels au nombre réglé ci-dessus : sans terrain
            // en base, aucun match ne peut se voir attribuer de terrain/horaire.
            (new CourtModel())->syncForTournament($id, (int) $data['nombre_terrains']);

            // Si les matchs ont déjà été générés, leurs créneaux horaires sont recalculés
            // avec les nouveaux réglages (structure des matchs inchangée, seuls les horaires bougent).
            (new QualificationModel())->scheduleQualification($id);
            (new BracketModel())->scheduleFinalPhase($id);

            (new AdminModel())->logAction(Auth::id(), 'update_settings', 'Modification des réglages du tournoi', $id);

            // Premier réglage d'un tournoi tout juste créé (aucune équipe encore inscrite) :
            // enchaîne directement sur l'inscription des équipes plutôt que de renvoyer au
            // tableau de bord, qui n'a encore rien à montrer. Une modification ultérieure des
            // réglages d'un tournoi qui a déjà des équipes ramène au tableau de bord comme avant.
            $hasTeams = (bool) (new TeamModel())->byTournament($id);
            $redirect = $hasTeams
                ? 'index.php?controller=home&action=index&id=' . $id
                : 'index.php?controller=team&action=index&tournamentId=' . $id;

            header('Location: ' . $redirect);
            exit;
        }

        $settings = $model->settings($id);
        $schedule = $settings ? $this->computeSchedule((array) $settings) : null;
        $grandFinaleDuration = $settings ? (int) $settings->duree_grande_finale : ScheduleCalculator::GRAND_FINAL_DURATION_MINUTES;
        $errors = [];
        $this->render('tournament/settings', compact('tournament', 'settings', 'schedule', 'grandFinaleDuration', 'errors'));
    }

    /** Contraintes numériques imposées par la base (table tournament_settings) : mieux vaut les signaler que crasher dessus. */
    private function validateSettingsRanges(array $data): array
    {
        $errors = [];
        if ($data['nombre_max_equipes'] < 8 || $data['nombre_max_equipes'] > 20) {
            $errors[] = "Le nombre max d'équipes doit être compris entre 8 et 20.";
        }
        if ($data['nombre_poules'] < 2 || $data['nombre_poules'] > 4) {
            $errors[] = 'Le nombre de poules doit être compris entre 2 et 4.';
        }
        if ($data['nombre_terrains'] < 1 || $data['nombre_terrains'] > 20) {
            $errors[] = 'Le nombre de terrains doit être compris entre 1 et 20.';
        }
        if ($data['max_matchs_consecutifs_qualifications'] < 1 || $data['max_matchs_consecutifs_phase_finale'] < 1) {
            $errors[] = 'Le nombre de matchs consécutifs maximum doit être au moins 1.';
        }
        if ($data['prix_par_joueur'] < 0) {
            $errors[] = 'Le prix par joueur ne peut pas être négatif.';
        }
        if ($data['temps_entre_matchs_qualification'] < 0 || $data['temps_entre_matchs_phase_finale'] < 0) {
            $errors[] = 'Le battement entre matchs ne peut pas être négatif.';
        }
        if ($data['duree_grande_finale'] < 1) {
            $errors[] = 'La durée de la grande finale doit être au moins 1 min.';
        }
        if ($data['nombre_equipes_upper'] < 0 || $data['nombre_equipes_lower'] < 0) {
            $errors[] = "Le nombre d'équipes upper/lower ne peut pas être négatif.";
        }
        if (($data['nombre_equipes_upper'] + $data['nombre_equipes_lower']) > $data['nombre_max_equipes']) {
            $errors[] = "Le total des équipes upper + lower (" . ($data['nombre_equipes_upper'] + $data['nombre_equipes_lower']) . ") ne peut pas dépasser le nombre max d'équipes du tournoi (" . $data['nombre_max_equipes'] . ").";
        }
        return $errors;
    }

    /**
     * Découpe la journée du tournoi (heure_debut_tournoi -> heure_fin_tournoi) entre
     * qualifications et phase finale, au prorata du nombre de créneaux que prend chaque
     * phase (terrains pris en compte), la grande finale réservant d'abord son créneau, dont
     * la durée est réglable (tournament_settings.duree_grande_finale). Déduit ensuite, pour
     * chaque phase, la durée d'un match à partir de la fenêtre qui lui revient, du nombre de
     * matchs à y jouer et du temps de battement choisi par l'utilisateur.
     */
    private function computeSchedule(array $data): array
    {
        $nombreTerrains = max(1, (int) $data['nombre_terrains']);
        $gapQual = (int) $data['temps_entre_matchs_qualification'];
        $gapFinale = (int) $data['temps_entre_matchs_phase_finale'];
        $grandFinaleDuration = (int) ($data['duree_grande_finale'] ?? ScheduleCalculator::GRAND_FINAL_DURATION_MINUTES);

        $qualMatches = ScheduleCalculator::qualificationMatchCount(
            (int) $data['nombre_max_equipes'],
            (int) $data['nombre_poules']
        );
        $qualCreneaux = $qualMatches > 0 ? (int) ceil($qualMatches / $nombreTerrains) : 0;

        $finaleBreakdown = ScheduleCalculator::finalPhaseMatchCount(
            (int) $data['nombre_equipes_upper'],
            (int) $data['nombre_equipes_lower']
        );
        $finaleMatches = $finaleBreakdown['bracket'];
        $finaleCreneaux = $finaleMatches > 0 ? (int) ceil($finaleMatches / $nombreTerrains) : 0;
        $reserve = $finaleBreakdown['grande_finale']
            ? $grandFinaleDuration + ($finaleMatches > 0 ? $gapFinale : 0)
            : 0;

        $totalWindow = ScheduleCalculator::windowMinutes($data['heure_debut_tournoi'] ?? null, $data['heure_fin_tournoi'] ?? null);
        $split = ScheduleCalculator::splitWindow(max(0, $totalWindow - $reserve), $qualCreneaux, $finaleCreneaux);

        $debutTournoi = $data['heure_debut_tournoi'] ?? null;
        $pivot = $debutTournoi ? (new \DateTimeImmutable($debutTournoi))->modify('+' . $split['qualification'] . ' minutes')->format('H:i:s') : null;

        $qual = ScheduleCalculator::resolveMatchDuration($split['qualification'], $qualMatches, $nombreTerrains, $gapQual);

        // La fenêtre "brute" de la phase finale va du pivot à la fin du tournoi : elle
        // contient à la fois les matchs de bracket et la réserve pour la grande finale.
        $finaleWindow = $totalWindow > 0 ? $split['phase_finale'] + $reserve : 0;
        $finale = ScheduleCalculator::resolveMatchDuration($finaleWindow, $finaleMatches, $nombreTerrains, $gapFinale, $reserve);

        return [
            'heure_debut_qualifications' => $debutTournoi,
            'heure_fin_qualifications'   => $pivot,
            'heure_debut_phase_finale'   => $pivot,
            'heure_fin_phase_finale'     => $data['heure_fin_tournoi'] ?? null,
            'qualification' => [
                'nombre_matchs'   => $qualMatches,
                'fenetre_minutes' => $split['qualification'],
                'creneaux'        => $qual['creneaux'],
                'duree_match'     => $qual['duree_match'],
                'faisable'        => $qual['faisable'],
            ],
            'phase_finale' => [
                'nombre_matchs'   => $finaleMatches,
                'grande_finale'   => $finaleBreakdown['grande_finale'],
                'fenetre_minutes' => $finaleWindow,
                'creneaux'        => $finale['creneaux'],
                'duree_match'     => $finale['duree_match'],
                'faisable'        => $finale['faisable'],
            ],
        ];
    }

    public function delete(int $id): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?controller=tournament&action=index');
            exit;
        }

        $tournament = (new TournamentModel())->find($id);
        (new TournamentModel())->delete($id);
        (new AdminModel())->logAction(Auth::id(), 'delete_tournament', 'Suppression du tournoi « ' . ($tournament->titre ?? $id) . ' »');

        header('Location: index.php?controller=tournament&action=index');
        exit;
    }
}
