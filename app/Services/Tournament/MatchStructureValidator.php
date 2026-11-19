<?php

namespace App\Services\Tournament;

use App\Models\GroupTeam;
use App\Models\Team;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MatchStructureValidator
{
    public function __construct(private KnockoutEligibility $eligibility, private KnockoutIntegrity $integrity) {}

    /**
     * Validate a proposed new fixture; this method never writes or grants access.
     * The scheduling service must authorize and run this inside the common year
     * transaction, with group/team locks, immediately before inserting the match.
     * Database unique indexes remain the final duplicate safeguard.
     */
    public function validate(int $year, array $input): array
    {
        $fields = ['stage', 'group_id', 'bracket_position', 'team1_id', 'team2_id',
            'balls_per_over', 'overs_per_innings', 'max_wickets'];
        if (array_diff(array_keys($input), $fields)) {
            $this->reject('tournament', 'Match setup contains unsupported fields. Reload and try again.');
        }

        Validator::make(['year' => $year], ['year' => ['required', 'integer', 'between:1,9999']])->validate();
        $data = Validator::make($input + ['overs_per_innings' => 5, 'max_wickets' => 7], [
            'stage' => ['required', Rule::in(['G', 'QF', 'SF', 'F'])],
            'group_id' => ['required_if:stage,G', 'nullable', 'integer', 'min:1'],
            'bracket_position' => ['required_unless:stage,G', 'nullable', 'integer', 'min:1'],
            'team1_id' => ['required', 'integer', 'min:1'],
            'team2_id' => ['required', 'integer', 'min:1', 'different:team1_id'],
            'balls_per_over' => ['required', 'integer', Rule::in([4, 6])],
            'overs_per_innings' => ['required', 'integer', Rule::in([5])],
            'max_wickets' => ['required', 'integer', Rule::in([7])],
        ])->validate();

        // Browser controls submit strings; compare canonical IDs as well as
        // Laravel's field rule so 12 and "12" cannot become opposing teams.
        if ((int) $data['team1_id'] === (int) $data['team2_id']) {
            $this->reject('team2_id', 'Select two different teams.');
        }

        if ($data['stage'] !== 'G') {
            if (($data['group_id'] ?? null) !== null) {
                $this->reject('group_id', 'Knockout matches cannot belong to a group.');
            }
            $slots = config('tournament.knockout_slots.'.$data['stage'], 0);
            if ($data['bracket_position'] > $slots) {
                $this->reject('bracket_position', 'Select a valid position for this knockout round.');
            }
            $matches = TournamentMatch::where('tournament_year', $year)->orderBy('id')->get();
            $teams = Team::whereIn('id', [$data['team1_id'], $data['team2_id']])->orderBy('id')->get();
            $assignments = GroupTeam::where('tournament_year', $year)->orderBy('id')->get();
            $this->eligibility->pair($year, $data['stage'], (int) $data['team1_id'], (int) $data['team2_id'], $teams, $assignments, $matches);
            $this->integrity->assertFixture($year, $data['stage'], (int) $data['bracket_position'], (int) $data['team1_id'], (int) $data['team2_id'], $matches);

            return ['stage' => $data['stage'], 'group_id' => null, 'bracket_position' => (int) $data['bracket_position'],
                'team1_id' => (int) $data['team1_id'], 'team2_id' => (int) $data['team2_id'],
                'balls_per_over' => (int) $data['balls_per_over'], 'overs_per_innings' => 5, 'max_wickets' => 7];
        }
        if (($data['bracket_position'] ?? null) !== null) {
            $this->reject('bracket_position', 'Group matches do not have a bracket position.');
        }
        $groupId = (int) $data['group_id'];
        if (! TournamentGroup::whereKey($groupId)->where('tournament_year', $year)->exists()) {
            $this->reject('group_id', 'Select an existing group in this tournament year.');
        }
        $teamIds = [(int) $data['team1_id'], (int) $data['team2_id']];
        $approved = Team::whereIn('id', $teamIds)->where('approved', true)->pluck('id')->all();
        $assigned = GroupTeam::where('tournament_year', $year)->where('group_id', $groupId)
            ->whereIn('team_id', $teamIds)->pluck('team_id')->all();
        foreach ($teamIds as $index => $teamId) {
            $field = 'team'.($index + 1).'_id';
            if (! in_array($teamId, $approved)) {
                $this->reject($field, 'Select an existing approved team.');
            }
            if (! in_array($teamId, $assigned)) {
                $this->reject($field, 'Select a team assigned to this group and tournament year.');
            }
        }

        // Either orientation represents the same fixture. Keep the submitted
        // Team 1/Team 2 order in the returned values for display and score entry.
        if (TournamentMatch::where('tournament_year', $year)->where('stage', 'G')->where('group_id', $groupId)
            ->where(fn ($query) => $query
                ->where(fn ($pair) => $pair->where('team1_id', $teamIds[0])->where('team2_id', $teamIds[1]))
                ->orWhere(fn ($pair) => $pair->where('team1_id', $teamIds[1])->where('team2_id', $teamIds[0])))
            ->exists()) {
            $this->reject('team2_id', 'These teams already have a group fixture, including the reversed pairing.');
        }

        return [
            'stage' => 'G', 'group_id' => $groupId, 'bracket_position' => null,
            'team1_id' => $teamIds[0], 'team2_id' => $teamIds[1],
            'balls_per_over' => (int) $data['balls_per_over'],
            'overs_per_innings' => 5, 'max_wickets' => 7,
        ];
    }

    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
