<?php

namespace App\Models;

use App\Services\Tournament\RosterProtectionService;
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

    public function save(array $options = [])
    {
        // Existing forms do not move players. Guard model-based reassignment too,
        // without changing ordinary registration, name, gender, or picture edits.
        if (! $this->exists || ! $this->isDirty('team_id')) {
            return parent::save($options);
        }

        return $this->getConnection()->transaction(function () use ($options) {
            $member = static::whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            app(RosterProtectionService::class)->assertMemberUnreferenced($member);

            return parent::save($options);
        });
    }

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
