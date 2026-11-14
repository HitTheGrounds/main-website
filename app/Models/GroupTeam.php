<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupTeam extends Model
{
    // Includes derived standings and committee adjustments; never fill from a request.
    protected $guarded = ['*'];

    protected $hidden = ['point_adjustments'];

    protected function casts(): array
    {
        return [
            'tournament_year' => 'integer',
            'group_id' => 'integer',
            'team_id' => 'integer',
            'played' => 'integer',
            'wins' => 'integer',
            'losses' => 'integer',
            'ties' => 'integer',
            'no_results' => 'integer',
            'base_points' => 'integer',
            'point_adjustments' => 'array',
            'points' => 'integer',
            'runs_for' => 'integer',
            'runs_against' => 'integer',
            'nrr_overs_faced' => 'decimal:8',
            'nrr_overs_bowled' => 'decimal:8',
            'nrr' => 'decimal:6',
            'qualified' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TournamentGroup::class, 'group_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
