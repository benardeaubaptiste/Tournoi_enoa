<?php

namespace tournoi_enoa\Controllers;

use Dompdf\Dompdf;
use Dompdf\Options;
use tournoi_enoa\Models\MatchModel;
use tournoi_enoa\Models\PlayerModel;
use tournoi_enoa\Models\RankingModel;
use tournoi_enoa\Models\TeamModel;
use tournoi_enoa\Models\TournamentModel;

/**
 * Génère des documents PDF imprimables (liste des équipes, planning, classements, arbre
 * de la phase finale, ou un bilan complet réunissant les quatre) à partir des mêmes
 * données que l'affichage web, via Dompdf.
 */
class ExportController extends Controller
{
    public function teams(int $tournamentId = 0): void
    {
        $tournament = (new TournamentModel())->find($tournamentId);
        [$teams, $playersByTeam] = $this->teamsData($tournamentId);

        $html = $this->renderPartial('pdf/teams', compact('tournament', 'teams', 'playersByTeam'));
        $this->streamPdf($html, 'equipes-tournoi-' . $tournamentId . '.pdf');
    }

    public function planning(int $tournamentId = 0): void
    {
        $tournament = (new TournamentModel())->find($tournamentId);
        $planningMatches = (new MatchModel())->byTournament($tournamentId);

        $html = $this->renderPartial('pdf/planning', compact('tournament', 'planningMatches'));
        $this->streamPdf($html, 'planning-tournoi-' . $tournamentId . '.pdf');
    }

    public function standings(int $tournamentId = 0): void
    {
        $tournament = (new TournamentModel())->find($tournamentId);
        [$pools, $standingsByPool, $globalStandings, $finalRanking] = $this->standingsData($tournamentId);

        $html = $this->renderPartial('pdf/standings', compact('tournament', 'pools', 'standingsByPool', 'globalStandings', 'finalRanking'));
        $this->streamPdf($html, 'classements-tournoi-' . $tournamentId . '.pdf');
    }

    public function bracket(int $tournamentId = 0): void
    {
        $tournament = (new TournamentModel())->find($tournamentId);
        [$bracketMatches, $finalRanking] = $this->bracketData($tournamentId);

        $html = $this->renderPartial('pdf/bracket', compact('tournament', 'bracketMatches', 'finalRanking'));
        $this->streamPdf($html, 'arbre-phase-finale-tournoi-' . $tournamentId . '.pdf');
    }

    /** Bilan complet : équipes + planning + classements + arbre de la phase finale, en un seul PDF. */
    public function full(int $tournamentId = 0): void
    {
        $tournament = (new TournamentModel())->find($tournamentId);
        [$teams, $playersByTeam] = $this->teamsData($tournamentId);
        $planningMatches = (new MatchModel())->byTournament($tournamentId);
        [$pools, $standingsByPool, $globalStandings, $finalRanking] = $this->standingsData($tournamentId);
        [$bracketMatches] = $this->bracketData($tournamentId);

        $html = $this->renderPartial('pdf/full', compact(
            'tournament',
            'teams',
            'playersByTeam',
            'planningMatches',
            'pools',
            'standingsByPool',
            'globalStandings',
            'bracketMatches',
            'finalRanking'
        ));
        $this->streamPdf($html, 'bilan-tournoi-' . $tournamentId . '.pdf');
    }

    private function teamsData(int $tournamentId): array
    {
        $teams = (new TeamModel())->byTournament($tournamentId);
        $playerModel = new PlayerModel();
        $playersByTeam = [];
        foreach ($teams as $team) {
            $playersByTeam[$team->id] = $playerModel->byTeam((int) $team->id);
        }
        return [$teams, $playersByTeam];
    }

    private function standingsData(int $tournamentId): array
    {
        $ranking = $this->refreshQualificationRankings($tournamentId);
        $finalRanking = (new RankingModel())->forPhase($tournamentId, null, 'final');
        return [$ranking['pools'], $ranking['standingsByPool'], $ranking['globalStandings'], $finalRanking];
    }

    private function bracketData(int $tournamentId): array
    {
        $bracketMatches = (new MatchModel())->finalPhaseByTournament($tournamentId);
        $finalRanking = (new RankingModel())->forPhase($tournamentId, null, 'final');
        return [$bracketMatches, $finalRanking];
    }

    private function streamPdf(string $html, string $filename): void
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream($filename, ['Attachment' => true]);
        exit;
    }
}
