<?php

namespace tournoi_enoa\Controllers;

use tournoi_enoa\Core\Standings;
use tournoi_enoa\Models\MatchModel;
use tournoi_enoa\Models\PoolModel;
use tournoi_enoa\Models\RankingModel;
use tournoi_enoa\Models\TeamModel;
use tournoi_enoa\Models\TournamentModel;

abstract class Controller{
    protected $connection;



protected function render(string $path, array $data=[]){

    // Permet d'extraire les données récupérées sous forme de variables
    extract($data);

    // On créer le buffer de sortie
    ob_start();

    // Crée le chemin et inclut le fichier de la vue souhaitée
    include dirname(__DIR__) . '/Views/' . $path . '.php';

    //On vide le buffer dans les variables $title et $content
    $content = ob_get_clean();

    // On fabrique le "template"
    include dirname(__DIR__) . '/Views/base.php';
}

    /**
     * Comme render(), mais renvoie le HTML de la vue seule (sans l'habillage base.php :
     * en-tête, nav, pied de page). Sert au rafraîchissement partiel en JS d'une portion de
     * page déjà affichée (ex. l'arbre de la phase finale après une saisie de score), pour
     * remplacer son contenu sans recharger toute la page ni perdre la position de défilement.
     */
    protected function renderPartial(string $path, array $data = []): string
    {
        extract($data);

        ob_start();
        include dirname(__DIR__) . '/Views/' . $path . '.php';
        return ob_get_clean();
    }

    /**
     * Recalcule les classements de qualification (par poule + général) à partir des
     * réglages de points du tournoi, et les persiste dans `rankings` (table restée
     * inutilisée jusqu'ici) au lieu de les recalculer à la volée à chaque affichage.
     * Partagé entre PoolController (affichage) et MatchController (après une saisie de
     * score) pour ne calculer/écrire qu'à un seul endroit.
     *
     * @return array{pools:array,assignments:array,qualMatches:array,matchesByPool:array,standingsByPool:array,globalStandings:array}
     */
    protected function refreshQualificationRankings(int $tournamentId): array
    {
        $settings = (new TournamentModel())->settings($tournamentId);
        $pointsVictoire = $settings ? (float) $settings->points_victoire : 3.0;
        $pointsDefaite  = $settings ? (float) $settings->points_defaite : 1.0;

        $poolModel = new PoolModel();
        $pools = $poolModel->byTournament($tournamentId);
        $assignments = $poolModel->teamAssignments($tournamentId);
        $qualMatches = (new MatchModel())->qualificationByTournament($tournamentId);

        // Une équipe déclarée forfait ne doit plus apparaître dans les classements, même si
        // elle a déjà joué des matchs de qualification avant son forfait (les résultats de
        // ses adversaires restent comptés, seule sa propre ligne disparaît du classement).
        $forfaitTeamIds = $this->forfaitTeamIds($tournamentId);

        $matchesByPool = [];
        foreach ($qualMatches as $match) {
            $matchesByPool[(int) $match->resolved_pool_id][] = $match;
        }

        $rankingModel = new RankingModel();
        $standingsByPool = [];
        $qualifiedTeamNames = [];
        foreach ($pools as $pool) {
            $poolTeamNames = [];
            foreach ($assignments[$pool->id] ?? [] as $slot) {
                $poolTeamNames[$slot['team_id']] = $slot['nom'];
            }
            $rows = Standings::compute($matchesByPool[$pool->id] ?? [], $poolTeamNames, $pointsVictoire, $pointsDefaite);
            $rows = Standings::excludeTeams($rows, $forfaitTeamIds);
            $standingsByPool[$pool->id] = $rows;
            $rankingModel->saveStandings($tournamentId, (int) $pool->id, 'qualification', $rows);
            $qualifiedTeamNames += $poolTeamNames;
        }

        // Seules les équipes réellement affectées à une poule comptent dans le classement général
        // (une équipe inscrite mais pas encore placée ne joue aucun match de qualification).
        $globalStandings = Standings::compute($qualMatches, $qualifiedTeamNames, $pointsVictoire, $pointsDefaite);
        $globalStandings = Standings::excludeTeams($globalStandings, $forfaitTeamIds);
        $rankingModel->saveStandings($tournamentId, null, 'global', $globalStandings);

        return compact('pools', 'assignments', 'qualMatches', 'matchesByPool', 'standingsByPool', 'globalStandings');
    }

