<?php

namespace App\Services\Tournament;

use App\Models\PlayerMatchStat;
use App\Models\PlayerTournamentStat;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LogicException;

class PlayerTotalsService
{
    public function __construct(private TournamentWriteService $writes, private PlayerStatsValidator $validator) {}

    /**
     * Internal derived-data writer, not a scorer action or public endpoint.
     * The authorized publisher/rebuild must already hold this year's write lock
     * and any source-row locks, in the shared order. No source rows are changed.
     * Retain the outer transaction so source publication and totals commit once.
     */
    public function recalculate(int $year, array $memberIds): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Player totals require the caller publication/rebuild transaction.');
        }
        Validator::make(compact('year', 'memberIds'), [
            'year' => ['integer', 'between:1,9999'], 'memberIds' => ['array'],
            'memberIds.*' => ['integer', 'min:1', 'distinct'],
        ])->validate();
        $memberIds = array_map('intval', array_values($memberIds));
        sort($memberIds);
        if ($memberIds === []) {
            return;
        }

        // Nested use retains the existing lock/transaction and also ensures the
        // year is serialized if an internal caller omitted that helper.
        $this->writes->run($year, function () use ($year, $memberIds) {
            $sources = PlayerMatchStat::whereIn('team_member_id', $memberIds)->where('record_state', 'published')
                ->whereHas('tournamentMatch', fn ($query) => $query->where('tournament_year', $year)
                    ->where('status', 'finished')->whereNotNull('result_published_at')
                    ->whereIn('result_type', ['win', 'tie'])->whereIn('stage', app(PlayoffFeature::class)->stages($year)))
                ->with('tournamentMatch:id,team1_id,team2_id,balls_per_over')->orderBy('match_id')->orderBy('id')->get();
            $calculated = [];
            foreach ($sources as $source) {
                $id = $source->team_member_id;
                if (! in_array($source->team_id, [$source->tournamentMatch->team1_id, $source->tournamentMatch->team2_id], true)) {
                    $this->reject('A published player performance has a team outside its match.');
                }
                $identity = Validator::make($source->only(['player_name', 'player_gender']), [
                    'player_name' => ['required', 'string', 'max:255'], 'player_gender' => ['required', Rule::in(['Male', 'Female', 'Other'])],
                ])->validate();
                if (! isset($calculated[$id])) {
                    // Earliest eligible match supplies the display name, never
                    // a mutable registration profile. Gender/team must agree.
                    $calculated[$id] = ['tournament_year' => $year, 'team_member_id' => $id, 'team_id' => $source->team_id,
                        ...$identity, 'highest_score' => null, 'highest_score_match_id' => null,
                        'bowling_overs_units' => null, 'strike_rate_complete' => true, 'economy_complete' => true];
                    foreach (PlayerStatsValidator::METRICS as $metric) {
                        $calculated[$id][$metric] = null;
                        $calculated[$id][$metric.'_recorded_count'] = 0;
                    }
                } elseif ($calculated[$id]['team_id'] !== $source->team_id || $calculated[$id]['player_gender'] !== $source->player_gender) {
                    $this->reject('Published player performances have conflicting team or gender snapshots. Resolve the historical classification before recalculating.');
                }
                $figures = $this->validator->validate($source->only([...PlayerStatsValidator::METRICS, 'batting_status']));
                // Check paired inputs per match: equal recorded counts alone
                // cannot prove the numerator and denominator cover the same rows.
                if ($figures['batting_status'] !== 'did_not_bat'
                    && ($figures['runs_scored'] === null || $figures['balls_faced'] === null)) {
                    $calculated[$id]['strike_rate_complete'] = false;
                }
                if ($figures['runs_conceded'] === null || $figures['bowling_balls'] === null) {
                    $calculated[$id]['economy_complete'] = false;
                }
                if ($figures['bowling_balls'] !== null) {
                    $ballsPerOver = $source->tournamentMatch->balls_per_over;
                    if (! in_array($ballsPerOver, [4, 6], true)) {
                        $this->reject('Published bowling figures require a supported balls-per-over format.');
                    }
                    $units = BigInteger::of($figures['bowling_balls'])
                        ->multipliedBy(intdiv(PlayerTournamentStat::BOWLING_OVER_UNITS, $ballsPerOver))
                        ->plus($calculated[$id]['bowling_overs_units'] ?? 0);
                    if ($units->isGreaterThan('9223372036854775807')) {
                        $this->reject('Calculated bowling overs exceed their supported storage range.');
                    }
                    $calculated[$id]['bowling_overs_units'] = $units->toInt();
                }
                foreach (PlayerStatsValidator::METRICS as $metric) {
                    if ($figures[$metric] === null) {
                        continue;
                    }
                    $sum = BigInteger::of($calculated[$id][$metric] ?? 0)->plus($figures[$metric]);
                    $count = $calculated[$id][$metric.'_recorded_count'] + 1;
                    if ($sum->isGreaterThan('9223372036854775807') || $count > 2147483647) {
                        $this->reject('Calculated player totals exceed their supported storage range.');
                    }
                    $calculated[$id][$metric] = $sum->toInt();
                    $calculated[$id][$metric.'_recorded_count'] = $count;
                }
                if ($figures['runs_scored'] !== null && ($calculated[$id]['highest_score'] === null || $figures['runs_scored'] > $calculated[$id]['highest_score'])) {
                    $calculated[$id]['highest_score'] = $figures['runs_scored'];
                    $calculated[$id]['highest_score_match_id'] = $source->match_id;
                }
            }

            // Final table in the shared lock order. Never lock earlier tables
            // here, and never change performances, registration or group points.
            $existing = PlayerTournamentStat::where('tournament_year', $year)->whereIn('team_member_id', $memberIds)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('team_member_id');
            foreach ($memberIds as $id) {
                $summary = $existing->get($id);
                if (! isset($calculated[$id])) {
                    $summary?->delete();

                    continue;
                }
                $summary ??= new PlayerTournamentStat;
                foreach ($calculated[$id] as $field => $value) {
                    $summary->$field = $value;
                }
                if (! $summary->exists || $summary->isDirty()) {
                    $summary->save();
                }
            }
        });
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['player' => $message]);
    }
}
