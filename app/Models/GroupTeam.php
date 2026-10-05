<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'team_id',
        'matches_played',
        'wins',
        'losses',
        'draws',
        'points',
        'total_balls_faced',
        'total_runs_scored',
        'total_balls_bowled',
        'total_runs_conceded',
        'nrr',
        'qualified',
    ];

    protected $casts = [
        'qualified' => 'boolean',
        'nrr' => 'decimal:4',
    ];

    /**
     * Get the group this record belongs to.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(TournamentGroup::class, 'group_id');
    }

    /**
     * Get the team this record belongs to.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
