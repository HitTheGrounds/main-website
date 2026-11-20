<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerTournamentStat extends Model
{
    public const CREATED_AT = null;

    public const BOWLING_OVER_UNITS = 12;

    // Derived only from eligible published rows by the totals service.
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'tournament_year' => 'integer',
            'team_member_id' => 'integer',
            'team_id' => 'integer',
            'runs_scored' => 'integer',
            'runs_scored_recorded_count' => 'integer',
            'balls_faced' => 'integer',
            'balls_faced_recorded_count' => 'integer',
            'fours' => 'integer',
            'fours_recorded_count' => 'integer',
            'sixes' => 'integer',
            'sixes_recorded_count' => 'integer',
            'bowling_balls' => 'integer',
            'bowling_balls_recorded_count' => 'integer',
            'bowling_overs_units' => 'integer',
            'strike_rate_complete' => 'boolean',
            'economy_complete' => 'boolean',
            'runs_conceded' => 'integer',
            'runs_conceded_recorded_count' => 'integer',
            'wickets_taken' => 'integer',
            'wickets_taken_recorded_count' => 'integer',
            'dot_balls' => 'integer',
            'dot_balls_recorded_count' => 'integer',
            'highest_score' => 'integer',
            'highest_score_match_id' => 'integer',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    public function highestScoreMatch(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class, 'highest_score_match_id');
    }
}
