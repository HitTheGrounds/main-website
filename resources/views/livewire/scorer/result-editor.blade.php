<?php

use App\Services\Tournament\MatchResultService;
use App\Services\Tournament\MatchSchedulingService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public int $matchId;
    #[Locked]
    public int $version;
    #[Locked]
    public ?array $review = null;
    #[Locked]
    public ?array $official = null;
    #[Locked]
    public bool $hasDraft = false;

    public array $form = [];
    public string $notice = '';

    public function mount(int $matchId): void
    {
        $this->matchId = $matchId;
        $this->loadSaved();
    }

    public function with(): array
    {
        return ['match' => app(MatchSchedulingService::class)->findForScheduling($this->year(), $this->matchId)];
    }

    public function updatedForm(mixed $value, ?string $key): void
    {
        $this->review = null;
        $this->notice = '';
        if ($key === 'result_type') {
            if ($value !== 'win') {
                $this->form['winner_id'] = null;
            }
            $this->form['result_method'] = in_array($value, ['no_result', 'abandoned'], true) ? null : 'normal';
            $this->form['revised_target'] = null;
            $this->form['nrr_inputs'] = null;
        }
        if ($key === 'result_method') {
            $this->form['revised_target'] = null;
            $this->form['nrr_inputs'] = $value === 'revised_target'
                ? ['included' => '', 'reason' => '', 'team1' => ['runs' => '', 'overs' => '', 'balls' => ''], 'team2' => ['runs' => '', 'overs' => '', 'balls' => '']]
                : null;
        }
        if ($key === 'nrr_inputs.included' && in_array($value, [false, 0, '0'], true)) {
            unset($this->form['nrr_inputs']['team1'], $this->form['nrr_inputs']['team2']);
        }
    }

    public function saveDraft(): void
    {
        $this->resetErrorBag();
        $this->review = null;
        $this->notice = '';
        $match = app(MatchResultService::class)->saveDraft($this->year(), $this->matchId, $this->version, $this->input());
        $this->version = $match->version;
        $this->hasDraft = true;
        $this->notice = 'Draft saved. Published results have not changed.';
    }

    public function reviewResult(): void
    {
        // Save what is on screen before reviewing, including any final edits.
        $this->saveDraft();
        $this->notice = '';
        $this->review = app(MatchResultService::class)->review($this->year(), $this->matchId);
    }

    public function publishResult(): void
    {
        $this->resetErrorBag();
        $this->notice = '';
        if ($this->review === null) {
            throw ValidationException::withMessages(['tournament' => 'Review the current result before publishing.']);
        }
        app(MatchResultService::class)->publish($this->year(), $this->matchId, $this->review['version'], $this->review['draft_hash']);
        $this->loadSaved();
        $this->notice = 'Team result published and group standings updated. Player figures can be entered separately later.';
    }

    public function discardDraft(): void
    {
        $this->review = null;
        app(MatchResultService::class)->discardDraft($this->year(), $this->matchId, $this->version);
        $this->loadSaved();
        $this->notice = 'Draft discarded. Published results have not changed.';
    }

    public function reloadResult(): void
    {
        $this->loadSaved();
        $this->notice = 'Latest saved result loaded.';
    }

    private function loadSaved(): void
    {
        $editor = app(MatchResultService::class)->editor($this->year(), $this->matchId);
        $this->resetErrorBag();
        $this->review = null;
        $this->version = $editor['version'];
        $this->hasDraft = $editor['has_draft'];
        $this->official = $editor['official'];
        $defaults = array_fill_keys(['result_type', 'result_method', 'winner_id', 'batting_first_id', 'result_note', 'revised_target', 'nrr_inputs'], null);
        foreach (['team1', 'team2'] as $team) {
            foreach (['runs', 'wickets', 'overs', 'balls'] as $field) {
                $defaults[$team.'_'.$field] = null;
            }
        }
        $defaults['batting_first_id'] = app(MatchSchedulingService::class)->findForScheduling($this->year(), $this->matchId)->batting_first_id;
        $this->form = array_replace($defaults, $editor['form']);
        if ($this->form['result_type'] === null) {
            $this->form['result_method'] ??= 'normal';
        }
    }

    private function input(): array
    {
        $normalize = function (array $input) use (&$normalize): array {
            foreach ($input as &$value) {
                $value = is_array($value) ? $normalize($value) : ($value === '' ? null : $value);
            }
            unset($value);
            return $input;
        };
        return $normalize($this->form);
    }

    private function year(): int
    {
        return (int) config('tournament.year');
    }
}; ?>

