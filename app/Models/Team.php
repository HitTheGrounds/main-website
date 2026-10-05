<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_id',
        'team_name',
        'captain_email',
        'captain_phone',
        'locked',
        'approved',
    ];

    protected $casts = [
        'locked' => 'boolean',
        'approved' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function captain(): ?TeamMember
    {
        return $this->members()->where('is_captain', true)->first();
    }

    public function getMaleCount(): int
    {
        return $this->members()->where('gender', 'Male')->count();
    }

    public function getFemaleCount(): int
    {
        return $this->members()->where('gender', 'Female')->count();
    }

    public function meetsMinimumRequirements(): bool
    {
        $maleCount = $this->getMaleCount();
        $femaleCount = $this->getFemaleCount();
        $totalCount = $this->members()->count();

        // Minimum: 8 players (6 male, 2 female)
        return $totalCount === 12 && $maleCount === 9 && $femaleCount === 3;
    }

    public function isValidConfiguration(): bool
    {
        $maleCount = $this->getMaleCount();
        $femaleCount = $this->getFemaleCount();
        $totalCount = $this->members()->count();

        // Must meet minimum requirements
        if (!$this->meetsMinimumRequirements()) {
            return false;
        }

        // Maximum: 12 players (9 male, 3 female)
        // Starting from minimum (6M, 2F), can add up to 3 male and 1 female
        return $totalCount === 12 && $maleCount === 9 && $femaleCount === 3;
    }
    public function groupTeam(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(GroupTeam::class);
    }
}
