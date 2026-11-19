<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerMatchStat extends Model
{
    // Services validate player identity, figures, and draft/publication transitions.
    protected $guarded = ['*'];

    protected $hidden = ['pending_action', 'updated_by', 'published_by'];

    protected function casts(): array
    {
        return [
            'match_id' => 'integer',
            'team_id' => 'integer',
            'team_member_id' => 'integer',
            'runs_scored' => 'integer',
            'balls_faced' => 'integer',
            'fours' => 'integer',
            'sixes' => 'integer',
            'bowling_balls' => 'integer',
            'runs_conceded' => 'integer',
            'wickets_taken' => 'integer',
            'dot_balls' => 'integer',
            'published_at' => 'datetime',
            'version' => 'integer',
            'updated_by' => 'integer',
            'published_by' => 'integer',
        ];
    }

    public function tournamentMatch(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class, 'match_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
