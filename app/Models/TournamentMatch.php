<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentMatch extends Model
{
    protected $table = 'matches';

    // Publication and structural changes belong in authorized tournament services.
    protected $guarded = ['*'];

    protected $hidden = ['draft_result', 'nrr_inputs', 'created_by', 'updated_by', 'published_by'];

    protected function casts(): array
    {
        return [
            'tournament_year' => 'integer',
            'display_number' => 'integer',
            'group_id' => 'integer',
            'bracket_position' => 'integer',
            'team1_id' => 'integer',
            'team2_id' => 'integer',
            'batting_first_id' => 'integer',
            'balls_per_over' => 'integer',
            'overs_per_innings' => 'integer',
            'max_wickets' => 'integer',
            'scheduled_at' => 'datetime',
            'team1_runs' => 'integer',
            'team1_wickets' => 'integer',
            'team1_deliveries' => 'integer',
            'team2_runs' => 'integer',
            'team2_wickets' => 'integer',
            'team2_deliveries' => 'integer',
            'winner_id' => 'integer',
            'revised_target' => 'integer',
            'nrr_inputs' => 'array',
            'draft_result' => 'array',
            'result_published_at' => 'datetime',
            'structure_locked_at' => 'datetime',
            'player_stats_published_at' => 'datetime',
            'player_stats_revision' => 'integer',
            'version' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'published_by' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TournamentGroup::class, 'group_id');
    }

    public function team1(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team1_id');
    }

    public function team2(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team2_id');
    }

    public function battingFirst(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'batting_first_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_id');
    }

    public function playerMatchStats(): HasMany
    {
        return $this->hasMany(PlayerMatchStat::class, 'match_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
