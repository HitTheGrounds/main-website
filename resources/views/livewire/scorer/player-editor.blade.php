<?php

use App\Services\Tournament\PlayerEntryService;
use App\Services\Tournament\PlayerPublicationService;
use App\Services\Tournament\PlayerStatsService;
use App\Services\Tournament\PlayerStatsValidator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public int $matchId;
    #[Locked]
    public int $teamId;
    #[Locked]
    public ?int $memberId = null;
    #[Locked]
    public array $expected = ['draft' => null, 'published' => null];
    #[Locked]
    public ?array $official = null;
    #[Locked]
    public ?string $pendingAction = null;
    #[Locked]
    public ?array $review = null;

    public array $form = [];
    public string $notice = '';

    public function mount(int $matchId): void
    {
        $this->matchId = $matchId;
        $this->teamId = $this->snapshot()['teams'][0]['id'];
    }

    public function with(): array
    {
        return ['data' => $this->snapshot(), 'metrics' => $this->metrics(),
            // For the shared match header (same scorer-only read as the result page).
            'match' => app(\App\Services\Tournament\MatchSchedulingService::class)->findForScheduling((int) config('tournament.year'), $this->matchId)];
    }

    public function selectTeam(int $teamId): void
    {
        $data = $this->snapshot();
        if (! in_array($teamId, array_column($data['teams'], 'id'), true)) {
            $this->reject('Select a participating team.');
        }
        $this->teamId = $teamId;
        $this->closeEditor();
    }

    public function selectPlayer(int $memberId): void
    {
        $this->loadPlayer($memberId, $this->snapshot());
    }

    public function returnToList(): void
    {
        $this->snapshot();
        $this->closeEditor();
    }

    public function reloadPlayer(): void
    {
        $this->requireSelection();
        $this->loadPlayer($this->memberId, $this->snapshot());
        $this->notice = 'Latest saved figures loaded.';
    }

    public function savePlayer(bool $next = false): void
    {
        $this->resetErrorBag();
        $this->notice = '';
        $this->requireSelection();
        app(PlayerStatsService::class)->saveDraft((int) config('tournament.year'), $this->matchId,
            $this->teamId, $this->memberId, $this->expected, $this->form);
        $current = $this->memberId;
        $data = $this->snapshot();
        $this->closeEditor();
        if ($next) {
            $players = array_values(array_filter($data['players'], fn ($player) => $player['team_id'] === $this->teamId));
            $index = array_search($current, array_column($players, 'id'), true);
            if ($index !== false && isset($players[$index + 1])) {
                $this->loadPlayer($players[$index + 1]['id'], $data);
            }
        }
        $this->notice = 'Player draft saved. Published figures have not changed.';
    }

    public function discardDraft(): void
    {
        $this->requireSelection();
        app(PlayerStatsService::class)->discardDraft((int) config('tournament.year'), $this->matchId,
            $this->teamId, $this->memberId, $this->expected);
        $this->loadPlayer($this->memberId, $this->snapshot());
        $this->notice = 'Draft discarded. Published figures have not changed.';
    }

    public function stageWithdrawal(): void
    {
        $this->requireSelection();
        app(PlayerStatsService::class)->stageWithdrawal((int) config('tournament.year'), $this->matchId,
            $this->teamId, $this->memberId, $this->expected);
        $this->loadPlayer($this->memberId, $this->snapshot());
        $this->notice = 'Withdrawal saved as a draft. Official figures remain until publication.';
    }

    public function reviewPlayerUpdates(): void
    {
        $this->resetErrorBag();
        $this->notice = '';
        $this->review = null;
        if ($this->memberId !== null) {
            $this->reject('Save your player edits and return to the list before reviewing this match.');
        }
        $this->review = app(PlayerPublicationService::class)->review((int) config('tournament.year'), $this->matchId);
    }

    public function cancelReview(): void
    {
        $this->snapshot();
        $this->review = null;
        $this->notice = '';
        $this->resetErrorBag();
    }

    public function publishPlayerUpdates(): void
    {
        $this->resetErrorBag();
        $this->notice = '';
        if ($this->review === null || $this->memberId !== null) {
            $this->reject('Review the saved player updates before publishing.');
        }
        $review = $this->review;
        // A rejected or failed attempt must be reviewed again; the service keeps
        // source rows, drafts, totals and metadata atomic on every failure.
        $this->review = null;
        try {
            app(PlayerPublicationService::class)->publish((int) config('tournament.year'), $this->matchId,
                $review['version'], $review['player_stats_revision'], $review['receipt']);
        } catch (ValidationException|\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);
            $this->addError('player', 'Publication failed. Your saved drafts remain. Review the updates again and retry.');

            return;
        }
        $this->notice = 'Player updates published and tournament totals updated. Public pages show the changes on their next load or refresh.';
    }

    private function loadPlayer(int $id, array $data): void
    {
        $member = collect($data['players'])->first(fn ($player) => $player['id'] === $id && $player['team_id'] === $this->teamId);
        if (! $member) {
            $this->reject('Select a player currently in this team.');
        }
        $this->review = null;
        $rows = collect($data['rows'])->where('team_member_id', $id)->where('team_id', $this->teamId);
        $draft = $rows->firstWhere('record_state', 'draft');
        $official = $rows->firstWhere('record_state', 'published');
        $fields = [...PlayerStatsValidator::METRICS, 'batting_status'];
        $this->memberId = $id;
        foreach (['draft' => $draft, 'published' => $official] as $state => $row) {
            $this->expected[$state] = $row ? ['id' => $row['id'], 'version' => $row['version']] : null;
        }
        $this->official = $official ? array_intersect_key($official, array_flip($fields)) : null;
        $this->pendingAction = $draft['pending_action'] ?? null;
        $base = ($draft['pending_action'] ?? null) === 'replace' ? $draft : $official;
        $this->form = array_replace(array_fill_keys($fields, null), array_intersect_key($base ?? [], array_flip($fields)));
        $this->notice = '';
        $this->resetErrorBag();
    }

    private function closeEditor(): void
    {
        $this->review = null;
        $this->memberId = null;
        $this->form = [];
        $this->expected = ['draft' => null, 'published' => null];
        $this->official = null;
        $this->pendingAction = null;
        $this->notice = '';
        $this->resetErrorBag();
    }

    private function requireSelection(): void
    {
        if ($this->memberId === null) {
            $this->reject('Select a player before changing figures.');
        }
    }

    private function snapshot(): array
    {
        return app(PlayerEntryService::class)->read((int) config('tournament.year'), $this->matchId);
    }

    private function metrics(): array
    {
        return ['runs_scored' => 'Runs scored', 'balls_faced' => 'Balls faced', 'fours' => 'Fours', 'sixes' => 'Sixes',
            'bowling_balls' => 'Balls bowled', 'runs_conceded' => 'Runs conceded', 'wickets_taken' => 'Wickets taken', 'dot_balls' => 'Dot balls'];
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['player' => $message]);
    }
}; ?>

