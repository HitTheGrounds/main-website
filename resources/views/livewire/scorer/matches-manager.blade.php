<?php

use App\Models\Team;
use App\Services\Tournament\MatchSchedulingService;
use App\Services\Tournament\TournamentGroupService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $groupChoice = '';
    public array $newStructure = ['team1_id' => '', 'team2_id' => '', 'balls_per_over' => '4'];
    public array $newSchedule = ['status' => 'upcoming', 'venue' => '', 'scheduled_at' => '', 'display_number' => '', 'batting_first_id' => ''];
    public array $schedule = [];
    public array $fixtureStructure = [];
    #[Locked]
    public bool $fixtureEditable = false;
    #[Locked]
    public int $fixtureGroupVersion = 0;
    #[Locked]
    public ?string $fixtureRevision = null;
    #[Locked]
    public array $fixtureTeams = [];
    public array $filterForm = ['group_id' => '', 'stage' => '', 'status' => '', 'venue' => '', 'sort' => 'upcoming_first'];
    public string $notice = '';
    // Which popup is open: '' (none), 'create' or 'edit'. UI state only; the
    // browser can change it freely, so nothing security-relevant depends on it.
    public string $panel = '';

    #[Locked]
    public ?int $selectedGroupId = null;
    #[Locked]
    public ?int $groupVersion = null;
    #[Locked]
    public ?int $editingId = null;
    #[Locked]
    public ?int $editingVersion = null;
    #[Locked]
    public bool $statusEditable = false;
    #[Locked]
    public bool $battingEditable = false;
    #[Locked]
    public bool $publishedResult = false;
    #[Locked]
    public array $filters = [];

    public function mount(): void
    {
        if (request()->filled('edit')) {
            $input = Validator::make(request()->query(), ['edit' => ['required', 'integer', 'min:1']])->validate();
            $this->openMatch((int) $input['edit']);
        }
    }

    public function with(): array
    {
        if (! app(\App\Services\Tournament\PlayoffFeature::class)->enabled($this->year())) {
            if (($this->filterForm['stage'] ?? '') === 'PO') {
                $this->filterForm['stage'] = '';
            }
            if (($this->filters['stage'] ?? '') === 'PO') {
                $this->filters['stage'] = '';
                $this->filterForm['stage'] = '';
                $this->resetPage();
            }
        }
        $setup = app(TournamentGroupService::class)->setup($this->year());
        $teams = $setup['groups']->firstWhere('id', $this->selectedGroupId)['teams'] ?? collect();
        $approvedIds = Team::whereIn('id', $teams->pluck('id'))->where('approved', true)->pluck('id');
        $service = app(MatchSchedulingService::class);
        $matches = $service->listing($this->year(), $this->filters);
        $editing = null;
        if ($this->editingId !== null) {
            try {
                $editing = $service->findForScheduling($this->year(), $this->editingId);
            } catch (ValidationException) {
                // Another scorer may have deleted this unused fixture. Keep
                // permission failures intact, but stop rendering a missing editor.
                $this->reset('editingId', 'editingVersion', 'fixtureEditable', 'fixtureStructure', 'fixtureTeams', 'fixtureRevision', 'fixtureGroupVersion', 'panel', 'schedule');
                $this->notice = 'This fixture was removed by another update. The match list has been reloaded.';
            }
        }

        return [
            'groups' => $setup['groups'],
            'teams' => $teams->whereIn('id', $approvedIds),
            'matches' => $matches,
            'editing' => $editing,
            'year' => $this->year(),
            'stageLabels' => app(\App\Services\Tournament\PlayoffFeature::class)->labels($this->year()),
        ];
    }

    public function updatedGroupChoice(): void
    {
        $this->chooseGroup();
    }

    public function updatedNewStructure(): void
    {
        if (! in_array((string) ($this->newSchedule['batting_first_id'] ?? ''), [
            (string) ($this->newStructure['team1_id'] ?? ''), (string) ($this->newStructure['team2_id'] ?? ''),
        ], true)) {
            $this->newSchedule['batting_first_id'] = '';
        }
    }

    public function chooseGroup(): void
    {
        $setup = app(TournamentGroupService::class)->setup($this->year());
        $this->resetErrorBag();
        $this->notice = '';
        $this->panel = 'create';
        if ($this->groupChoice === '') {
            $this->reset('selectedGroupId', 'groupVersion', 'newStructure');
            return;
        }
        Validator::make(['group_id' => $this->groupChoice], ['group_id' => ['required', 'integer', 'min:1']])->validate();
        $group = $setup['groups']->firstWhere('id', (int) $this->groupChoice);
        if (! $group) {
            throw ValidationException::withMessages(['group_id' => 'Select a group from this tournament.']);
        }
        $this->selectedGroupId = $group['id'];
        $this->groupVersion = $group['version'];
        $this->reset('newStructure');
        $this->newStructure['balls_per_over'] = (string) config('tournament.default_balls_per_over.group', 4);
        $this->newSchedule['batting_first_id'] = '';
    }

    public function createMatch(): void
    {
        $this->resetErrorBag();
        $this->notice = '';
        $this->panel = 'create';
        if ($this->selectedGroupId === null || $this->groupVersion === null) {
            throw ValidationException::withMessages(['group_id' => 'Choose a group before creating a match.']);
        }
        $structure = array_merge($this->newStructure, ['stage' => 'G', 'group_id' => $this->selectedGroupId, 'bracket_position' => null]);
        $match = app(MatchSchedulingService::class)->create($this->year(), $this->groupVersion, $structure, $this->scheduleInput($this->newSchedule));
        $this->reset('newStructure', 'newSchedule', 'filters', 'filterForm');
        $this->resetPage();
        $this->openMatch($match->id);
        $this->panel = '';
        $this->notice = 'Match created. Scores remain empty until results are entered and published.';
    }

    public function openMatch(int $id): void
    {
        $match = app(MatchSchedulingService::class)->findForScheduling($this->year(), $id);
        $this->resetErrorBag();
        $this->notice = '';
        $this->panel = 'edit';
        $this->editingId = $match->id;
        $this->editingVersion = $match->version;
        $this->statusEditable = $match->status !== 'finished'
            && $match->structure_locked_at === null && $match->result_published_at === null
            && $match->player_stats_published_at === null && $match->result_type === null && $match->winner_id === null;
        $this->publishedResult = $match->result_published_at !== null;
        $this->battingEditable = $match->structure_locked_at === null && $match->result_published_at === null;
        $this->fixtureEditable = app(MatchSchedulingService::class)->canEditFixture($match);
        $this->fixtureStructure = $match->only(['stage', 'group_id', 'bracket_position', 'team1_id', 'team2_id', 'balls_per_over', 'overs_per_innings', 'max_wickets']);
        $this->loadFixtureOptions();
        $this->schedule = [
            'status' => $match->status, 'venue' => $match->venue ?? '',
            'scheduled_at' => $match->scheduled_at?->setTimezone(config('tournament.timezone'))->format('Y-m-d\TH:i') ?? '',
            'display_number' => $match->display_number ?? '', 'batting_first_id' => $match->batting_first_id ?? '',
        ];
    }

    public function saveSchedule(): void
    {
        $this->resetErrorBag();
        $this->notice = '';
        if ($this->editingId === null || $this->editingVersion === null) {
            throw ValidationException::withMessages(['tournament' => 'Open a match before saving.']);
        }
        $this->panel = 'edit';
        $input = $this->scheduleInput($this->schedule);
        if (! $this->statusEditable) {
            unset($input['status']);
        }
        if (! $this->battingEditable) {
            unset($input['batting_first_id']);
        }
        $match = $this->fixtureEditable
            ? app(MatchSchedulingService::class)->updateFixture($this->year(), $this->editingId, $this->editingVersion,
                $this->fixtureGroupVersion, $this->normalizedStructure(), $input, $this->fixtureRevision)
            : app(MatchSchedulingService::class)->updateSchedule($this->year(), $this->editingId, $this->editingVersion, $input);
        $this->openMatch($match->id);
        $this->panel = '';
        $this->notice = 'Match details saved.';
    }

    public function updatedFixtureStructure(mixed $value, ?string $key): void
    {
        if (! $this->fixtureEditable) {
            return;
        }
        if (in_array($key, ['stage', 'group_id'], true)) {
            if ($key === 'stage') {
                $this->fixtureStructure['group_id'] = '';
                $this->fixtureStructure['bracket_position'] = '';
                $format = ['G' => 'group', 'PO' => 'playoff', 'QF' => 'quarter_final', 'SF' => 'semi_final', 'F' => 'final'][$value] ?? null;
                if ($format === null) {
                    throw ValidationException::withMessages(['stage' => 'Select a valid tournament round.']);
                }
                $this->fixtureStructure['balls_per_over'] = config('tournament.default_balls_per_over.'.$format);
            }
            $this->fixtureStructure['team1_id'] = '';
            $this->fixtureStructure['team2_id'] = '';
            $this->schedule['batting_first_id'] = '';
            $this->loadFixtureOptions();
        } elseif (in_array($key, ['team1_id', 'team2_id'], true)
            && ! in_array((string) ($this->schedule['batting_first_id'] ?? ''), [
                (string) ($this->fixtureStructure['team1_id'] ?? ''), (string) ($this->fixtureStructure['team2_id'] ?? ''),
            ], true)) {
            $this->schedule['batting_first_id'] = '';
        }
    }

    public function deleteFixture(): void
    {
        $this->resetErrorBag();
        if ($this->editingId === null || $this->editingVersion === null) {
            throw ValidationException::withMessages(['tournament' => 'Open a fixture before deleting it.']);
        }
        app(MatchSchedulingService::class)->deleteFixture($this->year(), $this->editingId, $this->editingVersion);
        $this->reset('editingId', 'editingVersion', 'fixtureEditable', 'fixtureStructure', 'fixtureTeams', 'fixtureRevision', 'fixtureGroupVersion', 'panel', 'schedule');
        $this->resetPage();
        $this->notice = 'Unused fixture deleted. You can correct group assignments or create its replacement.';
    }

    private function loadFixtureOptions(): void
    {
        $this->fixtureTeams = [];
        $this->fixtureGroupVersion = 0;
        $this->fixtureRevision = null;
        if (! $this->fixtureEditable) {
            return;
        }
        $stage = $this->fixtureStructure['stage'] ?? 'G';
        if ($stage !== 'G') {
            $round = app(MatchSchedulingService::class)->knockoutOptions($this->year(), $stage);
            $this->fixtureTeams = $round['candidates'];
            $this->fixtureRevision = $round['revision'];

            return;
        }
        $group = app(TournamentGroupService::class)->setup($this->year())['groups']
            ->firstWhere('id', (int) ($this->fixtureStructure['group_id'] ?? 0));
        if ($group) {
            $this->fixtureGroupVersion = $group['version'];
            $this->fixtureTeams = $group['teams']->values()->all();
        }
    }

    private function normalizedStructure(): array
    {
        $structure = $this->fixtureStructure;
        foreach (['group_id', 'bracket_position'] as $key) {
            if (($structure[$key] ?? null) === '') {
                $structure[$key] = null;
            }
        }

        return $structure;
    }

    public function changeResultStatus(string $status): void
    {
        $this->resetErrorBag();
        $this->notice = '';
        if ($this->editingId === null || $this->editingVersion === null || ! $this->publishedResult) {
            throw ValidationException::withMessages(['tournament' => 'Open a published result before changing its result status.']);
        }
        $match = app(MatchSchedulingService::class)->updateSchedule($this->year(), $this->editingId,
            $this->editingVersion, ['status' => $status]);
        $this->openMatch($match->id);
        $this->notice = $status === 'finished'
            ? 'Stored official result restored. Standings and player totals updated; pending drafts were not published.'
            : 'Match reopened. Its contributions were removed from standings and player totals; saved figures and drafts are preserved.';
    }

    public function reloadMatch(): void
    {
        if ($this->editingId !== null) {
            $this->openMatch($this->editingId);
            $this->notice = 'Latest match details loaded.';
        }
    }

    public function applyFilters(): void
    {
        $this->resetErrorBag();
        $this->panel = '';
        $filters = array_map(fn ($value) => $value === '' ? null : $value, $this->filterForm);
        app(MatchSchedulingService::class)->listing($this->year(), $filters);
        $this->filters = $filters;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->resetErrorBag();
        $this->panel = '';
        $this->reset('filterForm', 'filters');
        $this->resetPage();
    }

    private function scheduleInput(array $input): array
    {
        foreach (['venue', 'scheduled_at', 'display_number', 'batting_first_id'] as $key) {
            if (($input[$key] ?? null) === '') {
                $input[$key] = null;
            }
        }
        return $input;
    }

    private function year(): int
    {
        return (int) config('tournament.year');
    }
}; ?>

