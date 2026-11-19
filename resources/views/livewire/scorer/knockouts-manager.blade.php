<?php

use App\Helpers\CompanyAuth;
use App\Models\User;
use App\Services\Tournament\BracketSlotService;
use App\Services\Tournament\MatchSchedulingService;
use App\Services\Tournament\QualificationService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public array $selection = [];
    #[Locked]
    public array $round = [];
    #[Locked]
    public string $stage = 'QF';
    #[Locked]
    public ?array $moveReview = null;

    public array $selected = [];
    public array $fixture = ['team1_id' => '', 'team2_id' => '', 'bracket_position' => '', 'balls_per_over' => '4'];
    public string $notice = '';

    public function mount(): void
    {
        $this->reloadSelection();
        $this->chooseRound('QF');
    }

    public function with(): array
    {
        $this->authorizeScorer();
        $reserved = collect($this->round['positions'])->whereNotNull('match_id')
            ->flatMap(fn ($position) => [$position['team1']['id'], $position['team2']['id']])->all();

        return ['available' => collect($this->round['candidates'])->whereNotIn('id', $reserved),
            'roundNames' => ['QF' => 'Quarter-finals', 'SF' => 'Semi-finals', 'F' => 'Final']];
    }

    public function reloadSelection(): void
    {
        $this->selection = app(QualificationService::class)->selection($this->year());
        $this->selected = $this->selection['selected'];
        $this->resetErrorBag();
        $this->notice = '';
    }

    public function saveSelection(): void
    {
        $this->resetErrorBag();
        $this->notice = '';
        $this->selection = app(QualificationService::class)->save($this->year(), $this->selected, $this->selection['revision']);
        $this->selected = $this->selection['selected'];
        $this->notice = 'Advancing teams saved. Reload the round before choosing new fixtures.';
    }

    public function chooseRound(string $stage): void
    {
        // The service validates the stage and current permission before state changes.
        $round = app(MatchSchedulingService::class)->knockoutOptions($this->year(), $stage);
        $this->stage = $stage;
        $this->round = $round;
        $this->reset('fixture', 'moveReview', 'notice');
        $key = ['QF' => 'quarter_final', 'SF' => 'semi_final', 'F' => 'final'][$stage];
        $this->fixture['balls_per_over'] = (string) config('tournament.default_balls_per_over.'.$key);
        $this->resetErrorBag();
    }

    public function reloadRound(): void
    {
        $this->chooseRound($this->stage);
    }

    public function createMatch(): void
    {
        $this->resetErrorBag();
        $this->notice = '';
        // Server-held stage and revision cannot be replaced by browser fields.
        // Unknown fixture fields are rejected by the existing structure validator.
        $structure = array_merge($this->fixture, ['stage' => $this->stage, 'group_id' => null]);
        $match = app(MatchSchedulingService::class)->create($this->year(), 0, $structure, [], $this->round['revision']);
        $this->chooseRound($this->stage);
        $this->notice = 'Knockout match #'.$match->id.' created. Open its result or player figures below.';
    }

    public function prepareMove(int $matchId, int $position): void
    {
        $this->authorizeScorer();
        $this->resetErrorBag();
        $this->notice = '';
        $this->moveReview = null;
        $source = collect($this->round['fixtures'])->firstWhere('id', $matchId);
        $target = collect($this->round['positions'])->firstWhere('position', $position);
        if ($this->stage !== 'QF' || ! $source || ! $target || $source['bracket_position'] === $position) {
            throw ValidationException::withMessages(['tournament' => 'Choose a quarter-final and a different valid position.']);
        }
        $other = collect($this->round['fixtures'])->firstWhere('id', $target['match_id']);
        $this->moveReview = ['match_id' => $source['id'], 'version' => $source['version'],
            'from' => $source['bracket_position'], 'to' => $position,
            'occupant' => $other ? ['id' => $other['id'], 'version' => $other['version']] : null];
    }

    public function cancelMove(): void
    {
        $this->authorizeScorer();
        $this->moveReview = null;
    }

    public function moveMatch(): void
    {
        $this->authorizeScorer();
        $this->resetErrorBag();
        $this->notice = '';
        if ($this->moveReview === null) {
            throw ValidationException::withMessages(['tournament' => 'Review a quarter-final position change first.']);
        }
        $review = $this->moveReview;
        app(BracketSlotService::class)->move($this->year(), $review['match_id'], $review['version'], $review['to'], $review['occupant']);
        $this->chooseRound('QF');
        $this->notice = 'Quarter-final positions updated. Teams, results and later-round connections are preserved.';
    }

    private function authorizeScorer(): void
    {
        abort_unless(config('tournament.enabled') === true, 404);
        abort_unless(User::whereKey(CompanyAuth::user()?->id)->where('is_scorer', true)->exists(), 403);
    }

    private function year(): int
    {
        return (int) config('tournament.year');
    }
}; ?>

