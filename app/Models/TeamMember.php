<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeamMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'gender',
        'picture',
        'is_captain',
    ];

    protected $casts = [
        'is_captain' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function playerMatchStats(): HasMany
    {
        return $this->hasMany(PlayerMatchStat::class);
    }

    public function playerTournamentStats(): HasMany
    {
        return $this->hasMany(PlayerTournamentStat::class);
    }
}
