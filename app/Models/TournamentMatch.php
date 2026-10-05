<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'stage',
        'status',
        'group_id',
        'bracket_position',
        'team1_id',
        'team2_id',
        'batting_first_id',
        'team1_score',
        'team1_overs',
        'team1_balls',
        'team1_wickets',
        'team2_score',
        'team2_overs',
        'team2_balls',
        'team2_wickets',
        'is_draw',
        'winner_id',
        'entered_by',
    ];

    protected $casts = [
        'is_draw' => 'boolean',
    ];

    protected static function booted()
    {
        static::saving(function (TournamentMatch $match) {
            if ($match->is_draw && $match->winner_id !== null) {
                throw new \Exception('A drawn match cannot have a winner.');
            }
        });
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

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TournamentGroup::class, 'group_id');
    }

    public function scopeFinished(Builder $query): void
    {
        $query->where('status', 'finished');
    }

    public function scopeLive(Builder $query): void
    {
        $query->where('status', 'live');
    }

    public function scopeUpcoming(Builder $query): void
    {
        $query->where('status', 'upcoming');
    }

    public function scopeForStage(Builder $query, string $stage): void
    {
        $query->where('stage', $stage);
    }
}