    /**
     * Une fois la grande finale jouée, établit et persiste le classement final complet
     * (phase 'final' de `rankings`) : champion/finaliste d'après la grande finale, puis
     * les autres équipes de la phase finale départagées par leur tour d'élimination
     * (upper/lower bracket confondus) et, à égalité, par leur classement de qualification.
     * Sans effet si la grande finale n'a pas encore de résultat.
     */
    protected function refreshFinalRanking(int $tournamentId): void
    {
        $matches = (new MatchModel())->finalPhaseByTournament($tournamentId);

        $grandFinal = null;
        foreach ($matches as $match) {
            if ($match->phase === 'grand_final') {
                $grandFinal = $match;
            }
        }
        if ($grandFinal === null || $grandFinal->status !== 'finished' || !$grandFinal->winner_team_id) {
            return;
        }

        $championId = (int) $grandFinal->winner_team_id;
        $runnerUpId = (int) ((int) $grandFinal->team_a_id === $championId ? $grandFinal->team_b_id : $grandFinal->team_a_id);

        // Tour d'élimination de chaque équipe de la phase finale (upper/lower confondus) :
        // le plus grand round_number où elle est encore apparue comme team_a/team_b.
        $eliminationRound = [];
        foreach ($matches as $match) {
            if ($match->phase === 'grand_final') {
                continue;
            }
            foreach (['team_a', 'team_b'] as $side) {
                $teamId = $match->{$side . '_id'};
                if ($teamId === null) {
                    continue;
                }
                $teamId = (int) $teamId;
                $round = (int) $match->round_number;
                if (!isset($eliminationRound[$teamId]) || $round > $eliminationRound[$teamId]) {
                    $eliminationRound[$teamId] = $round;
                }
            }
        }
        unset($eliminationRound[$championId], $eliminationRound[$runnerUpId]);

        // Départage à tour d'élimination égal : le classement général de qualification déjà persisté.
        $qualPosition = [];
        foreach ((new RankingModel())->forPhase($tournamentId, null, 'global') as $row) {
            $qualPosition[(int) $row->team_id] = (int) $row->position;
        }

        $others = array_keys($eliminationRound);
        usort($others, static function (int $a, int $b) use ($eliminationRound, $qualPosition): int {
            return $eliminationRound[$b] <=> $eliminationRound[$a]
                ?: ($qualPosition[$a] ?? PHP_INT_MAX) <=> ($qualPosition[$b] ?? PHP_INT_MAX);
        });

        $rows = [];
        foreach (array_merge([$championId, $runnerUpId], $others) as $index => $teamId) {
            $rows[] = [
                'team_id'     => $teamId,
                'position'    => $index + 1,
                'joues'       => 0,
                'victoires'   => 0,
                'defaites'    => 0,
                'sets_pour'   => 0,
                'sets_contre' => 0,
                'diff_sets'   => 0,
                'points'      => 0,
            ];
        }

        (new RankingModel())->saveStandings($tournamentId, null, 'final', $rows);
    }

    /**
     * Identifiants des équipes déclarées forfait pour ce tournoi (teams.statut = 'forfait').
     * Sert à les exclure des classements et de la phase finale à tout moment du tournoi, pas
     * seulement au moment de la composition initiale des poules (PoolController::autoGenerate) :
     * un forfait déclaré après coup doit aussi disparaître des classements et ne jamais être
     * placé dans le tableau de la phase finale.
     *
     * @return int[]
     */
    protected function forfaitTeamIds(int $tournamentId): array
    {
        $teams = (new TeamModel())->byTournament($tournamentId);
        return array_values(array_map(
            static fn ($team) => (int) $team->id,
            array_filter($teams, static fn ($team) => $team->statut === 'forfait')
        ));
    }

}

