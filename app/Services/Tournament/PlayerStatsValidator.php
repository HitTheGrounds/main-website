<?php

namespace App\Services\Tournament;

use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;

class PlayerStatsValidator
{
    public const METRICS = ['runs_scored', 'balls_faced', 'fours', 'sixes', 'bowling_balls', 'runs_conceded', 'wickets_taken', 'dot_balls'];

    /** Validate one complete replacement of the figures, including unknowns. */
    public function validate(array $input): array
    {
        try {
            $size = strlen(json_encode($input, JSON_THROW_ON_ERROR, 8));
        } catch (JsonException) {
            $this->reject('player', 'The player figures contain invalid data.');
        }
        if ($size > 4096 || array_diff(array_keys($input), [...self::METRICS, 'batting_status'])) {
            $this->reject('player', 'Player figures contain unsupported fields or are too large.');
        }
        $input = array_replace(array_fill_keys([...self::METRICS, 'batting_status'], null), $input);
        foreach ($input as &$value) {
            $value = $value === '' ? null : $value;
        }
        unset($value);
        $integer = function (string $field, mixed $value, Closure $fail): void {
            // HTML number controls submit decimal digit strings. Never coerce
            // booleans, fractional floats, exponent notation or arbitrary text.
            if (! is_int($value) && (! is_string($value) || ! preg_match('/\A(?:0|[1-9][0-9]*)\z/', $value))) {
                $fail('Enter a whole nonnegative number or leave this field blank.');
            }
        };
        $rules = [];
        foreach (self::METRICS as $field) {
            $rules[$field] = ['nullable', $integer, 'integer', 'between:0,'.($field === 'wickets_taken' ? 11 : 2147483647)];
        }
        $rules['batting_status'] = ['nullable', Rule::in(['out', 'not_out', 'did_not_bat', 'retired_hurt', 'retired_out'])];
        $data = Validator::make($input, $rules)->validate();
        foreach (self::METRICS as $field) {
            if ($data[$field] !== null) {
                $data[$field] = (int) $data[$field];
            }
        }
        if ($data['dot_balls'] !== null && $data['bowling_balls'] !== null && $data['dot_balls'] > $data['bowling_balls']) {
            $this->reject('dot_balls', 'Dot balls cannot exceed recorded bowling deliveries.');
        }
        if ($data['runs_scored'] !== null && $data['fours'] !== null && $data['sixes'] !== null
            && 4 * $data['fours'] + 6 * $data['sixes'] > $data['runs_scored']) {
            $this->reject('runs_scored', 'Recorded boundary runs cannot exceed the player runs.');
        }

        return $data;
    }

    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