{{-- Two tabs, switched in the browser (both panels stay rendered for Livewire). --}}
<div class="scorer-screen space-y-5" x-data="{ tab: location.hash === '#bracket' ? 'bracket' : 'qualify', show(t) { this.tab = t; history.replaceState(null, '', t === 'bracket' ? '#bracket' : location.pathname); } }">
    <header class="scorer-heading">
        <h1 class="text-2xl font-bold sm:text-3xl">Qualification &amp; bracket</h1>
        <p>Tournament {{ $selection['year'] }}. Select advancing teams and choose each knockout matchup manually.</p>
    </header>
    <div class="scorer-feedback" aria-live="polite">
        @if($notice)<p role="status" class="alert alert-success">{{ $notice }}</p>@endif
        @if($errors->any())<div role="alert" class="alert alert-error"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
        <p wire:loading role="status" class="text-sm">Loading tournament details...</p>
    </div>

    <div class="ko-tabs" role="tablist" aria-label="Knockout tasks">
        <button type="button" role="tab" id="ko-tab-qualify" aria-controls="ko-panel-qualify" class="sr-tab" :class="tab === 'qualify' && 'is-active'" :aria-selected="tab === 'qualify'" x-on:click="show('qualify')">
            <span class="ko-step">1</span> Select advancing teams
        </button>
        <button type="button" role="tab" id="ko-tab-bracket" aria-controls="ko-panel-bracket" class="sr-tab" :class="tab === 'bracket' && 'is-active'" :aria-selected="tab === 'bracket'" x-on:click="show('bracket')">
            <span class="ko-step">2</span> Arrange knockout fixtures
        </button>
    </div>
    <section id="ko-panel-qualify" role="tabpanel" aria-labelledby="ko-tab-qualify" x-show="tab === 'qualify'" class="card border border-base-300 bg-base-100"><div class="card-body space-y-4">
        <h2 id="qualification-heading" tabindex="-1" class="text-xl font-bold">Advancing teams</h2>
        <p class="scorer-instruction" aria-live="polite">{{ count($selected) }} of {{ $selection['capacity'] }} places selected in this form. Saved: {{ count($selection['selected']) }}.</p>
        <p class="text-sm">Table position is guidance. You may select any approved team, including more than one from a group.</p>
        @if($selection['unequal_groups'])<p class="text-sm">Group sizes differ. Compare runner-up figures with care.</p>@endif
        <form wire:submit="saveSelection" class="space-y-4">
            <div class="grid gap-4 xl:grid-cols-2">
                @forelse($selection['groups'] as $group)
                    <fieldset wire:key="qualification-{{ $group['id'] }}" class="rounded-lg border border-base-300 p-4 space-y-3">
                        <legend class="px-2 text-lg font-bold">{{ $group['name'] }}</legend>
                        <details class="scorer-coverage">
                            <summary>{{ $group['coverage']['published'] }} of {{ $group['coverage']['expected'] }} fixtures published</summary>
                            <p>{{ $group['coverage']['missing'] }} not created; {{ $group['coverage']['unpublished'] }} awaiting publication.</p>
                            <p>{{ $group['coverage']['complete'] ? 'All group fixtures published.' : 'Incomplete group results. Selection is still allowed.' }}</p>
                            @if($group['tied'])<p>Joint positions exist. No team is chosen automatically.</p>@endif
                            @if($group['undefined_nrr'])<p>Some NRR values are unknown.</p>@endif
                        </details>
                        <ul class="scorer-choices">
                            @forelse($group['teams'] as $team)
                                <li wire:key="qualifier-{{ $team['id'] }}">
                                    <label class="scorer-choice">
                                        <input type="checkbox" wire:model.live="selected" value="{{ $team['id'] }}"
                                            aria-label="Select {{ $team['name'] }}" @disabled(!$team['approved'] && !$team['qualified']) class="checkbox" />
                                        <span class="scorer-choice-copy">
                                            <strong>{{ $team['name'] }}</strong>
                                            <span class="scorer-choice-meta">
                                                <span>Rank {{ $team['rank'] }}</span>
                                                <span>{{ $team['played'] }} played</span>
                                                <span>NRR {{ $team['nrr'] ?? 'Unknown' }}</span>
                                            </span>
                                            @if($team['qualified'])<span class="scorer-choice-state">Saved qualifier</span>@endif
                                            @if(!$team['approved'])<span class="scorer-choice-state">Not approved</span>@endif
                                        </span>
                                        <span class="scorer-choice-points"><strong>{{ $team['points'] }}</strong><small>pts</small></span>
                                    </label>
                                </li>
                            @empty
                                <li class="p-3 text-sm">No teams assigned.</li>
                            @endforelse
                        </ul>
                    </fieldset>
                @empty<p>No groups yet. Use <a href="{{ route('scorer.groups.index') }}" class="link">Group setup</a> to add groups and teams.</p>@endforelse
            </div>
            <div class="scorer-actions">
                <button type="submit" wire:loading.attr="disabled" class="btn btn-primary">Save advancing teams</button>
            <button type="button" wire:click="reloadSelection" wire:confirm="Discard unsaved selections and load the latest standings and saved choices?" wire:loading.attr="disabled" class="btn btn-outline btn-sm">Reload selection and guidance</button>
            </div>
            <p class="text-sm">An empty selection is allowed. A team already in a knockout fixture cannot be removed here. If another scorer changes the tournament, reload before saving.</p>
        </form>
        <details class="rounded-lg border border-base-300 p-4"><summary class="cursor-pointer font-bold">Runner-up guidance (points, then NRR)</summary>
            <p class="mt-3 text-sm">Provisional comparison only. Includes teams tied at the second position, including joint first. This list does not confirm qualification.</p>
            <div class="scorer-scroll overflow-x-auto" tabindex="0" role="region" aria-label="Scrollable figures"><table class="table w-full">
                <caption class="sr-only">Provisional cross-group runner-up comparison</caption>
                <thead><tr><th scope="col">Comparison rank</th><th scope="col">Team</th><th scope="col">Group</th><th scope="col">Group rank</th><th scope="col">Points</th><th scope="col">NRR</th><th scope="col">Notice</th></tr></thead>
                <tbody>@forelse($selection['runner_up_guidance'] as $team)<tr><td>{{ $team['rank'] }}</td><th scope="row">{{ $team['name'] }}</th><td>{{ $team['group_name'] }}</td><td>{{ $team['group_rank'] }}</td><td>{{ $team['points'] }}</td><td>{{ $team['nrr'] ?? 'Unknown' }}</td><td>{{ $team['provisional'] ? 'Incomplete, tied, unknown NRR or unequal groups' : 'Group results complete' }}</td></tr>
                @empty<tr><td colspan="7">No runner-up candidates yet.</td></tr>@endforelse</tbody>
            </table></div>
        </details>
    </div></section>

    <section id="ko-panel-bracket" role="tabpanel" aria-labelledby="ko-tab-bracket" x-show="tab === 'bracket'" x-cloak class="card border border-base-300 bg-base-100"><div class="card-body space-y-4">
        <h2 id="bracket-heading" tabindex="-1" class="text-xl font-bold">Knockout bracket</h2>
        <nav aria-label="Knockout rounds" class="flex flex-wrap gap-2">
            @foreach($roundNames as $key => $name)<button type="button" wire:click="chooseRound('{{ $key }}')" wire:confirm="Load this round? Unsaved fixture choices and position reviews will be cleared." wire:loading.attr="disabled" @if($stage === $key) aria-current="page" @endif class="btn btn-sm {{ $stage === $key ? 'btn-primary' : 'btn-outline' }}">{{ $name }}</button>@endforeach
        </nav>
        <h3 class="text-lg font-bold">{{ $roundNames[$stage] }}</h3>
        <button type="button" wire:click="reloadRound" wire:confirm="Load the latest round? Unsaved fixture choices and position reviews will be cleared." wire:loading.attr="disabled" class="btn btn-outline btn-sm">Reload round</button>
        <p class="text-sm">{{ $stage === 'QF' ? 'Choose from saved advancing teams.' : 'Choose only published winners from the preceding round.' }} Teams already scheduled in this round are excluded from new choices.</p>
        <div class="grid gap-4 md:grid-cols-2">
            @foreach($round['positions'] as $position)
                @php($match = collect($round['fixtures'])->firstWhere('id', $position['match_id']))
                <article wire:key="bracket-{{ $stage }}-{{ $position['position'] }}" class="rounded-lg border border-base-300 p-4 space-y-2">
                    <h4 class="font-bold">{{ rtrim($roundNames[$stage], 's') }} {{ $position['position'] }}</h4>
                    @if($match)
                        <p class="font-semibold">{{ $position['team1']['name'] ?? 'Team unavailable' }} vs {{ $position['team2']['name'] ?? 'Team unavailable' }}</p>
                        <p>{{ ['upcoming' => 'Upcoming', 'live' => 'In progress', 'finished' => 'Finished'][$match['status']] }} | Match #{{ $match['id'] }}</p>
                        @foreach(['team1', 'team2'] as $side)
                            @if($position[$side]['source_match_id'])<p class="text-sm">{{ $position[$side]['name'] }}: winner of {{ $stage === 'SF' ? 'QF' : 'SF' }} match #{{ $position[$side]['source_match_id'] }}</p>@endif
                            @if(!$position[$side]['eligible'])<p class="text-sm">{{ $position[$side]['name'] }} is currently ineligible. Reload and check approval or the preceding result.</p>@endif
                        @endforeach
                        @if($match['published'] && $match['status'] === 'finished' && $match['winner_id'])<p class="font-bold">{{ $stage === 'F' ? 'Champion' : 'Winner' }}: {{ $match['winner_id'] === $position['team1']['id'] ? $position['team1']['name'] : $position['team2']['name'] }}</p>@endif
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('scorer.dashboard', ['edit' => $match['id']]) }}" class="btn btn-outline btn-sm">Edit match</a>
                            <a href="{{ route('scorer.matches.result', ['matchId' => $match['id']]) }}" class="btn btn-primary btn-sm">Enter / review result</a>
                            <a href="{{ route('scorer.matches.players', ['matchId' => $match['id']]) }}" class="btn btn-outline btn-sm">Player figures</a>
                            <a href="{{ route('scorer.dashboard') }}" class="btn btn-ghost btn-sm">Schedule / status on dashboard</a>
                        </div>
                        @if($stage === 'QF')<details><summary class="cursor-pointer text-sm">Rearrange quarter-final position</summary><div class="mt-2 flex flex-wrap gap-2">
                            @foreach($round['positions'] as $target)@if($target['position'] !== $position['position'])<button type="button" wire:click="prepareMove({{ $match['id'] }}, {{ $target['position'] }})" wire:loading.attr="disabled" class="btn btn-outline btn-sm">{{ $target['match_id'] ? 'Swap with' : 'Move to' }} QF {{ $target['position'] }}</button>@endif @endforeach
                        </div></details>@endif
                    @else<p>To be confirmed</p>@endif
                </article>
            @endforeach
        </div>
        @if($moveReview)
            <div class="rounded-lg border border-primary p-4 space-y-3">
                <h3 class="font-bold">Review position change</h3>
                <p>Match #{{ $moveReview['match_id'] }}: QF {{ $moveReview['from'] }} to QF {{ $moveReview['to'] }}.
                    @if($moveReview['occupant'])Match #{{ $moveReview['occupant']['id'] }} will move to QF {{ $moveReview['from'] }}.@endif</p>
                <p class="text-sm">Only display positions change. Teams, scores and later-round connections stay the same.</p>
                <button type="button" wire:click="moveMatch" wire:loading.attr="disabled" class="btn btn-primary">Confirm position change</button>
                <button type="button" wire:click="cancelMove" wire:loading.attr="disabled" class="btn btn-ghost">Cancel</button>
            </div>
        @endif
        @if(collect($round['positions'])->contains(fn ($position) => $position['match_id'] === null))
            <form wire:submit="createMatch" class="rounded-lg border border-base-300 p-4 space-y-4">
                <h3 class="font-bold">Create {{ $stage }} match</h3>
                @if($available->count() < 2)<p class="text-center">At least two eligible, unscheduled teams are needed. Save advancing teams or publish preceding-round results, then reload this round.</p>@endif
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach(['team1_id' => 'Team 1', 'team2_id' => 'Team 2'] as $key => $label)
                        <label><span class="label">{{ $label }}</span><select wire:model="fixture.{{ $key }}" class="select w-full"><option value="">Choose a team</option>@foreach($available as $team)<option value="{{ $team['id'] }}">{{ $team['name'] }}</option>@endforeach</select></label>
                    @endforeach
                    <label><span class="label">Bracket position</span><select wire:model="fixture.bracket_position" class="select w-full"><option value="">Choose a position</option>@foreach($round['positions'] as $position)@if($position['match_id'] === null)<option value="{{ $position['position'] }}">{{ $stage }} {{ $position['position'] }}</option>@endif @endforeach</select></label>
                    <label><span class="label">Balls per over</span><select wire:model="fixture.balls_per_over" class="select w-full"><option value="4">4</option><option value="6">6</option></select></label>
                </div>
                <p class="text-sm">Five overs per innings. Seven wickets. Creates an upcoming match without scores. Schedule and batting-first details can be entered on the dashboard.</p>
                <button type="submit" wire:loading.attr="disabled" @disabled($available->count() < 2) class="btn btn-primary">Create knockout match</button>
            </form>
        @else<p class="text-center">All positions in this round are filled.</p>@endif
    </div></section>
</div>
