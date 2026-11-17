<?php

namespace App\Services\Tournament;

use App\Models\TournamentMatch;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;

class MatchResultValidator
{
    public function __construct(private DeliveryCount $deliveries, private KnockoutIntegrity $knockouts) {}

    /** Validate a replacement form draft, allowing incomplete result details. */
    public function validateDraft(TournamentMatch $match, array $input): array
    {
        $data = $this->validatedInput($match, $input, false);
        if ($data === []) {
            $this->reject('tournament', 'Enter at least one result detail before saving a draft.');
        }
        if (isset($data['nrr_inputs'])) {
            $rules = [
                'nrr_inputs.included' => ['nullable', 'boolean'],
                'nrr_inputs.reason' => ['nullable', 'string', 'max:1000'],
            ];
            foreach (['team1', 'team2'] as $team) {
                $prefix = 'nrr_inputs.'.$team;
                $rules[$prefix] = ['nullable', 'array:runs,overs,balls'];
                $rules[$prefix.'.runs'] = ['nullable', 'integer', 'between:0,2147483647'];
                $rules[$prefix.'.overs'] = ['nullable', 'integer', 'between:0,5'];
                $rules[$prefix.'.balls'] = ['nullable', 'integer', 'min:0', 'max:'.($match->balls_per_over - 1)];
            }
            Validator::make($data, $rules)->validate();
            foreach (['team1', 'team2'] as $team) {
                foreach (['runs', 'overs', 'balls'] as $field) {
                    if (isset($data['nrr_inputs'][$team][$field])) {
                        $data['nrr_inputs'][$team][$field] = (int) $data['nrr_inputs'][$team][$field];
                    }
                }
                $parts = $data['nrr_inputs'][$team] ?? [];
                if (isset($parts['overs'], $parts['balls'])) {
                    $this->count($match, $parts['overs'], $parts['balls'], 'nrr_inputs.'.$team.'.');
                }
            }
            if (isset($data['nrr_inputs']['included'])) {
                $data['nrr_inputs']['included'] = (bool) $data['nrr_inputs']['included'];
            }
        }
        foreach (['winner_id', 'batting_first_id', 'revised_target'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = (int) $data[$field];
            }
        }
        foreach (['team1', 'team2'] as $team) {
            foreach (['runs', 'wickets', 'overs', 'balls'] as $field) {
                if (isset($data[$team.'_'.$field])) {
                    $data[$team.'_'.$field] = (int) $data[$team.'_'.$field];
                }
            }
            if (isset($data[$team.'_overs'], $data[$team.'_balls'])) {
                $this->count($match, $data[$team.'_overs'], $data[$team.'_balls'], $team.'_');
            }
        }
        if (array_key_exists('batting_first_id', $data)
            && ($match->structure_locked_at !== null || $match->result_published_at !== null)
            && $data['batting_first_id'] !== $match->batting_first_id) {
            $this->reject('batting_first_id', 'The batting-first team is locked after result publication.');
        }

        return $data;
    }