<div class="space-y-6">
    <header class="space-y-2 text-center">
        <h1 class="text-2xl font-bold sm:text-3xl">Match result</h1>
        <p>{{ $match->team1->team_name }} vs {{ $match->team2->team_name }} | {{ $match->group?->name }}</p>
        <p class="text-sm">{{ $match->balls_per_over }} balls per over | Five overs | Eleven wickets</p>
        <a href="{{ route('scorer.dashboard') }}" class="link">Back to matches</a>
    </header>
    <div aria-live="polite">
        @if($notice)<p role="status" class="alert alert-success">{{ $notice }}</p>@endif
        @if($errors->any())<div role="alert" class="alert alert-error"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
        <p wire:loading role="status" class="text-center text-sm">Updating result...</p>
    </div>
    <section class="card border border-base-300 bg-base-100" aria-labelledby="official-result-heading">
        <div class="card-body text-center">
            <h2 id="official-result-heading" class="text-xl font-bold">Published result</h2>
            @if($official)
                <p>{{ str_replace('_', ' ', $official['result_type']) }} @if($official['result_method']) ({{ str_replace('_', ' ', $official['result_method']) }}) @endif
                    @if($official['winner_id']) | Winner: {{ $official['winner_id'] === $match->team1_id ? $match->team1->team_name : $match->team2->team_name }} @endif</p>
                @foreach(['team1' => $match->team1->team_name, 'team2' => $match->team2->team_name] as $side => $name)
                    <p>{{ $name }}: {{ $official[$side.'_runs'] ?? 'Unknown' }} / {{ $official[$side.'_wickets'] ?? 'Unknown' }}
                        ({{ $official[$side.'_overs'] ?? '?' }} overs, {{ $official[$side.'_balls'] ?? '?' }} balls)</p>
                @endforeach
                @if($official['result_note'])<p class="whitespace-pre-wrap break-words">{{ $official['result_note'] }}</p>@endif
            @else<p>No team result has been published.</p>@endif
            @if($hasDraft)<p class="badge badge-outline mx-auto">Saved draft exists</p>@endif
        </div>
    </section>
    <section class="card border border-base-300 bg-base-100" aria-labelledby="result-draft-heading">
        <form wire:submit="saveDraft" class="card-body space-y-4">
            <h2 id="result-draft-heading" class="text-center text-xl font-bold">Result draft</h2>
            <p class="text-center text-sm">Save incomplete details at any time. Blank means unknown; enter 0 for a known zero. Team results do not need player figures.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <label><span class="label">Result</span><select wire:model.live="form.result_type" class="select w-full"><option value="">Choose result</option><option value="win">Win</option><option value="tie">Tie</option><option value="no_result">No result</option><option value="abandoned">Abandoned</option></select></label>
                @if(($form['result_type'] ?? null) === 'win')
                    <label><span class="label">Deciding method</span><select wire:model.live="form.result_method" class="select w-full"><option value="">Choose method</option><option value="normal">Normal play</option><option value="super_over">Super Over</option><option value="coin_toss">Coin toss</option><option value="official_decision">Official decision</option><option value="revised_target">Revised target</option></select></label>
                    <label><span class="label">Winner</span><select wire:model="form.winner_id" class="select w-full"><option value="">Choose winner</option><option value="{{ $match->team1_id }}">{{ $match->team1->team_name }}</option><option value="{{ $match->team2_id }}">{{ $match->team2->team_name }}</option></select></label>
                @endif
                <label><span class="label">Batting first</span><select wire:model="form.batting_first_id" @disabled($match->structure_locked_at !== null || $match->result_published_at !== null) class="select w-full"><option value="">Unknown</option><option value="{{ $match->team1_id }}">{{ $match->team1->team_name }}</option><option value="{{ $match->team2_id }}">{{ $match->team2->team_name }}</option></select></label>
            </div>
            @if($match->structure_locked_at !== null || $match->result_published_at !== null)<p class="text-sm">Batting order and match format are locked after publication.</p>@endif
            <div class="grid gap-4 md:grid-cols-2">
                @foreach(['team1' => $match->team1->team_name, 'team2' => $match->team2->team_name] as $side => $name)
                    <fieldset class="rounded-lg border border-base-300 p-4 space-y-3"><legend class="px-2 font-bold">{{ $name }}: normal innings</legend>
                        <div class="grid grid-cols-2 gap-3">
                            @foreach(['runs' => ['Runs', 2147483647], 'wickets' => ['Wickets', 11], 'overs' => ['Complete overs', 5], 'balls' => ['Remaining balls', $match->balls_per_over - 1]] as $field => [$label, $maximum])
                                <label><span class="label">{{ $label }}</span><input type="number" min="0" max="{{ $maximum }}" step="1" wire:model="form.{{ $side }}_{{ $field }}" class="input w-full" /></label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>
            <p class="text-sm text-center">Enter actual innings totals. Enter complete overs and remaining balls separately. Super Over figures are excluded.</p>
            <label><span class="label">Official explanation (required for an exceptional decision)</span><textarea wire:model="form.result_note" maxlength="2000" rows="3" class="textarea w-full"></textarea></label>
            @if(($form['result_method'] ?? null) === 'revised_target')
                <fieldset class="rounded-lg border border-base-300 p-4 space-y-4"><legend class="px-2 font-bold">Official revised-target figures</legend>
                    <label><span class="label">Revised target</span><input type="number" min="1" wire:model="form.revised_target" class="input w-full" /></label>
                    <label><span class="label">Include in NRR?</span><select wire:model.live="form.nrr_inputs.included" class="select w-full"><option value="">Choose official decision</option><option value="1">Yes, use official adjusted figures below</option><option value="0">No, exclude from NRR</option></select></label>
                    <label><span class="label">Reason for adjusted NRR figures or exclusion</span><textarea wire:model="form.nrr_inputs.reason" maxlength="1000" class="textarea w-full"></textarea></label>
                    @if(in_array($form['nrr_inputs']['included'] ?? null, [true, 1, '1'], true))
                        @foreach(['team1' => $match->team1->team_name, 'team2' => $match->team2->team_name] as $side => $name)
                            <fieldset class="grid gap-3 sm:grid-cols-3"><legend class="font-semibold">{{ $name }}: official NRR inputs</legend>
                                @foreach(['runs' => 'Runs', 'overs' => 'Complete overs', 'balls' => 'Remaining balls'] as $field => $label)<label><span class="label">{{ $label }}</span><input type="number" min="0" step="1" wire:model="form.nrr_inputs.{{ $side }}.{{ $field }}" class="input w-full" /></label>@endforeach
                            </fieldset>
                        @endforeach
                    @endif
                </fieldset>
            @endif
            <div class="flex flex-wrap justify-center gap-3">
                <button type="submit" wire:loading.attr="disabled" class="btn btn-outline">Save draft</button>
                <button type="button" wire:click="reviewResult" wire:loading.attr="disabled" class="btn btn-primary">Review result</button>
                <button type="button" wire:click="reloadResult" wire:confirm="Replace unsaved changes with the latest saved details?" wire:loading.attr="disabled" class="btn btn-ghost">Reload saved details</button>
                @if($hasDraft)<button type="button" wire:click="discardDraft" wire:confirm="Discard the saved result draft? The published result will stay unchanged." wire:loading.attr="disabled" class="btn btn-ghost">Discard draft</button>@endif
            </div>
        </form>
    </section>
    @if($review)
        <section class="card border border-primary bg-base-100" aria-labelledby="review-result-heading"><div class="card-body text-center space-y-3">
            <h2 id="review-result-heading" class="text-xl font-bold">Review before publishing</h2>
            <p class="font-semibold">{{ $review['description'] }}</p>
            @foreach(['team1' => $match->team1->team_name, 'team2' => $match->team2->team_name] as $side => $name)
                @php($deliveries = $review['result'][$side.'_deliveries'])
                <p>{{ $name }}: {{ $review['result'][$side.'_runs'] ?? 'Unknown' }} / {{ $review['result'][$side.'_wickets'] ?? 'Unknown' }}
                    ({{ $deliveries === null ? '?' : intdiv($deliveries, $match->balls_per_over) }} overs, {{ $deliveries === null ? '?' : $deliveries % $match->balls_per_over }} balls)</p>
            @endforeach
            @if($review['result']['batting_first_id'])<p>Batting first: {{ $review['result']['batting_first_id'] === $match->team1_id ? $match->team1->team_name : $match->team2->team_name }}</p>@endif
            @if($review['result']['result_note'])<p class="whitespace-pre-wrap break-words">{{ $review['result']['result_note'] }}</p>@endif
            @if($review['result']['nrr_inputs'])
                <p>Revised target: {{ $review['result']['revised_target'] }}. NRR: {{ $review['result']['nrr_inputs']['included'] ? 'Included using official figures' : 'Excluded' }}.</p>
                <p class="break-words">{{ $review['result']['nrr_inputs']['reason'] }}</p>
                @if($review['result']['nrr_inputs']['included'])
                    @foreach(['team1' => $match->team1->team_name, 'team2' => $match->team2->team_name] as $side => $name)
                        @php($adjusted = $review['result']['nrr_inputs'][$side])
                        <p>{{ $name }} NRR inputs: {{ $adjusted['runs'] }} runs, {{ intdiv($adjusted['deliveries'], $match->balls_per_over) }} overs, {{ $adjusted['deliveries'] % $match->balls_per_over }} balls.</p>
                    @endforeach
                @endif
            @endif
            <p class="text-sm">Publishing updates the official team result and group standings. Any earlier published result remains official until this succeeds.</p>
            <button type="button" wire:click="publishResult" wire:confirm="Publish this reviewed team result and update standings?" wire:loading.attr="disabled" class="btn btn-primary">Publish team result</button>
        </div></section>
    @endif
</div>
