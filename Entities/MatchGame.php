<?php

namespace tournoi_enoa\Entities;

class MatchGame
{
    public $id;
    public $tournament_id;
    public $phase;
    public $pool_id;
    public $round_name;
    public $round_number;
    public $match_number;
    public $team_a_id;
    public $team_b_id;
    public $winner_team_id;
    public $loser_team_id;
    public $court_id;
    public $scheduled_start;
    public $scheduled_end;
    public $duration_seconds;
    public $started_at;
    public $ended_at;
    public $status;
    public $score_team_a;
    public $score_team_b;
    public $next_match_id;
    public $next_match_slot;
    public $loser_next_match_id;
    public $loser_next_match_slot;
    public $created_at;
    public $updated_at;
    public $team_a_name;
    public $team_b_name;
    public $court_name;
    public $resolved_pool_id;
}