    /** Validate a complete result proposal. No writes, authorization or publication. */
    public function validate(TournamentMatch $match, array $input): array
    {
        $data = $this->validatedInput($match, $input, true);
        $type = $data['result_type'];
        $method = $data['result_method'] ?? null;
        $winner = isset($data['winner_id']) ? (int) $data['winner_id'] : null;
        $batting = array_key_exists('batting_first_id', $data) ? $data['batting_first_id'] : $match->batting_first_id;
        $batting = $batting === null ? null : (int) $batting;
        if (($match->structure_locked_at !== null || $match->result_published_at !== null) && $batting !== $match->batting_first_id) {
            $this->reject('batting_first_id', 'The batting-first team is locked after result publication.');
        }
        $note = isset($data['result_note']) ? trim($data['result_note']) : null;
        $note = $note === '' ? null : $note;
        $undecided = in_array($type, ['no_result', 'abandoned'], true);
        if ($undecided) {
            if ($match->stage !== 'G' || $winner !== null || $method !== null || isset($data['revised_target']) || isset($data['nrr_inputs'])) {
                $this->reject('result_type', 'A finished no-result or abandoned match must be a group match without a winner, deciding method or NRR override.');
            }
        } else {
            if ($method === null) {
                $this->reject('result_method', 'Select the official deciding method.');
            }
            if ($type === 'win' && $winner === null) {
                $this->reject('winner_id', 'Select the winning team.');
            }
            if ($type === 'tie' && ($match->stage !== 'G' || $winner !== null || $method !== 'normal')) {
                $this->reject('result_type', 'Only a group match can finish as a tie, without a winner or extra deciding method.');
            }
            if ($method !== 'normal' && $note === null) {
                $this->reject('result_note', 'Explain the official deciding method.');
            }
        }
        if ($method !== 'revised_target' && (isset($data['revised_target']) || isset($data['nrr_inputs']))) {
            $this->reject('nrr_inputs', 'Adjusted NRR inputs and revised targets belong only to a revised-target result.');
        }
        $result = [
            'result_type' => $type, 'result_method' => $method, 'winner_id' => $winner,
            'batting_first_id' => $batting, 'result_note' => $note,
            'revised_target' => isset($data['revised_target']) ? (int) $data['revised_target'] : null,
            'nrr_inputs' => null,
        ];
        $completeInnings = in_array($method, ['normal', 'super_over'], true);
        foreach (['team1', 'team2'] as $team) {
            foreach (['runs', 'wickets', 'overs', 'balls'] as $field) {
                if ($completeInnings && ! isset($data[$team.'_'.$field])) {
                    $this->reject($team.'_'.$field, 'Enter both official innings for this result.');
                }
            }
            $result[$team.'_runs'] = isset($data[$team.'_runs']) ? (int) $data[$team.'_runs'] : null;
            $result[$team.'_wickets'] = isset($data[$team.'_wickets']) ? (int) $data[$team.'_wickets'] : null;
            $overs = $data[$team.'_overs'] ?? null;
            $balls = $data[$team.'_balls'] ?? null;
            if (($overs === null) !== ($balls === null)) {
                $this->reject($team.'_overs', 'Enter both complete overs and balls in the current over, or leave both unknown.');
            }
            $result[$team.'_deliveries'] = $overs === null ? null : $this->count($match, $overs, $balls, $team.'_');
        }
        if ($completeInnings) {
            $a = $result['team1_runs'];
            $b = $result['team2_runs'];
            if (($type === 'tie' || $method === 'super_over') && $a !== $b) {
                $this->reject('result_type', 'A tie or Super Over decision requires equal normal-innings scores.');
            }
            if ($type === 'win' && $method === 'normal') {
                $higher = $a > $b ? $match->team1_id : ($b > $a ? $match->team2_id : null);
                if ($winner !== $higher) {
                    $this->reject('winner_id', 'The normal-play winner must have the higher score.');
                }
                if ($winner !== $batting && $batting !== null) {
                    $key = $winner === $match->team1_id ? 'team1_wickets' : 'team2_wickets';
                    if ($result[$key] === $match->max_wickets) {
                        $this->reject($key, 'A normal successful chase cannot finish with all wickets lost.');
                    }
                }
            }
        }
        if ($method === 'revised_target') {
            if ($type !== 'win' || ! isset($data['revised_target'])) {
                $this->reject('revised_target', 'Enter the official revised target for a decided result.');
            }
            $result['nrr_inputs'] = $this->nrr($match, $data['nrr_inputs'] ?? []);
        }

        $this->knockouts->assertFinishedResult($match, $result);

        return $result;
    }

    public function officialForm(TournamentMatch $match): array
    {
        $form = $match->only(['result_type', 'result_method', 'winner_id', 'batting_first_id', 'result_note', 'revised_target', 'nrr_inputs']);
        foreach (['team1', 'team2'] as $team) {
            $count = $match->{$team.'_deliveries'};
            $form[$team.'_runs'] = $match->{$team.'_runs'};
            $form[$team.'_wickets'] = $match->{$team.'_wickets'};
            $form[$team.'_overs'] = $count === null ? null : intdiv($count, $match->balls_per_over);
            $form[$team.'_balls'] = $count === null ? null : $count % $match->balls_per_over;
        }
        if (is_array($form['nrr_inputs'])) {
            foreach (['team1', 'team2'] as $team) {
                if (isset($form['nrr_inputs'][$team]['deliveries'])) {
                    $count = $form['nrr_inputs'][$team]['deliveries'];
                    $form['nrr_inputs'][$team]['overs'] = intdiv($count, $match->balls_per_over);
                    $form['nrr_inputs'][$team]['balls'] = $count % $match->balls_per_over;
                    unset($form['nrr_inputs'][$team]['deliveries']);
                }
            }
        }

        return $form;
    }

