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
    // '' or 'review': drives the review popup. UI state only; publishing still
    // requires the locked $review snapshot.
    public string $panel = '';

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
        $this->panel = 'review';
    }

    public function publishResult(): void
    {
        $this->resetErrorBag();
        $this->notice = '';
        if ($this->review === null) {
            throw ValidationException::withMessages(['tournament' => 'Review the current result before publishing.']);
        }
        app(MatchResultService::class)->publish($this->year(), $this->matchId, $this->review['version'], $this->review['draft_hash']);
        // Back to the match list with a toast (rendered by the scorer layout).
        session()->flash('scorer_toast', 'Team result published and group standings updated. Open Player figures, then review and publish player updates to update public Stats.');
        $this->redirectRoute('scorer.dashboard');
    }

    public function discardDraft(): void
    {
        $this->review = null;
        app(MatchResultService::class)->discardDraft($this->year(), $this->matchId, $this->version);
        $this->loadSaved();
        $this->notice = 'Draft discarded. Published results have not changed.';
    }

    public function returnToDraft(): void
    {
        $this->review = null;
        $this->panel = '';
        $this->resetErrorBag();
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
        $this->panel = '';
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

@php
    $sides = ['team1' => $match->team1, 'team2' => $match->team2];
    $locked = $match->structure_locked_at !== null || $match->result_published_at !== null;
    $resultLabels = ['win' => 'Win', 'tie' => 'Tie', 'no_result' => 'No result', 'abandoned' => 'Abandoned'];
    $methodLabels = ['normal' => 'Normal play', 'super_over' => 'Super Over', 'coin_toss' => 'Coin toss', 'official_decision' => 'Official decision', 'revised_target' => 'Revised target'];
@endphp
<div class="scorer-screen sr-screen" x-data="{ panel: $wire.entangle('panel') }">
    <x-scorer.match-header :match="$match" active="result" title="Match result" />

    <div class="scorer-feedback" aria-live="polite">
        @if($notice)<p role="status" class="alert alert-success">{{ $notice }}</p>@endif
        {{-- Actions live in the sticky bar at the bottom, so bring errors into view. --}}
        @if($errors->any() && $panel !== 'review')<div role="alert" class="alert alert-error sr-errors" wire:key="errors-{{ md5(implode('|', $errors->all())) }}"
            x-data x-init="$el.scrollIntoView({ block: 'center', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' })"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
    </div>

    {{-- What the public currently sees. --}}
    <section class="sr-published" aria-labelledby="official-result-heading">
        <div class="sr-published-head">
            <h2 id="official-result-heading">{{ $official ? 'Published result' : 'No published result' }}</h2>
            @if($hasDraft)<span class="sr-chip">Saved draft exists</span>@endif
        </div>
        @if($official)
            <dl class="sr-board">
                @foreach($sides as $side => $team)
                    <div @class(['sr-board-row', 'is-winner' => $official['winner_id'] === $team->id, 'is-loser' => $official['winner_id'] !== null && $official['winner_id'] !== $team->id])>
                        <dt><x-tournament.team-badge :name="$team->team_name" /><span>{{ $team->team_name }}</span></dt>
                        <dd><strong>{{ $official[$side.'_runs'] ?? 'Unknown' }} / {{ $official[$side.'_wickets'] ?? 'Unknown' }}</strong>
                            <span>{{ $official[$side.'_overs'] ?? '?' }} overs, {{ $official[$side.'_balls'] ?? '?' }} balls</span></dd>
                    </div>
                @endforeach
            </dl>
            <p class="sr-published-line">
                {{ $resultLabels[$official['result_type']] ?? str_replace('_', ' ', $official['result_type']) }}
                @if($official['result_method']) ({{ $methodLabels[$official['result_method']] ?? str_replace('_', ' ', $official['result_method']) }})@endif
                @if($official['winner_id']) · Winner: {{ $official['winner_id'] === $match->team1_id ? $match->team1->team_name : $match->team2->team_name }}@endif
            </p>
            @if($official['result_note'])<p class="sr-note-text whitespace-pre-wrap break-words">{{ $official['result_note'] }}</p>@endif
        @else
            <p class="sr-muted">No team result has been published.</p>
        @endif
    </section>

    <ol class="sr-steps" aria-label="Team result workflow">
        <li @if($panel !== 'review') aria-current="step" @endif><span>1</span> Enter figures</li>
        <li @if($panel === 'review') aria-current="step" @endif><span>2</span> Review and publish</li>
    </ol>

    <form id="result-form" wire:submit="saveDraft" class="sr-form" aria-labelledby="result-draft-heading">
        <div class="sr-form-intro">
            <h2 id="result-draft-heading">Result draft</h2>
            <p>Save incomplete details at any time. Blank means unknown; enter 0 for a known zero. Team results do not need player figures.</p>
        </div>

        <section class="sr-section" aria-labelledby="sr-outcome-heading">
            <h3 id="sr-outcome-heading" class="sr-section-title">Outcome</h3>
            <div class="sr-grid">
                <label><span class="label">Result</span><select wire:model.live="form.result_type" class="select w-full"><option value="">Choose result</option><option value="win">Win</option>@if($match->stage === 'G')<option value="tie">Tie</option><option value="no_result">No result</option><option value="abandoned">Abandoned</option>@endif</select></label>
                @if(($form['result_type'] ?? null) === 'win')
                    <label><span class="label">Deciding method</span><select wire:model.live="form.result_method" class="select w-full"><option value="">Choose method</option><option value="normal">Normal play</option><option value="super_over">Super Over</option><option value="coin_toss">Coin toss</option><option value="official_decision">Official decision</option><option value="revised_target">Revised target</option></select></label>
                @endif
            </div>
            @if(($form['result_type'] ?? null) === 'win')
                <fieldset class="sr-winner">
                    <legend class="label">Winner</legend>
                    <div class="sr-winner-options">
                        @foreach($sides as $team)
                            <label class="sr-winner-option">
                                <input type="radio" name="winner" value="{{ $team->id }}" wire:model="form.winner_id" class="sr-only">
                                <x-tournament.team-badge :name="$team->team_name" />
                                <span class="sr-winner-name">{{ $team->team_name }}</span>
                                <x-scorer.icon name="trophy" class="sr-winner-mark" />
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif
            <div class="sr-grid">
                <label><span class="label">Batting first</span><select wire:model.live="form.batting_first_id" @disabled($locked) class="select w-full"><option value="">Unknown</option><option value="{{ $match->team1_id }}">{{ $match->team1->team_name }}</option><option value="{{ $match->team2_id }}">{{ $match->team2->team_name }}</option></select></label>
            </div>
            @if($locked)<p class="sr-muted">Batting order and match format are locked after publication.</p>@endif
        </section>

        <section class="sr-section" aria-labelledby="sr-innings-heading">
            <h3 id="sr-innings-heading" class="sr-section-title">Innings</h3>
            <p class="sr-hint">For 4 overs and 3 balls, enter 4 complete overs and 3 balls in the current over. Use normal innings totals. Super Over figures are excluded.</p>
            <div class="sr-innings-grid">
                @foreach($sides as $side => $team)
                    <fieldset class="sr-innings">
                        <legend class="sr-innings-head">
                            <x-tournament.team-badge :name="$team->team_name" />
                            <span class="sr-innings-name">{{ $team->team_name }}<span class="sr-only">: normal innings</span></span>
                            @if((string) ($form['batting_first_id'] ?? '') === (string) $team->id)<span class="sr-chip">Bats first</span>@endif
                        </legend>
                        <div class="sr-score-row">
                            <label class="sr-big"><span class="label">Runs</span><input type="number" inputmode="numeric" min="0" max="2147483647" step="1" wire:model="form.{{ $side }}_runs" class="input" placeholder="-" /></label>
                            <span class="sr-sep" aria-hidden="true">/</span>
                            <label class="sr-big"><span class="label">Wickets</span><input type="number" inputmode="numeric" min="0" max="{{ $match->max_wickets }}" step="1" wire:model="form.{{ $side }}_wickets" class="input" placeholder="-" /></label>
                        </div>
                        <div class="sr-overs-row">
                            <label><span class="label">Complete overs</span><input type="number" inputmode="numeric" min="0" max="5" step="1" wire:model="form.{{ $side }}_overs" class="input" placeholder="-" /></label>
                            <span class="sr-sep" aria-hidden="true">.</span>
                            <label><span class="label">Balls in current over</span><input type="number" inputmode="numeric" min="0" max="{{ $match->balls_per_over - 1 }}" step="1" wire:model="form.{{ $side }}_balls" class="input" placeholder="-" /></label>
                            <span class="sr-unit" aria-hidden="true">ov</span>
                        </div>
                    </fieldset>
                @endforeach
            </div>
        </section>

        <section class="sr-section" aria-labelledby="sr-notes-heading">
            <h3 id="sr-notes-heading" class="sr-section-title">Notes and exceptions</h3>
            <label><span class="label">Official explanation (required for an exceptional decision)</span><textarea wire:model="form.result_note" maxlength="2000" rows="3" class="textarea w-full"></textarea></label>
            @if(($form['result_method'] ?? null) === 'revised_target')
                <fieldset class="sr-revised">
                    <legend>Official revised-target figures</legend>
                    <div class="sr-grid">
                        <label><span class="label">Revised target</span><input type="number" min="1" wire:model="form.revised_target" class="input w-full" /></label>
                        <label><span class="label">Include in NRR?</span><select wire:model.live="form.nrr_inputs.included" class="select w-full"><option value="">Choose official decision</option><option value="1">Yes, use official adjusted figures below</option><option value="0">No, exclude from NRR</option></select></label>
                    </div>
                    <label><span class="label">Reason for adjusted NRR figures or exclusion</span><textarea wire:model="form.nrr_inputs.reason" maxlength="1000" class="textarea w-full"></textarea></label>
                    @if(in_array($form['nrr_inputs']['included'] ?? null, [true, 1, '1'], true))
                        @foreach($sides as $side => $team)
                            <fieldset class="sr-grid sr-grid-3"><legend class="sr-revised-team">{{ $team->team_name }}: official NRR inputs</legend>
                                @foreach(['runs' => 'Runs', 'overs' => 'Complete overs', 'balls' => 'Balls in current over'] as $field => $label)<label><span class="label">{{ $label }}</span><input type="number" min="0" step="1" wire:model="form.nrr_inputs.{{ $side }}.{{ $field }}" class="input w-full" /></label>@endforeach
                            </fieldset>
                        @endforeach
                    @endif
                </fieldset>
            @endif
        </section>
    </form>

    {{-- Sticky action bar: always reachable while scrolling the form. --}}
    <div class="sr-actionbar">
        <div class="sr-actionbar-quiet">
            <button type="button" wire:click="reloadResult" wire:confirm="Replace unsaved changes with the latest saved details?" wire:loading.attr="disabled" class="btn btn-ghost btn-sm sd-btn-quiet">
                <x-scorer.icon name="refresh" /> Reload saved details
            </button>
            @if($hasDraft)
                <button type="button" wire:click="discardDraft" wire:confirm="Discard the saved result draft? The published result will stay unchanged." wire:loading.attr="disabled" class="btn btn-ghost btn-sm sd-btn-quiet">
                    <x-scorer.icon name="trash" /> Discard draft
                </button>
            @endif
        </div>
        <p wire:loading role="status" class="sr-saving">Updating result...</p>
        <div class="sr-actionbar-main">
            <button type="submit" form="result-form" wire:loading.attr="disabled" class="btn btn-outline">
                <x-scorer.icon name="save" /> Save draft
            </button>
            <button type="button" wire:click="reviewResult" wire:loading.attr="disabled" class="btn btn-primary">
                <x-scorer.icon name="eye" /> Review result
            </button>
        </div>
    </div>

    {{-- Review popup, opened by reviewResult() through "panel". --}}
    <dialog id="review-result-dialog" class="sd-dialog" aria-labelledby="review-result-heading" wire:ignore.self
            x-effect="panel === 'review' ? ($el.open || $el.showModal()) : ($el.open && $el.close())"
            x-on:close="panel = ''" x-on:click.self="$el.close()">
        <div class="sd-dialog-panel">
            @if($review)
                <header class="sd-dialog-head" wire:key="result-review">
                    <div>
                        <h2 id="review-result-heading">Review before publishing</h2>
                        <p>Check every figure. This is what the public will see.</p>
                    </div>
                    <button type="button" x-on:click="$el.closest('dialog').close()" class="sd-icon-btn" aria-label="Close">
                        <x-scorer.icon name="x" />
                    </button>
                </header>
                <div class="sd-dialog-body">
                    @if($errors->any() && $panel === 'review')<div role="alert" class="alert alert-error sr-errors" wire:key="review-errors-{{ md5(implode('|', $errors->all())) }}"
                        x-data x-init="$el.scrollIntoView({ block: 'nearest', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' })"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
                    <p class="sr-review-result"><x-scorer.icon :name="($review['result']['result_type'] ?? null) === 'win' ? 'trophy' : 'ball'" /> {{ $review['description'] }}</p>
                    <dl class="sr-board">
                        @foreach($sides as $side => $team)
                            @php($deliveries = $review['result'][$side.'_deliveries'])
                            @php($winner = $review['result']['winner_id'] ?? null)
                            <div @class(['sr-board-row', 'is-winner' => $winner === $team->id, 'is-loser' => $winner !== null && $winner !== $team->id])>
                                <dt><x-tournament.team-badge :name="$team->team_name" /><span>{{ $team->team_name }}</span></dt>
                                <dd><strong>{{ $review['result'][$side.'_runs'] ?? 'Unknown' }} / {{ $review['result'][$side.'_wickets'] ?? 'Unknown' }}</strong>
                                    <span>{{ $deliveries === null ? '?' : intdiv($deliveries, $match->balls_per_over) }} overs, {{ $deliveries === null ? '?' : $deliveries % $match->balls_per_over }} balls</span></dd>
                            </div>
                        @endforeach
                    </dl>
                    <ul class="sd-lock-notes">
                        @if($review['result']['batting_first_id'])<li>Batting first: {{ $review['result']['batting_first_id'] === $match->team1_id ? $match->team1->team_name : $match->team2->team_name }}</li>@endif
                        @if($review['result']['result_note'])<li class="whitespace-pre-wrap break-words">{{ $review['result']['result_note'] }}</li>@endif
                        @if($review['result']['nrr_inputs'])
                            <li>Revised target: {{ $review['result']['revised_target'] }}. NRR: {{ $review['result']['nrr_inputs']['included'] ? 'Included using official figures' : 'Excluded' }}.</li>
                            <li class="break-words">{{ $review['result']['nrr_inputs']['reason'] }}</li>
                            @if($review['result']['nrr_inputs']['included'])
                                @foreach($sides as $side => $team)
                                    @php($adjusted = $review['result']['nrr_inputs'][$side])
                                    <li>{{ $team->team_name }} NRR inputs: {{ $adjusted['runs'] }} runs, {{ intdiv($adjusted['deliveries'], $match->balls_per_over) }} overs, {{ $adjusted['deliveries'] % $match->balls_per_over }} balls.</li>
                                @endforeach
                            @endif
                        @endif
                    </ul>
                    <p class="sd-warning">Publishing updates the official team result and group standings. Any earlier published result remains official until this succeeds.</p>
                </div>
                <footer class="sd-dialog-foot">
                    <button type="button" wire:click="returnToDraft" wire:loading.attr="disabled" class="btn btn-ghost">
                        <x-scorer.icon name="arrow-left" /> Back to draft
                    </button>
                    <button type="button" wire:click="publishResult" wire:confirm="Publish this reviewed team result and update standings?" wire:loading.attr="disabled" class="btn btn-primary">
                        <x-scorer.icon name="send" /> Publish team result
                    </button>
                </footer>
            @endif
        </div>
    </dialog>
</div>

