<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TournamentGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Get the group teams for this group.
     */
    public function groupTeams(): HasMany
    {
        return $this->hasMany(GroupTeam::class, 'group_id');
    }

    /**
     * Get the teams in this group.
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'group_teams', 'group_id', 'team_id')
                    ->withPivot(['matches_played', 'wins', 'losses', 'draws', 'points', 'nrr', 'qualified'])
                    ->withTimestamps();
    }
}