    private function validatedInput(TournamentMatch $match, array $input, bool $complete): array
    {
        try {
            $size = strlen(json_encode($input, JSON_THROW_ON_ERROR, 16));
        } catch (JsonException) {
            $this->reject('tournament', 'The result contains invalid data.');
        }
        if ($size > 8192) {
            $this->reject('tournament', 'The result is too large. Shorten the result explanation.');
        }
        $allowed = ['result_type', 'result_method', 'winner_id', 'batting_first_id', 'result_note', 'revised_target', 'nrr_inputs'];
        foreach (['team1', 'team2'] as $team) {
            foreach (['runs', 'wickets', 'overs', 'balls'] as $field) {
                $allowed[] = $team.'_'.$field;
            }
        }
        if (array_diff(array_keys($input), $allowed)) {
            $this->reject('tournament', 'The result contains unsupported fields.');
        }
        Validator::make($match->only(['stage', 'team1_id', 'team2_id', 'balls_per_over', 'overs_per_innings', 'max_wickets']), [
            'stage' => ['required', Rule::in(['G', 'QF', 'SF', 'F'])],
            'team1_id' => ['required', 'integer', 'min:1'], 'team2_id' => ['required', 'integer', 'min:1', 'different:team1_id'],
            'balls_per_over' => ['required', Rule::in([4, 6])], 'overs_per_innings' => ['required', Rule::in([5])], 'max_wickets' => ['required', Rule::in([7])],
        ])->validate();
        $rules = [
            'result_type' => [$complete ? 'required' : 'nullable', Rule::in(['win', 'tie', 'no_result', 'abandoned'])],
            'result_method' => ['nullable', Rule::in(['normal', 'super_over', 'coin_toss', 'revised_target', 'official_decision'])],
            'winner_id' => ['nullable', 'integer', Rule::in([$match->team1_id, $match->team2_id])],
            'batting_first_id' => ['nullable', 'integer', Rule::in([$match->team1_id, $match->team2_id])],
            'result_note' => ['nullable', 'string', 'max:2000'],
            'revised_target' => ['nullable', 'integer', 'between:1,2147483647'],
            'nrr_inputs' => ['nullable', 'array:included,reason,team1,team2'],
        ];
        foreach (['team1', 'team2'] as $team) {
            $rules[$team.'_runs'] = ['nullable', 'integer', 'between:0,2147483647'];
            $rules[$team.'_wickets'] = ['nullable', 'integer', 'between:0,7'];
            $rules[$team.'_overs'] = ['nullable', 'integer', 'between:0,5'];
            $rules[$team.'_balls'] = ['nullable', 'integer', 'min:0', 'max:'.($match->balls_per_over - 1)];
        }

        return Validator::make($input, $rules)->validate();
    }

    private function nrr(TournamentMatch $match, array $input): array
    {
        $rules = ['included' => ['required', 'boolean'], 'reason' => ['required', 'string', 'max:1000'],
            'team1' => ['nullable', 'array:runs,overs,balls'], 'team2' => ['nullable', 'array:runs,overs,balls']];
        foreach (['team1', 'team2'] as $team) {
            $rules[$team.'.runs'] = ['required_if:included,1,true', 'nullable', 'integer', 'between:0,2147483647'];
            $rules[$team.'.overs'] = ['required_if:included,1,true', 'nullable', 'integer', 'between:0,5'];
            $rules[$team.'.balls'] = ['required_if:included,1,true', 'nullable', 'integer', 'min:0', 'max:'.($match->balls_per_over - 1)];
        }
        try {
            $data = Validator::make($input, $rules)->validate();
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors['nrr_inputs.'.$field] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
        if (trim($data['reason']) === '') {
            $this->reject('nrr_inputs', 'Explain the official adjusted NRR figures or exclusion.');
        }
        if (! (bool) $data['included']) {
            if (isset($data['team1']) || isset($data['team2'])) {
                $this->reject('nrr_inputs', 'Excluded NRR must not contain figures that would be silently ignored.');
            }

            return ['included' => false, 'reason' => trim($data['reason'])];
        }
        $result = ['included' => true, 'reason' => trim($data['reason'])];
        foreach (['team1', 'team2'] as $team) {
            $result[$team] = ['runs' => (int) $data[$team]['runs'],
                'deliveries' => $this->count($match, $data[$team]['overs'], $data[$team]['balls'], 'nrr_inputs.'.$team.'.')];
        }

        return $result;
    }

    private function count(TournamentMatch $match, mixed $overs, mixed $balls, string $prefix): int
    {
        try {
            return $this->deliveries->fromParts($overs, $balls, $match->balls_per_over, $match->overs_per_innings);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors[$prefix.$field] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
    }

    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