<div class="scorer-screen space-y-5">
    <x-scorer.match-header :match="$match" active="players" title="Player figures" />
    <div class="sr-hint">
        <p>Enter normal innings only. Exclude Super Over figures. You can skip players and leave fields blank.</p>
        @unless($data['eligible'])<p>Drafts can be saved now. Publishing requires a finished, published win or tie.</p>@endunless
    </div>
    <div class="scorer-feedback" aria-live="polite">
        @if($memberId === null)
            @if($notice)<p role="status" class="alert alert-success">{{ $notice }}</p>@endif
            @if($errors->any())<div role="alert" class="alert alert-error"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
        @endif
        <p wire:loading role="status" class="text-sm">Updating player figures...</p>
    </div>
    <ol class="scorer-steps" aria-label="Player figures workflow">
        <li @if($review === null) aria-current="step" @endif>1. Enter figures</li>
        <li @if($review !== null) aria-current="step" @endif>2. Review and publish</li>
    </ol>
    @if($review !== null)
        <section class="card border border-base-300 bg-base-100" aria-labelledby="player-review-heading" wire:key="player-review" x-data x-init="$nextTick(() => $refs.playerHeading.focus())"><div class="card-body space-y-4">
            <h2 id="player-review-heading" x-ref="playerHeading" tabindex="-1" class="text-xl font-bold">Review player updates</h2>
            <p class="text-center">{{ count($review['changes']) }} pending player updates across this match. Only the saved changes below will be published.</p>
            <p class="text-sm">Not recorded means unknown, not zero. Partial figures are allowed. Unentered players stay unchanged.</p>
            @foreach($data['teams'] as $team)
                <section class="space-y-3" aria-labelledby="review-team-{{ $team['id'] }}">
                    <h3 id="review-team-{{ $team['id'] }}" class="text-lg font-bold">{{ $team['team_name'] }}</h3>
                    @forelse(collect($review['changes'])->where('draft.team_id', $team['id']) as $change)
                        @php
                            $withdraw = $change['draft']['pending_action'] === 'withdraw';
                            $action = $withdraw ? 'Withdraw official performance' : ($change['published'] === null ? 'Add performance' : 'Correct performance');
                        @endphp
                        <article wire:key="review-draft-{{ $change['draft']['id'] }}" class="rounded-lg border border-base-300 p-3 space-y-2">
                            <h4 class="font-bold break-words">{{ $change['draft']['player_name'] }}</h4>
                            <p class="text-sm">{{ $action }} | Recorded gender: {{ $change['draft']['player_gender'] }}</p>
                            @if(!$withdraw && count($change['missing_metrics']))
                                <p class="text-sm">Incomplete figures: {{ count($change['missing_metrics']) }} of {{ count($metrics) }} numeric fields are not recorded. You can still publish.</p>
                            @endif
                            @if($withdraw)<p class="text-sm">This removes the official performance from tournament totals. The registered player stays unchanged.</p>@endif
                            <div class="scorer-scroll overflow-x-auto" tabindex="0" role="region" aria-label="Scrollable figures"><table class="table w-full">
                                <caption class="sr-only">Reviewed figures for {{ $change['draft']['player_name'] }}</caption>
                                <thead><tr><th scope="col">Figure</th><th scope="col">Current official</th><th scope="col">After publication</th></tr></thead>
                                <tbody>
                                    @foreach($metrics + ['batting_status' => 'Batting status'] as $field => $label)
                                        <tr><th scope="row">{{ $label }}</th>
                                            <td>{{ $change['published'] === null ? 'No entry' : str_replace('_', ' ', (string) ($change['published'][$field] ?? 'Not recorded')) }}</td>
                                            <td>{{ $withdraw ? 'Removed' : str_replace('_', ' ', (string) ($change['draft'][$field] ?? 'Not recorded')) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table></div>
                        </article>
                    @empty<p class="text-sm">No pending updates for this team.</p>@endforelse
                </section>
            @endforeach
            <p class="text-sm">If another scorer changes this match or its player entries, publication will be rejected. Review again to load the latest saved changes.</p>
            <p class="text-sm">Public viewers will see published updates when they load or refresh the page.</p>
            <div class="scorer-actions flex flex-wrap gap-3">
                <button type="button" wire:click="publishPlayerUpdates" wire:confirm="Publish all reviewed player updates for both teams, including withdrawals?" wire:loading.attr="disabled" class="btn btn-primary">Publish player updates</button>
                <button type="button" wire:click="reviewPlayerUpdates" wire:loading.attr="disabled" class="btn btn-outline">Review latest saved changes</button>
                <button type="button" wire:click="cancelReview" wire:loading.attr="disabled" class="btn btn-ghost">Back to player list</button>
            </div>
        </div></section>
    @else
        <div class="scorer-review-bar">
            @if(collect($data['rows'])->contains('record_state', 'draft'))

                <button type="button" wire:click="reviewPlayerUpdates" wire:loading.attr="disabled" @disabled(!$data['eligible']) class="btn btn-primary mx-auto">Review player updates</button>
            @else<p>No pending player updates to publish.</p>@endif
        </div>
    <div class="scorer-team-picker" role="group" aria-label="Choose team">
        @foreach($data['teams'] as $team)
            <button type="button" wire:click="selectTeam({{ $team['id'] }})" @if($memberId) wire:confirm="Leave this editor? Unsaved changes will be lost." @endif
                wire:loading.attr="disabled" class="btn {{ $teamId === $team['id'] ? 'btn-primary' : 'btn-outline' }}" aria-pressed="{{ $teamId === $team['id'] ? 'true' : 'false' }}">{{ $team['team_name'] }}</button>
        @endforeach
    </div>
        <section class="card border border-base-300 bg-base-100"><div class="card-body space-y-3">
            <h2 class="text-xl font-bold">Select a player</h2>
            @forelse(collect($data['players'])->where('team_id', $teamId) as $player)
                @php
                    $entries = collect($data['rows'])->where('team_member_id', $player['id'])->where('team_id', $teamId);
                    $draft = $entries->firstWhere('record_state', 'draft');
                    $published = $entries->firstWhere('record_state', 'published');
                @endphp
                <div wire:key="player-{{ $player['id'] }}" class="scorer-player-row">
                    <div class="min-w-0 break-words"><span class="font-semibold">{{ $player['name'] }}</span>
                        <span @class(['scorer-player-state', 'is-draft' => $draft !== null])>{{ $draft ? 'Draft changes' : ($published ? 'Published' : 'No figures') }}</span>
                        @if(($draft['pending_action'] ?? null) === 'withdraw')<span class="text-sm">Withdrawal pending</span>@endif
                    </div>
                    <button type="button" wire:click="selectPlayer({{ $player['id'] }})" wire:loading.attr="disabled" class="btn btn-outline btn-sm" aria-haspopup="dialog"><x-scorer.icon name="pencil" /> Edit figures</button>
                </div>
            @empty<p class="text-center">No players are registered for this team.</p>@endforelse
        </div></section>
        <details class="scorer-disclosure"><summary>Saved entries by team</summary><div class="card-body space-y-3">
            @foreach($data['teams'] as $team)
                @php
                    $registered = collect($data['players'])->where('team_id', $team['id'])->count();
                    $recorded = collect($data['rows'])->where('team_id', $team['id'])->pluck('team_member_id')->unique()->count();
                @endphp
                <p>{{ $team['team_name'] }}: {{ $recorded }} players with saved entries (draft or official), {{ $registered }} registered.</p>
            @endforeach
            <p class="text-sm">These counts do not identify the playing XI or confirm that all figures are complete.</p>
        </div></details>
    @endif

    {{-- Edit figures popup. Opens while a player is selected ($memberId) and
         closes when the editor is closed on the server. Esc and the backdrop
         ask before discarding unsaved changes. --}}
    <dialog id="player-figures-dialog" class="sd-dialog sp-dialog" aria-labelledby="player-figures-heading" wire:ignore.self
            x-data
            x-effect="$wire.memberId !== null ? ($el.open || $el.showModal()) : ($el.open && $el.close())"
            x-on:cancel.prevent="if (confirm('Return to the player list? Unsaved changes will be lost.')) $wire.returnToList()"
            x-on:click.self="if (confirm('Return to the player list? Unsaved changes will be lost.')) $wire.returnToList()">
        <div class="sd-dialog-panel">
            @if($memberId !== null)
                @php($teamName = collect($data['teams'])->firstWhere('id', $teamId)['team_name'] ?? '')
                <header class="sd-dialog-head" wire:key="player-entry-{{ $memberId }}">
                    <div class="sp-dialog-title">
                        <x-tournament.team-badge :name="$teamName" />
                        <div>
                            <h2 id="player-figures-heading" tabindex="-1" x-init="$nextTick(() => $el.focus())">{{ collect($data['players'])->firstWhere('id', $memberId)['name'] ?? 'Player no longer available' }}</h2>
                            <p>{{ $teamName }} · Blank means unknown. Enter 0 for a known zero. Saving creates a draft for later review.</p>
                        </div>
                    </div>
                    <button type="button" wire:click="returnToList" wire:confirm="Return to the player list? Unsaved changes will be lost." class="sd-icon-btn" aria-label="Close">
                        <x-scorer.icon name="x" />
                    </button>
                </header>
                <div class="sd-dialog-body">
                    <div class="scorer-feedback" aria-live="polite">
                        @if($notice)<p role="status" class="alert alert-success">{{ $notice }}</p>@endif
                        @if($errors->any())<div role="alert" class="alert alert-error"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
                    </div>
                    <p wire:dirty wire:target="form" class="sr-chip sp-dirty">You have unsaved changes.</p>
                    @if($pendingAction === 'withdraw')<p class="alert alert-warning">Withdrawal pending. Saving this form replaces the withdrawal with a correction draft.</p>@endif
                    <form id="player-figures-form" wire:submit="savePlayer" class="sp-form">
                        @foreach(['Batting' => array_slice($metrics, 0, 4, true), 'Bowling' => array_slice($metrics, 4, null, true)] as $discipline => $fields)
                            <fieldset class="sp-group">
                                <legend>{{ $discipline }}</legend>
                                <div class="sp-fields">
                                    @foreach($fields as $field => $label)
                                        <label><span class="label">{{ $label }}</span><input type="number" inputmode="numeric" min="0" max="{{ $field === 'wickets_taken' ? 7 : 2147483647 }}" step="1" wire:model="form.{{ $field }}" class="input w-full" placeholder="-" /></label>
                                    @endforeach
                                </div>
                                @if($discipline === 'Batting')
                                    <label class="sp-status"><span class="label">Batting status (optional)</span><select wire:model="form.batting_status" class="select w-full">
                                        <option value="">Unknown</option>
                                        @foreach(['out' => 'Out', 'not_out' => 'Not out', 'did_not_bat' => 'Did not bat', 'retired_hurt' => 'Retired hurt', 'retired_out' => 'Retired out'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                    </select></label>
                                @endif
                            </fieldset>
                        @endforeach
                    </form>
                    @if($official !== null)
                        <details class="sp-official"><summary>Current official figures</summary>
                            <dl>@foreach($metrics as $field => $label)<div><dt>{{ $label }}</dt><dd>{{ $official[$field] ?? 'Unknown' }}</dd></div>@endforeach
                                <div><dt>Batting status</dt><dd>{{ str_replace('_', ' ', $official['batting_status'] ?? 'Unknown') }}</dd></div></dl>
                        </details>
                    @endif
                    <div class="sp-secondary">
                        <button type="button" wire:click="reloadPlayer" wire:confirm="Load the latest saved figures? Unsaved changes will be lost." wire:loading.attr="disabled" class="btn btn-ghost btn-sm sd-btn-quiet"><x-scorer.icon name="refresh" /> Reload saved figures</button>
                        @if($expected['draft'] !== null)<button type="button" wire:click="discardDraft" wire:confirm="Discard this saved draft and unsaved changes? Official figures will stay unchanged." wire:loading.attr="disabled" class="btn btn-ghost btn-sm sd-btn-quiet"><x-scorer.icon name="trash" /> Discard draft</button>@endif
                        @if($official !== null && $pendingAction !== 'withdraw')<button type="button" wire:click="stageWithdrawal" wire:confirm="Prepare withdrawal of these official figures? This replaces pending edits; figures remain official until published." wire:loading.attr="disabled" class="btn btn-ghost btn-sm sd-btn-quiet"><x-scorer.icon name="rotate-ccw" /> Prepare withdrawal</button>@endif
                    </div>
                </div>
                <footer class="sd-dialog-foot">
                    <button type="button" wire:click="returnToList" wire:confirm="Return to the player list? Unsaved changes will be lost." wire:loading.attr="disabled" class="btn btn-ghost sd-foot-start">Back to player list</button>
                    <button type="button" wire:click="savePlayer(true)" wire:loading.attr="disabled" class="btn btn-outline"><x-scorer.icon name="save" /> Save and next player</button>
                    <button type="submit" form="player-figures-form" wire:loading.attr="disabled" class="btn btn-primary"><x-scorer.icon name="check" /> Save and return</button>
                </footer>
            @endif
        </div>
    </dialog>
</div>
