<?php

namespace App\Services\Tournament;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeliveryCount
{
    public function fromParts(mixed $overs, mixed $balls, int $ballsPerOver, int $oversPerInnings = 5): int
    {
        $data = Validator::make(compact('overs', 'balls', 'ballsPerOver', 'oversPerInnings'), [
            'ballsPerOver' => ['required', Rule::in([4, 6])],
            'oversPerInnings' => ['required', Rule::in([5])],
            'overs' => ['required', 'integer', 'between:0,5'],
            'balls' => ['required', 'integer', 'min:0', 'max:'.($ballsPerOver - 1)],
        ])->validate();
        if ((int) $data['overs'] === $oversPerInnings && (int) $data['balls'] !== 0) {
            throw ValidationException::withMessages(['balls' => 'The innings cannot exceed its recorded over allocation.']);
        }

        return (int) $data['overs'] * $ballsPerOver + (int) $data['balls'];
    }
}