@php
    $activeFilters = collect($filters)->except('sort')->filter(fn ($value) => $value !== null && $value !== '')->count()
        + (int) (($filters['sort'] ?? 'upcoming_first') !== 'upcoming_first');
    $sortLabels = ['upcoming_first' => 'Nearest upcoming first', 'scheduled_asc' => 'Scheduled time: first to last'];
@endphp
<div class="scorer-screen sd-screen space-y-5" x-data="{ panel: $wire.entangle('panel') }">
    <header class="scorer-heading sd-heading">
        <div class="sd-heading-copy">
            <h1 class="text-2xl font-bold sm:text-3xl">Scorer dashboard</h1>
            <p class="text-sm text-base-content/70">Find a fixture, open its score sheet, or update its schedule.</p>
        </div>
        <button type="button" x-on:click="panel = 'create'" class="btn btn-primary sd-create-btn" aria-haspopup="dialog" aria-controls="create-match-dialog">
            <x-scorer.icon name="plus" /> Create group match
        </button>
        <p wire:loading role="status" class="sd-loading">Loading match details...</p>
    </header>

    @if($panel === '')
        @include('livewire.scorer.partials.dashboard-feedback')
    @endif

    {{-- Create group match popup. wire:ignore.self keeps Livewire from dropping
         the dialog's open state on re-render; Alpine opens and closes it from "panel". --}}
    <dialog id="create-match-dialog" class="sd-dialog" aria-labelledby="create-match-heading" wire:ignore.self
            x-effect="panel === 'create' ? ($el.open || $el.showModal()) : ($el.open && $el.close())"
            x-on:close="panel = ''" x-on:click.self="$el.close()">
        <div class="sd-dialog-panel">
            <header class="sd-dialog-head">
                <div>
                    <h2 id="create-match-heading">Create group match</h2>
                    <p>Five overs per innings. Seven wickets. No scores are added at creation.</p>
                </div>
                <button type="button" x-on:click="$el.closest('dialog').close()" class="sd-icon-btn" aria-label="Close">
                    <x-scorer.icon name="x" />
                </button>
            </header>
            <div class="sd-dialog-body">
                @if($panel === 'create')
                    @include('livewire.scorer.partials.dashboard-feedback')
                @endif
                @if($groups->isEmpty())
                    <p class="sd-note">No groups are available. Create groups and assign approved teams in <a href="{{ route('scorer.groups.index') }}" class="link">Group setup</a>.</p>
                @else
                    <div class="sd-group-row">
                        <label class="form-control"><span class="label">Group</span>
                            <select wire:model.live="groupChoice" class="select w-full"><option value="">Choose a group</option>
                                @foreach($groups as $group)<option value="{{ $group['id'] }}">{{ $group['name'] }} ({{ $group['team_count'] }} teams)</option>@endforeach
                            </select>
                        </label>
                        @if($selectedGroupId !== null)
                            <button type="button" wire:click="chooseGroup" wire:confirm="Reload the team list? Your team selections will be cleared." wire:loading.attr="disabled" class="btn btn-ghost sd-btn-quiet">
                                <x-scorer.icon name="refresh" /> Reload teams
                            </button>
                        @endif
                    </div>
                    @if($selectedGroupId === null)
                        <p class="sd-note">Choose a group to pick its teams.</p>
                    @else
                        @if($teams->count() < 2)<p class="sd-note">This group needs at least two approved teams before a match can be created.</p>@endif
                        <form id="create-match-form" wire:submit="createMatch" class="sd-form-stack">
                            <fieldset class="sd-fieldset">
                                <legend>Teams and format</legend>
                                <div class="sd-form-grid">
                                    @foreach(['team1_id' => 'Team 1', 'team2_id' => 'Team 2'] as $key => $label)
                                        <label class="form-control"><span class="label">{{ $label }}</span><select wire:model.live="newStructure.{{ $key }}" class="select w-full"><option value="">Select a team</option>
                                            @foreach($teams as $team)<option value="{{ $team['id'] }}">{{ $team['name'] }}</option>@endforeach
                                        </select></label>
                                    @endforeach
                                    <label class="form-control"><span class="label">Balls per over</span><select wire:model="newStructure.balls_per_over" class="select w-full"><option value="4">4</option><option value="6">6</option></select></label>
                                    <label class="form-control"><span class="label">Batting first (optional)</span><select wire:model="newSchedule.batting_first_id" class="select w-full"><option value="">Not decided</option>
                                        @foreach($teams as $team)@if(in_array((string) $team['id'], [(string) ($newStructure['team1_id'] ?? ''), (string) ($newStructure['team2_id'] ?? '')], true))<option value="{{ $team['id'] }}">{{ $team['name'] }}</option>@endif @endforeach
                                    </select></label>
                                </div>
                            </fieldset>
                            <fieldset class="sd-fieldset">
                                <legend>Schedule</legend>
                                <div class="sd-form-grid">
                                    <label class="form-control"><span class="label">Status</span><select wire:model="newSchedule.status" class="select w-full"><option value="upcoming">Upcoming</option><option value="live">In progress</option></select></label>
                                    <label class="form-control"><span class="label">Match number (optional)</span><input type="number" min="1" wire:model="newSchedule.display_number" class="input w-full" /></label>
                                    <label class="form-control"><span class="label">Scheduled time (Asia/Colombo)</span><input type="datetime-local" wire:model="newSchedule.scheduled_at" required class="input w-full" /></label>
                                    <label class="form-control"><span class="label">Venue</span><select wire:model="newSchedule.venue" required class="select w-full"><option value="">Choose a venue</option>@foreach(config('tournament.venues') as $venue)<option value="{{ $venue }}">{{ $venue }}</option>@endforeach</select></label>
                                </div>
                            </fieldset>
                        </form>
                    @endif
                @endif
            </div>
            <footer class="sd-dialog-foot">
                <button type="button" x-on:click="$el.closest('dialog').close()" class="btn btn-ghost">Cancel</button>
                @if($selectedGroupId !== null && $groups->isNotEmpty())
                    <button type="submit" form="create-match-form" wire:loading.attr="disabled" @disabled($teams->count() < 2) class="btn btn-primary">
                        <x-scorer.icon name="plus" /> Create match
                    </button>
                @endif
            </footer>
        </div>
    </dialog>

    {{-- Edit schedule popup, opened by openMatch() through "panel". --}}
    <dialog id="edit-schedule-dialog" class="sd-dialog" aria-labelledby="edit-schedule-heading" wire:ignore.self
            x-effect="panel === 'edit' ? ($el.open || $el.showModal()) : ($el.open && $el.close())"
            x-on:close="panel = ''" x-on:click.self="$el.close()">
        <div class="sd-dialog-panel">
            @if($editing)
                <header class="sd-dialog-head" wire:key="schedule-editor-{{ $editing->id }}">
                    <div>
                        <h2 id="edit-schedule-heading">{{ $fixtureEditable ? 'Edit scheduled fixture' : 'Edit schedule' }}</h2>
                        <p>{{ $editing->team1->team_name }} vs {{ $editing->team2->team_name }}</p>
                    </div>
                    <button type="button" x-on:click="$el.closest('dialog').close()" class="sd-icon-btn" aria-label="Close">
                        <x-scorer.icon name="x" />
                    </button>
                </header>
                <div class="sd-dialog-body">
                    @if($panel === 'edit')
                        @include('livewire.scorer.partials.dashboard-feedback')
                    @endif
                    <ul class="sd-lock-notes">
                        @if($fixtureEditable)
                            <li>Teams, round, group, bracket position and format can be corrected before play or scoring starts. Five overs and seven wickets remain the tournament rules.</li>
                        @else
                            <li>Teams, group and match format are protected once play or scoring starts. Discard unpublished drafts and return an unplayed match to Upcoming before changing its setup.</li>
                        @endif
                        @if(!$statusEditable)<li>Result status is locked in the schedule form.</li>@endif
                        @if(!$battingEditable)<li>Batting-first team is locked after result publication.</li>@endif
                    </ul>
                    @if($publishedResult)
                        <div class="sd-warning">
                            <p>Reopening removes this match from standings and player totals. Saved official figures and pending drafts remain. A dependent later-round fixture can block the change.</p>
                            @if($editing->status === 'finished')
                                <button type="button" wire:click="changeResultStatus('live')" wire:confirm="Reopen this match as in progress and remove its contributions from standings and player totals? Official figures and drafts are retained. Unsaved schedule edits will be cleared." wire:loading.attr="disabled" class="btn btn-outline btn-sm">
                                    <x-scorer.icon name="rotate-ccw" /> Reopen match
                                </button>
                            @else
                                <button type="button" wire:click="changeResultStatus('finished')" wire:confirm="Restore the stored official result and its summary contributions? Pending drafts will not be published. Unsaved schedule edits will be cleared." wire:loading.attr="disabled" class="btn btn-outline btn-sm">
                                    <x-scorer.icon name="check" /> Restore official result
                                </button>
                            @endif
                        </div>
                    @endif
                    <form id="edit-schedule-form" wire:submit="saveSchedule">
                        @if($fixtureEditable)
                            <fieldset class="sd-fieldset">
                                <legend>Teams and match format</legend>
                                <div class="sd-form-grid">
                                    <label><span class="label">Round</span><select wire:model.live="fixtureStructure.stage" class="select w-full">
                                        @foreach($stageLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                    </select></label>
                                    @if(($fixtureStructure['stage'] ?? 'G') === 'G')
                                        <label><span class="label">Group</span><select wire:model.live="fixtureStructure.group_id" class="select w-full"><option value="">Choose a group</option>
                                            @foreach($groups as $group)<option value="{{ $group['id'] }}">{{ $group['name'] }}</option>@endforeach
                                        </select></label>
                                    @elseif(($fixtureStructure['stage'] ?? '') !== 'PO')
                                        <label><span class="label">Bracket position</span><select wire:model="fixtureStructure.bracket_position" class="select w-full"><option value="">Choose position</option>
                                            @for($position = 1; $position <= (int) config('tournament.knockout_slots.'.($fixtureStructure['stage'] ?? ''), 0); $position++)<option value="{{ $position }}">{{ $position }}</option>@endfor
                                        </select></label>
                                    @endif
                                    @foreach(['team1_id' => 'Team 1', 'team2_id' => 'Team 2'] as $key => $label)
                                        <label><span class="label">{{ $label }}</span><select wire:model.live="fixtureStructure.{{ $key }}" class="select w-full"><option value="">Select a team</option>
                                            @foreach($fixtureTeams as $team)<option value="{{ $team['id'] }}">{{ $team['name'] }}</option>@endforeach
                                        </select></label>
                                    @endforeach
                                    <label><span class="label">Balls per over</span><select wire:model="fixtureStructure.balls_per_over" class="select w-full"><option value="4">4</option><option value="6">6</option></select></label>
                                </div>
                            </fieldset>
                        @endif
                        <div class="sd-form-grid">
                            @if($statusEditable)<label><span class="label">Match status</span><select wire:model="schedule.status" class="select w-full"><option value="upcoming">Upcoming</option><option value="live">In progress</option></select></label>@endif
                            @if($battingEditable)<label><span class="label">Batting first</span><select wire:model="schedule.batting_first_id" class="select w-full"><option value="">Not decided</option>
                                @if($fixtureEditable)
                                    @foreach($fixtureTeams as $team)@if(in_array((string) $team['id'], [(string) ($fixtureStructure['team1_id'] ?? ''), (string) ($fixtureStructure['team2_id'] ?? '')], true))<option value="{{ $team['id'] }}">{{ $team['name'] }}</option>@endif @endforeach
                                @else
                                    <option value="{{ $editing->team1_id }}">{{ $editing->team1->team_name }}</option><option value="{{ $editing->team2_id }}">{{ $editing->team2->team_name }}</option>
                                @endif
                            </select></label>@endif
                            <label><span class="label">Scheduled time (Asia/Colombo)</span><input type="datetime-local" wire:model="schedule.scheduled_at" required class="input w-full" /></label>
                            <label><span class="label">Venue</span><select wire:model="schedule.venue" required class="select w-full"><option value="">Choose a venue</option>@foreach(config('tournament.venues') as $venue)<option value="{{ $venue }}">{{ $venue }}</option>@endforeach</select></label>
                            <label><span class="label">Match number</span><input type="number" min="1" wire:model="schedule.display_number" class="input w-full" /></label>
                        </div>
                    </form>
                </div>
                <footer class="sd-dialog-foot">
                    @if($fixtureEditable)
                        <button type="button" wire:click="deleteFixture" wire:confirm="Delete this unused fixture? This cannot be undone. You can create a corrected fixture afterward." wire:loading.attr="disabled" class="btn btn-outline btn-error">
                            <x-scorer.icon name="trash" /> Delete unused fixture
                        </button>
                    @endif
                    <button type="button" wire:click="reloadMatch" wire:confirm="Discard unsaved schedule changes and load the latest saved details?" wire:loading.attr="disabled" class="btn btn-ghost sd-btn-quiet sd-foot-start">
                        <x-scorer.icon name="refresh" /> Reload this match
                    </button>
                    <button type="button" x-on:click="$el.closest('dialog').close()" class="btn btn-ghost">Cancel</button>
                    <button type="submit" form="edit-schedule-form" wire:loading.attr="disabled" class="btn btn-primary">
                        <x-scorer.icon name="check" /> {{ $fixtureEditable ? 'Save match details' : 'Save schedule' }}
                    </button>
                </footer>
            @endif
        </div>
    </dialog>

    <section class="sd-matches" aria-labelledby="match-list-heading">
        <div class="sd-matches-head">
            <h2 id="match-list-heading">Matches</h2>
            <p class="sd-count">Matches found: {{ $matches->total() }}. {{ $sortLabels[$filters['sort'] ?? 'upcoming_first'] }}.</p>
        </div>

        <form wire:submit="applyFilters" class="sd-filters" aria-label="Filter matches">
            <label><span class="label">Group filter</span><select wire:model="filterForm.group_id" class="select w-full"><option value="">All groups</option>@foreach($groups as $group)<option value="{{ $group['id'] }}">{{ $group['name'] }}</option>@endforeach</select></label>
            <label><span class="label">Stage filter</span><select wire:model="filterForm.stage" class="select w-full"><option value="">All stages</option>@foreach($stageLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
            <label><span class="label">Status filter</span><select wire:model="filterForm.status" class="select w-full"><option value="">All statuses</option><option value="upcoming">Upcoming</option><option value="live">In progress</option><option value="finished">Finished</option></select></label>
            <label><span class="label">Venue filter</span><select wire:model="filterForm.venue" class="select w-full"><option value="">All venues</option>@foreach(config('tournament.venues') as $venue)<option value="{{ $venue }}">{{ $venue }}</option>@endforeach</select></label>
            <label><span class="label">Sort by</span><select wire:model="filterForm.sort" class="select w-full">@foreach($sortLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
            <div class="sd-filter-actions">
                <button type="submit" wire:loading.attr="disabled" class="btn btn-secondary">
                    <x-scorer.icon name="filter" /> Apply filters
                </button>
                @if($activeFilters > 0)
                    <button type="button" wire:click="clearFilters" wire:loading.attr="disabled" class="btn btn-ghost sd-btn-quiet">
                        <x-scorer.icon name="x" /> Clear
                    </button>
                @endif
            </div>
        </form>

        <div class="sd-match-list">
            @forelse($matches as $match)
                @include('livewire.scorer.partials.dashboard-match-card', ['match' => $match])
            @empty
                <div class="sd-empty">
                    <x-scorer.icon name="ball" />
                    <p>No matches found for these filters. Change the filters or create a group match.</p>
                </div>
            @endforelse
        </div>
        {{ $matches->links() }}
    </section>
</div>

