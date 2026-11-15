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
    public array $filterForm = ['group_id' => '', 'stage' => '', 'status' => ''];
    public string $notice = '';

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

    public function with(): array
    {
        $setup = app(TournamentGroupService::class)->setup($this->year());
        $teams = $setup['groups']->firstWhere('id', $this->selectedGroupId)['teams'] ?? collect();
        $approvedIds = Team::whereIn('id', $teams->pluck('id'))->where('approved', true)->pluck('id');

        return [
            'groups' => $setup['groups'],
            'teams' => $teams->whereIn('id', $approvedIds),
            'matches' => app(MatchSchedulingService::class)->listing($this->year(), $this->filters),
            'editing' => $this->editingId === null ? null : app(MatchSchedulingService::class)->findForScheduling($this->year(), $this->editingId),
            'year' => $this->year(),
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
        if ($this->selectedGroupId === null || $this->groupVersion === null) {
            throw ValidationException::withMessages(['group_id' => 'Choose a group before creating a match.']);
        }
        $structure = array_merge($this->newStructure, ['stage' => 'G', 'group_id' => $this->selectedGroupId, 'bracket_position' => null]);
        $match = app(MatchSchedulingService::class)->create($this->year(), $this->groupVersion, $structure, $this->scheduleInput($this->newSchedule));
        $this->reset('newStructure', 'newSchedule', 'filters', 'filterForm');
        $this->resetPage();
        $this->openMatch($match->id);
        $this->notice = 'Match created. Scores remain empty until results are entered and published.';
    }

    public function openMatch(int $id): void
    {
        $match = app(MatchSchedulingService::class)->findForScheduling($this->year(), $id);
        $this->resetErrorBag();
        $this->notice = '';
        $this->editingId = $match->id;
        $this->editingVersion = $match->version;
        $this->statusEditable = $match->status !== 'finished'
            && $match->structure_locked_at === null && $match->result_published_at === null
            && $match->player_stats_published_at === null && $match->result_type === null && $match->winner_id === null;
        $this->publishedResult = $match->result_published_at !== null;
        $this->battingEditable = $match->structure_locked_at === null && $match->result_published_at === null;
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
        $input = $this->scheduleInput($this->schedule);
        if (! $this->statusEditable) {
            unset($input['status']);
        }
        if (! $this->battingEditable) {
            unset($input['batting_first_id']);
        }
        $match = app(MatchSchedulingService::class)->updateSchedule($this->year(), $this->editingId, $this->editingVersion, $input);
        $this->openMatch($match->id);
        $this->notice = 'Match schedule saved.';
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
        $filters = array_map(fn ($value) => $value === '' ? null : $value, $this->filterForm);
        app(MatchSchedulingService::class)->listing($this->year(), $filters);
        $this->filters = $filters;
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

<div class="space-y-6">
    <div class="space-y-2 text-center">
        <h1 class="text-2xl font-bold sm:text-3xl">Scorer dashboard</h1>
        <p class="text-sm text-base-content/70">Tournament {{ $year }}: create matches and manage their schedules.</p>
        <p class="text-sm text-base-content/70">Enter team results and player figures below. Choose knockout fixtures in Qualification &amp; bracket.</p>
        <p wire:loading role="status" class="text-sm">Loading match details...</p>
    </div>
    <div aria-live="polite">
        @if($notice)<p role="status" class="alert alert-success">{{ $notice }}</p>@endif
        @if($errors->any())
            <div role="alert" class="alert alert-error"><ul>
                @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul></div>
        @endif
    </div>

    <section class="card border border-base-300 bg-base-100 shadow-xs" aria-labelledby="create-match-heading">
        <div class="card-body space-y-4">
            <h2 id="create-match-heading" class="text-center text-xl font-bold">Create group match</h2>
            @if($groups->isEmpty())
                <p class="text-center">No groups are available. Create groups and assign approved teams in <a href="{{ route('scorer.groups.index') }}" class="link">Group setup</a>.</p>
            @else
                <label class="form-control"><span class="label">Group</span>
                    <select wire:model.live="groupChoice" class="select w-full"><option value="">Choose a group</option>
                        @foreach($groups as $group)<option value="{{ $group['id'] }}">{{ $group['name'] }} ({{ $group['team_count'] }} teams)</option>@endforeach
                    </select>
                </label>
                @if($selectedGroupId !== null)
                    <button type="button" wire:click="chooseGroup" wire:confirm="Reload the team list? Your team selections will be cleared." wire:loading.attr="disabled" class="btn btn-ghost btn-sm">Reload group teams</button>
                    @if($teams->count() < 2)<p class="text-center">This group needs at least two approved teams before a match can be created.</p>@endif
                    <form wire:submit="createMatch" class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach(['team1_id' => 'Team 1', 'team2_id' => 'Team 2'] as $key => $label)
                                <label class="form-control"><span class="label">{{ $label }}</span><select wire:model.live="newStructure.{{ $key }}" class="select w-full"><option value="">Select a team</option>
                                    @foreach($teams as $team)<option value="{{ $team['id'] }}">{{ $team['name'] }}</option>@endforeach
                                </select></label>
                            @endforeach
                            <label class="form-control"><span class="label">Balls per over</span><select wire:model="newStructure.balls_per_over" class="select w-full"><option value="4">4</option><option value="6">6</option></select></label>
                            <label class="form-control"><span class="label">Status</span><select wire:model="newSchedule.status" class="select w-full"><option value="upcoming">Upcoming</option><option value="live">In progress</option></select></label>
                            <label class="form-control"><span class="label">Scheduled time (Asia/Colombo, optional)</span><input type="datetime-local" wire:model="newSchedule.scheduled_at" class="input w-full" /></label>
                            <label class="form-control"><span class="label">Venue (optional)</span><input wire:model="newSchedule.venue" maxlength="255" class="input w-full" /></label>
                            <label class="form-control"><span class="label">Match number (optional)</span><input type="number" min="1" wire:model="newSchedule.display_number" class="input w-full" /></label>
                            <label class="form-control"><span class="label">Batting first (optional)</span><select wire:model="newSchedule.batting_first_id" class="select w-full"><option value="">Not decided</option>
                                @foreach($teams as $team)@if(in_array((string) $team['id'], [(string) ($newStructure['team1_id'] ?? ''), (string) ($newStructure['team2_id'] ?? '')], true))<option value="{{ $team['id'] }}">{{ $team['name'] }}</option>@endif @endforeach
                            </select></label>
                        </div>
                        <p class="text-center text-sm">Five overs per innings. Eleven wickets. No scores are added at creation.</p>
                        <button type="submit" wire:loading.attr="disabled" @disabled($teams->count() < 2) class="btn btn-primary w-full sm:w-auto">Create match</button>
                    </form>
                @endif
            @endif
        </div>
    </section>

    <section class="card border border-base-300 bg-base-100 shadow-xs" aria-labelledby="match-list-heading">
        <div class="card-body space-y-4">
            <h2 id="match-list-heading" class="text-center text-xl font-bold">Matches</h2>
            <form wire:submit="applyFilters" class="grid gap-3 sm:grid-cols-4">
                <label><span class="label">Group filter</span><select wire:model="filterForm.group_id" class="select w-full"><option value="">All groups</option>@foreach($groups as $group)<option value="{{ $group['id'] }}">{{ $group['name'] }}</option>@endforeach</select></label>
                <label><span class="label">Stage filter</span><select wire:model="filterForm.stage" class="select w-full"><option value="">All stages</option><option value="G">Group</option><option value="QF">Quarter-final</option><option value="SF">Semi-final</option><option value="F">Final</option></select></label>
                <label><span class="label">Status filter</span><select wire:model="filterForm.status" class="select w-full"><option value="">All statuses</option><option value="upcoming">Upcoming</option><option value="live">In progress</option><option value="finished">Finished</option></select></label>
                <button type="submit" wire:loading.attr="disabled" class="btn btn-outline self-end">Apply filters / reload</button>
            </form>
            <p class="text-center text-sm text-base-content/70">Matches found: {{ $matches->total() }}. Newest first.</p>
            <div class="grid gap-4 md:grid-cols-2">
                @forelse($matches as $match)
                    <article wire:key="match-{{ $match->id }}" class="rounded-lg border border-base-300 p-4 text-center space-y-2">
                        <h3 class="font-bold">{{ $match->team1->team_name }} vs {{ $match->team2->team_name }}</h3>
                        <p>{{ $match->group?->name ?? $match->stage }} @if($match->display_number !== null) | Match {{ $match->display_number }}@endif</p>
                        <p><span class="badge badge-outline">{{ ['upcoming' => 'Upcoming', 'live' => 'In progress', 'finished' => 'Finished'][$match->status] }}</span></p>
                        <p class="text-sm">{{ $match->balls_per_over }} balls per over | {{ $match->overs_per_innings }} overs | {{ $match->max_wickets }} wickets</p>
                        <p class="text-sm">{{ $match->scheduled_at?->setTimezone(config('tournament.timezone'))->format('d M Y, H:i') ?? 'Time not set' }} | {{ $match->venue ?? 'Venue not set' }}</p>
                        <button type="button" wire:click="openMatch({{ $match->id }})" wire:loading.attr="disabled" class="btn btn-outline btn-sm">Edit schedule</button>
                            <a href="{{ route('scorer.matches.result', ['matchId' => $match->id]) }}" class="btn btn-primary btn-sm">Enter / review result</a>
                            <a href="{{ route('scorer.matches.players', ['matchId' => $match->id]) }}" class="btn btn-outline btn-sm">Player figures</a>
                    </article>
                @empty
                    <p class="text-center md:col-span-2">No matches found for these filters. Create a group match above or change the filters.</p>
                @endforelse
            </div>
            {{ $matches->links() }}
        </div>
    </section>

    @if($editing)
        <section class="card border border-base-300 bg-base-100 shadow-xs" aria-labelledby="edit-schedule-heading">
            <div class="card-body space-y-4">
                <h2 id="edit-schedule-heading" class="text-center text-xl font-bold">Edit schedule: {{ $editing->team1->team_name }} vs {{ $editing->team2->team_name }}</h2>
                <p class="text-center text-sm">Teams, group and match format cannot be changed in this schedule form.</p>
                @if(!$statusEditable)<p class="text-center text-sm">Result status is locked in the schedule form.</p>@endif
                @if(!$battingEditable)<p class="text-center text-sm">Batting-first team is locked after result publication.</p>@endif
                @if($publishedResult)
                    <div class="rounded-lg border border-base-300 p-4 text-center space-y-3">
                        <p class="text-sm">Reopening removes this match from standings and player totals. Saved official figures and pending drafts remain. A dependent later-round fixture can block the change.</p>
                        @if($editing->status === 'finished')
                            <button type="button" wire:click="changeResultStatus('live')" wire:confirm="Reopen this match as in progress and remove its contributions from standings and player totals? Official figures and drafts are retained. Unsaved schedule edits will be cleared." wire:loading.attr="disabled" class="btn btn-outline">Reopen match</button>
                        @else
                            <button type="button" wire:click="changeResultStatus('finished')" wire:confirm="Restore the stored official result and its summary contributions? Pending drafts will not be published. Unsaved schedule edits will be cleared." wire:loading.attr="disabled" class="btn btn-outline">Restore official result</button>
                        @endif
                    </div>
                @endif
                <button type="button" wire:click="reloadMatch" wire:confirm="Discard unsaved schedule changes and load the latest saved details?" wire:loading.attr="disabled" class="btn btn-ghost btn-sm">Reload this match</button>
                <form wire:submit="saveSchedule" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        @if($statusEditable)<label><span class="label">Match status</span><select wire:model="schedule.status" class="select w-full"><option value="upcoming">Upcoming</option><option value="live">In progress</option></select></label>@endif
                        @if($battingEditable)<label><span class="label">Batting first</span><select wire:model="schedule.batting_first_id" class="select w-full"><option value="">Not decided</option><option value="{{ $editing->team1_id }}">{{ $editing->team1->team_name }}</option><option value="{{ $editing->team2_id }}">{{ $editing->team2->team_name }}</option></select></label>@endif
                        <label><span class="label">Scheduled time (Asia/Colombo)</span><input type="datetime-local" wire:model="schedule.scheduled_at" class="input w-full" /></label>
                        <label><span class="label">Venue</span><input wire:model="schedule.venue" maxlength="255" class="input w-full" /></label>
                        <label><span class="label">Match number</span><input type="number" min="1" wire:model="schedule.display_number" class="input w-full" /></label>
                    </div>
                    <button type="submit" wire:loading.attr="disabled" class="btn btn-primary w-full sm:w-auto">Save schedule</button>
                </form>
            </div>
        </section>
    @endif
</div>
