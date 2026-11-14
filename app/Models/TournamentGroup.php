<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentGroup extends Model
{
    // Tournament services must assign validated fields explicitly.
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'tournament_year' => 'integer',
            'version' => 'integer',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(GroupTeam::class, 'group_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'group_id');
    }
}
