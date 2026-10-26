<?php 

namespace tournoi_enoa\Controllers;

use tournoi_enoa\Models\TournamentModel;

class HomeController extends Controller{

    public function index(): void{
        $model = new TournamentModel();
        $tournaments = $model->all();
        $selectedId = (int) ($_GET['id'] ?? ($tournaments[0]->id ?? 0));
        $dashboard = $selectedId ? $model->dashboard($selectedId) : null;
        $nextStep = $dashboard ? $this->nextStep($dashboard) : null;
        $this->render('home/index', compact('tournaments', 'dashboard', 'nextStep'));
    }

    /**
     * Prochaine action à mener pour faire avancer le tournoi, déduite de son statut
     * (tournaments.statut, cycle de vie tenu à jour par TournamentModel::advanceStatut).
     * Renvoie null quand le tournoi est terminé/archivé : il n'y a plus d'étape à pousser.
     */
    private function nextStep(object $dashboard): ?array
    {
        $id = (int) $dashboard->id;

        switch ($dashboard->statut) {
            case 'draft':
            case 'inscriptions':
                return ((int) $dashboard->nombre_equipes) < 1
                    ? ['label' => 'Étape suivante : inscrire des équipes', 'href' => 'index.php?controller=team&action=add&tournamentId=' . $id]
                    : ['label' => 'Étape suivante : générer les poules', 'href' => 'index.php?controller=pool&action=index&tournamentId=' . $id];
            case 'poules_a_generer':
            case 'poules_validees':
                return ['label' => 'Étape suivante : générer les matchs de qualification', 'href' => 'index.php?controller=pool&action=index&tournamentId=' . $id];
            case 'planning_genere':
            case 'en_cours':
                return ['label' => 'Étape suivante : suivre les matchs', 'href' => 'index.php?controller=match&action=index&tournamentId=' . $id];
            case 'phase_finale':
                return ['label' => 'Étape suivante : voir le tableau final', 'href' => 'index.php?controller=match&action=bracket&tournamentId=' . $id];
            default:
                return null;
        }
    }
}
