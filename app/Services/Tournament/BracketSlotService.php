<?php

namespace App\Services\Tournament;

use App\Helpers\CompanyAuth;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Support\ScorerWriteThrottle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BracketSlotService
{
    public function __construct(private TournamentWriteService $writes, private KnockoutDependencies $dependencies) {}

    /** Move into an empty QF place, or swap exactly the reviewed two fixtures. */
    public function move(int $year, int $matchId, int $version, int $position, ?array $expectedOccupant): void
    {
        ScorerWriteThrottle::hit();
        Validator::make(compact('year', 'matchId', 'version', 'position', 'expectedOccupant'), [
            'year' => ['integer', 'between:1,9999'], 'matchId' => ['integer', 'min:1'], 'version' => ['integer', 'min:1'],
            'position' => ['integer', 'min:1', 'max:'.(int) config('tournament.knockout_slots.QF')],
            'expectedOccupant' => ['nullable', 'array:id,version', 'required_array_keys:id,version'],
            'expectedOccupant.id' => ['required_with:expectedOccupant', 'integer', 'min:1'],
            'expectedOccupant.version' => ['required_with:expectedOccupant', 'integer', 'min:1'],
        ])->validate();

        $this->writes->run($year, function () use ($year, $matchId, $version, $position, $expectedOccupant) {
            abort_unless(config('tournament.enabled') === true, 404);
            $actor = User::whereKey(CompanyAuth::user()?->id)->sharedLock()->first();
            abort_unless($actor?->is_scorer, 403);
            // No group/team/source changes. Match rows are locked in ID order.
            $matches = TournamentMatch::where('tournament_year', $year)->orderBy('id')->lockForUpdate()->get();
            $match = $matches->firstWhere('id', $matchId);
            if (! $match || $match->stage !== 'QF' || $match->group_id !== null || $match->version !== $version) {
                $this->reject('Select a current quarter-final in this year and reload its latest version.');
            }
            $other = $matches->first(fn ($row) => $row->stage === 'QF' && $row->bracket_position === $position && $row->id !== $matchId);
            if (($other === null) !== ($expectedOccupant === null)
                || ($other && ($other->id !== (int) $expectedOccupant['id'] || $other->version !== (int) $expectedOccupant['version']))) {
                $this->reject('The target position changed. Reload both fixtures before rearranging them.');
            }
            if ($match->bracket_position === $position) {
                return;
            }
            if ($match->bracket_position === null || $match->bracket_position < 1
                || $match->bracket_position > (int) config('tournament.knockout_slots.QF')) {
                $this->reject('The current quarter-final has no valid position. Resolve its structure first.');
            }
            $old = $match->bracket_position;
            $proposed = clone $match;
            $proposed->bracket_position = $position;
            $this->dependencies->assertChange($match, $proposed, $matches);
            if ($other) {
                $proposedOther = clone $other;
                $proposedOther->bracket_position = $old;
                $this->dependencies->assertChange($other, $proposedOther, $matches);
                // The existing partial unique index is immediate on both engines.
                // Null is a private scratch position inside this transaction only.
                // Never disable constraints, delete/reinsert history, or commit it.
                DB::table('matches')->where('id', $match->id)->update(['bracket_position' => null]);
                $other->bracket_position = $old;
                $this->save($other, $actor);
            }
            $match->bracket_position = $position;
            $this->save($match, $actor);
        });
    }

    private function save(TournamentMatch $match, User $actor): void
    {
        $match->version++;
        $match->updated_by = $actor->id;
        $match->save();
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['tournament' => $message]);
    }
}
