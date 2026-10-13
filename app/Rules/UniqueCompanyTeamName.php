<?php

namespace App\Rules;

use App\Models\Team;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueCompanyTeamName implements ValidationRule
{
    public function __construct(private ?int $companyId, private ?int $ignoreTeamId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $query = Team::query()
            ->where('company_id', $this->companyId)
            ->whereRaw('LOWER(TRIM(team_name)) = ?', [strtolower(trim($value))]);

        if ($this->ignoreTeamId !== null) {
            $query->where('id', '!=', $this->ignoreTeamId);
        }

        if ($query->exists()) {
            $fail('A team with this name already exists in your company.');
        }
    }
}
