<?php

namespace App\Services\Tournament;

use App\Models\PlayerMatchStat;
use App\Models\TournamentMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlayerStatsService
{
    public function __construct(private PlayerStatsContext $context, private PlayerStatsValidator $validator) {}

    /**
     * Both expected rows must be explicit: null means absent; otherwise ID and
     * version must match. Omitted figures retain the current draft/official
     * value; explicit null clears one. The stored draft is a full replacement.
     */
    public function saveDraft(int $year, int $matchId, int $teamId, int $memberId, array $expected, array $input): PlayerMatchStat
    {
        return $this->context->withMember($year, $matchId, $teamId, $memberId, function ($match, $member, $actor) use ($expected, $input, $teamId) {
            $rows = $this->lockedRows($match, $member->id, $teamId, $expected);
            $draft = $rows->firstWhere('record_state', 'draft');
            $published = $rows->firstWhere('record_state', 'published');
            $base = $draft?->pending_action === 'replace' ? $draft : $published;
            $fields = [...PlayerStatsValidator::METRICS, 'batting_status'];
            $figures = $this->validator->validate(array_replace($base?->only($fields) ?? [], $input));
            $identity = $published ?? $draft;
            $snapshot = Validator::make([
                'player_name' => $identity?->player_name ?? $member->name,
                'player_gender' => $identity?->player_gender ?? $member->gender,
            ], ['player_name' => ['required', 'string', 'max:255'],
                'player_gender' => ['required', Rule::in(['Male', 'Female', 'Other'])]])->validate();

            $draft ??= new PlayerMatchStat;
            foreach ($figures + $snapshot as $field => $value) {
                $draft->$field = $value;
            }
            $draft->match_id = $match->id;
            $draft->team_id = $teamId;
            $draft->team_member_id = $member->id;
            $draft->record_state = 'draft';
            $draft->pending_action = 'replace';
            $draft->published_at = null;
            $draft->published_by = null;

            return $this->persistDraft($match, $draft, $actor);
        });
    }

    public function discardDraft(int $year, int $matchId, int $teamId, int $memberId, array $expected): void
    {
        $this->context->withMember($year, $matchId, $teamId, $memberId, function ($match, $member) use ($expected, $teamId) {
            $rows = $this->lockedRows($match, $member->id, $teamId, $expected);
            $draft = $rows->firstWhere('record_state', 'draft');
            if ($draft) {
                $draft->delete();
                $match->player_stats_revision++;
                $match->save();
            }
        });
    }

    /** Stage removal of official figures; publication performs the removal later. */
    public function stageWithdrawal(int $year, int $matchId, int $teamId, int $memberId, array $expected): PlayerMatchStat
    {
        return $this->context->withMember($year, $matchId, $teamId, $memberId, function ($match, $member, $actor) use ($expected, $teamId) {
            $rows = $this->lockedRows($match, $member->id, $teamId, $expected);
            $published = $rows->firstWhere('record_state', 'published');
            if (! $published) {
                $this->reject('Only an existing published player performance can be withdrawn. Discard an unpublished draft instead.');
            }
            $draft = $rows->firstWhere('record_state', 'draft') ?? new PlayerMatchStat;
            foreach ($published->only([...PlayerStatsValidator::METRICS, 'batting_status', 'player_name', 'player_gender']) as $field => $value) {
                $draft->$field = $value;
            }
            $draft->match_id = $match->id;
            $draft->team_id = $teamId;
            $draft->team_member_id = $member->id;
            $draft->record_state = 'draft';
            $draft->pending_action = 'withdraw';
            $draft->published_at = null;
            $draft->published_by = null;

            return $this->persistDraft($match, $draft, $actor);
        });
    }

    private function persistDraft(TournamentMatch $match, PlayerMatchStat $draft, User $actor): PlayerMatchStat
    {
        if (! $draft->exists || $draft->isDirty()) {
            $draft->version = $draft->exists ? $draft->version + 1 : 1;
            $draft->updated_by = $actor->id;
            $draft->save();
            $match->player_stats_revision++;
            $match->save();
        }

        return $draft->refresh();
    }

    private function lockedRows(TournamentMatch $match, int $memberId, int $teamId, array $expected): Collection
    {
        if (array_diff(array_keys($expected), ['draft', 'published'])) {
            $this->reject('The player edit contains unsupported expectations. Reload the player.');
        }
        $rules = [];
        foreach (['draft', 'published'] as $state) {
            $rules[$state] = ['present', 'nullable', 'array:id,version', 'required_array_keys:id,version'];
            $rules[$state.'.id'] = ['required_with:'.$state, 'integer', 'min:1'];
            $rules[$state.'.version'] = ['required_with:'.$state, 'integer', 'min:1'];
        }
        $expectations = Validator::make($expected, $rules)->validate();
        $rows = PlayerMatchStat::where('match_id', $match->id)->where('team_member_id', $memberId)
            ->orderBy('id')->lockForUpdate()->get();
        foreach (['draft', 'published'] as $state) {
            $row = $rows->firstWhere('record_state', $state);
            $wanted = $expectations[$state];
            if (($row === null) !== ($wanted === null)
                || ($row && ($row->id !== (int) $wanted['id'] || $row->version !== (int) $wanted['version'] || $row->team_id !== $teamId))) {
                $this->reject('This player entry changed while you were editing. Reload before saving.');
            }
        }

        return $rows;
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['player' => $message]);
    }
}
