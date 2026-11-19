<?php

use App\Services\Tournament\ScorerStandingsService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public ?int $groupId = null;
    #[Locked]
    public ?int $groupVersion = null;
    #[Locked]
    public ?int $teamId = null;

    public string $points = '';
    public string $reason = '';
    public string $notice = '';

    public function with(): array
    {
        return ['groups' => app(ScorerStandingsService::class)->listing($this->year())];
    }

    public function openAdjustment(int $groupId, int $teamId): void
    {
        $groups = collect(app(ScorerStandingsService::class)->listing($this->year()));
        $group = $groups->firstWhere('id', $groupId);
        if (! $group || ! collect($group['teams'])->contains('id', $teamId)) {
            throw ValidationException::withMessages(['tournament' => 'Select a team assigned to this tournament group.']);
        }
        $this->resetErrorBag();
        $this->reset('points', 'reason', 'notice');
        $this->groupId = $groupId;
        $this->groupVersion = $group['version'];
        $this->teamId = $teamId;
    }

    public function saveAdjustment(): void
    {
        $this->resetErrorBag();
        $this->notice = '';
        if ($this->groupId === null || $this->groupVersion === null || $this->teamId === null) {
            throw ValidationException::withMessages(['tournament' => 'Choose a team before adding an adjustment.']);
        }
        app(ScorerStandingsService::class)->adjust($this->year(), $this->groupId, $this->groupVersion,
            $this->teamId, ['points' => $this->points, 'reason' => $this->reason]);
        $this->reset('groupId', 'groupVersion', 'teamId', 'points', 'reason');
        $this->notice = 'Official point adjustment recorded. Standings updated.';
    }

    public function closeAdjustment(): void
    {
        $this->resetErrorBag();
        $this->reset('groupId', 'groupVersion', 'teamId', 'points', 'reason');
    }

    private function year(): int
    {
        return (int) config('tournament.year');
    }
}; ?>

@php
    // Stored NRR keeps 6 decimals for ranking; show 4, like the public Table tab.
    $formatNrr = fn ($nrr) => $nrr === null ? '-'
        : (string) \Brick\Math\BigDecimal::of((string) $nrr)->toScale(4, \Brick\Math\RoundingMode::HALF_UP);
    $adjustGroup = $groupId !== null ? collect($groups)->firstWhere('id', $groupId) : null;
    $adjustTeam = $adjustGroup ? collect($adjustGroup['teams'])->firstWhere('id', $teamId) : null;
@endphp
<div class="scorer-screen sst-screen space-y-5">
    <header class="scorer-heading sd-heading">
        <div class="sd-heading-copy">
            <h1 class="text-2xl font-bold sm:text-3xl">Group standings</h1>
            <p class="text-sm">Published results, ranked by points then net run rate. Equal records remain tied.</p>
        </div>
        <div class="sg-heading-actions">
            <a href="{{ route('scorer.knockouts') }}" class="btn btn-ghost sd-btn-quiet"><x-scorer.icon name="trophy" /> Choose advancing teams</a>
            <button type="button" wire:click="$refresh" wire:loading.attr="disabled" class="btn btn-outline"><x-scorer.icon name="refresh" /> Refresh standings</button>
        </div>
        <p wire:loading role="status" class="sd-loading">Updating standings...</p>
    </header>

    @if($groupId === null)
        <div class="scorer-feedback" aria-live="polite">
            @if($notice)<p role="status" class="alert alert-success">{{ $notice }}</p>@endif
            @if($errors->any())<div role="alert" class="alert alert-error"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
        </div>
    @endif

    @if(count($groups) > 1)
        <nav class="sst-jump" aria-label="Jump to group standings">
            @foreach($groups as $group)
                <a href="#group-heading-{{ $group['id'] }}">{{ $group['name'] }}</a>
            @endforeach
        </nav>
    @endif

    <div class="sst-groups">
    @forelse($groups as $group)
        <section wire:key="standings-{{ $group['id'] }}" class="sst-panel" aria-labelledby="group-heading-{{ $group['id'] }}">
            <header class="sst-panel-head">
                <h2 id="group-heading-{{ $group['id'] }}" tabindex="-1">{{ $group['name'] }}</h2>
                <span @class(['sr-chip', 'sst-chip-done' => $group['coverage']['complete']])>{{ $group['coverage']['published'] }} / {{ $group['coverage']['expected'] }} fixtures published</span>
            </header>
            <div class="sst-scroll" tabindex="0" role="region" aria-label="{{ $group['name'] }} standings, scrollable">
                <table class="sst-table">
                    <caption class="sr-only">{{ $group['name'] }} published standings</caption>
                    <thead><tr>
                        <th scope="col"><abbr title="Position">#</abbr></th>
                        <th scope="col">Team</th>
                        <th scope="col" class="sst-detail">Played</th><th scope="col" class="sst-detail">Won</th><th scope="col" class="sst-detail">Lost</th>
                        <th scope="col" class="sst-detail">Tied</th><th scope="col" class="sst-detail">No result</th>
                        <th scope="col" class="sst-detail">Base points</th><th scope="col" class="sst-detail">Adjustment</th>
                        <th scope="col"><abbr title="Points">Pts</abbr></th><th scope="col">NRR</th>
                        <th scope="col"><span class="sr-only">Action</span></th>
                    </tr></thead>
                    <tbody>@forelse($group['teams'] as $team)
                        @php($adjustment = $team['points'] - $team['base_points'])
                        <tr wire:key="standing-team-{{ $group['id'] }}-{{ $team['id'] }}">
                            <td class="sst-rank">{{ $team['rank'] }}</td>
                            <th scope="row">
                                <span class="tc-table-team">
                                    <x-tournament.team-badge :name="$team['name']" />
                                    <span class="tc-table-team-text">
                                        <span class="sst-team-name">{{ $team['name'] }}</span>
                                        <span class="sst-small-record" aria-hidden="true">P {{ $team['played'] }} · W {{ $team['wins'] }} · L {{ $team['losses'] }} · T {{ $team['ties'] }} · NR {{ $team['no_results'] }}@if($adjustment) · Adj {{ $adjustment > 0 ? '+' : '' }}{{ $adjustment }}@endif</span>
                                    </span>
                                </span>
                                <details class="scorer-record">
                                    <summary>Match record</summary>
                                    <dl>
                                        @foreach(['played' => 'Played', 'wins' => 'Won', 'losses' => 'Lost', 'ties' => 'Tied', 'no_results' => 'No result', 'base_points' => 'Base points'] as $field => $label)
                                            <div><dt>{{ $label }}</dt><dd>{{ $team[$field] }}</dd></div>
                                        @endforeach
                                        <div><dt>Adjustment</dt><dd>{{ $adjustment }}</dd></div>
                                    </dl>
                                </details>
                            </th>
                            @foreach(['played', 'wins', 'losses', 'ties', 'no_results', 'base_points'] as $field)<td class="sst-detail">{{ $team[$field] }}</td>@endforeach
                            <td class="sst-detail @if($adjustment) sst-adjusted @endif">{{ $adjustment }}</td>
                            <td class="sst-points">{{ $team['points'] }}</td>
                            <td class="sst-nrr">{{ $formatNrr($team['nrr']) }}</td>
                            <td class="sst-action">
                                <button type="button" wire:click="openAdjustment({{ $group['id'] }}, {{ $team['id'] }})" wire:loading.attr="disabled" class="btn btn-ghost btn-sm sd-btn-quiet" aria-haspopup="dialog">
                                    <x-scorer.icon name="pencil" /><span class="sst-action-label">Adjust points</span><span class="sr-only"> for {{ $team['name'] }}</span>
                                </button>
                            </td>
                        </tr>
                    @empty<tr><td colspan="12">No teams assigned.</td></tr>@endforelse</tbody>
                </table>
            </div>
            <div class="sst-foot">
                <details class="scorer-coverage">
                    <summary>Fixture coverage and NRR</summary>
                    <p class="text-sm">{{ $group['coverage']['published'] }} of {{ $group['coverage']['expected'] }} expected fixtures published.
                        {{ $group['coverage']['missing'] }} fixtures not created. {{ $group['coverage']['unpublished'] }} created fixtures awaiting publication.</p>
                    <p class="text-sm">{{ $group['coverage']['complete'] ? 'All group fixtures published.' : 'Group fixture coverage is incomplete.' }}</p>
                    <p class="text-sm">NRR is '-' until both rate denominators are known. Abandoned/no-result matches are excluded from NRR.</p>
                </details>
                @foreach($group['teams'] as $team)
                    @if($team['adjustments'])<details class="scorer-coverage"><summary>{{ $team['name'] }}: point adjustment history</summary>
                        <ul class="sst-history">@foreach($team['adjustments'] as $adjustment)<li class="break-words"><strong>{{ $adjustment['points'] > 0 ? '+' : '' }}{{ $adjustment['points'] }} points:</strong> {{ $adjustment['reason'] }}
                            @if(isset($adjustment['actor_id'])) | Scorer #{{ $adjustment['actor_id'] }} @endif
                            @if(isset($adjustment['recorded_at'])) | {{ $adjustment['recorded_at'] }} @endif</li>@endforeach</ul>
                    </details>@endif
                @endforeach
            </div>
        </section>
    @empty<p class="sd-empty">No tournament groups yet. Add groups in <a href="{{ route('scorer.groups.index') }}" class="link">Group setup</a>.</p>@endforelse
    </div>

    {{-- Adjust points popup: open while a team is chosen ($groupId). --}}
    <dialog id="adjust-points-dialog" class="sd-dialog sg-dialog-sm" aria-labelledby="adjust-heading" wire:ignore.self
            x-data
            x-effect="$wire.groupId !== null ? ($el.open || $el.showModal()) : ($el.open && $el.close())"
            x-on:cancel.prevent="$wire.closeAdjustment()" x-on:click.self="$wire.closeAdjustment()">
        @if($groupId !== null)
            <form wire:key="adjust-{{ $groupId }}-{{ $teamId }}" wire:submit="saveAdjustment" wire:confirm="Record this official point adjustment?" class="sd-dialog-panel">
                <header class="sd-dialog-head">
                    <div class="sp-dialog-title">
                        <x-tournament.team-badge :name="$adjustTeam['name'] ?? ''" />
                        <div>
                            <h2 id="adjust-heading">Adjust points: {{ $adjustTeam['name'] ?? 'Team no longer assigned' }}</h2>
                            <p>{{ $adjustGroup['name'] ?? '' }} · Currently {{ $adjustTeam['points'] ?? '-' }} points</p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeAdjustment" class="sd-icon-btn" aria-label="Close"><x-scorer.icon name="x" /></button>
                </header>
                <div class="sd-dialog-body">
                    <div class="scorer-feedback" aria-live="polite">
                        @if($errors->any())<div role="alert" class="alert alert-error"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
                    </div>
                    <p class="sd-note">Use a negative number for a penalty, or a positive number to add points. To correct a previous entry, add a compensating adjustment with a reason. History is retained.</p>
                    <label class="form-control"><span class="label">Point change</span><input type="number" step="1" wire:model="points" class="input w-full" placeholder="For example, -2" autofocus /></label>
                    <label class="form-control"><span class="label">Official reason</span><textarea wire:model="reason" maxlength="1000" required rows="3" class="textarea w-full"></textarea></label>
                </div>
                <footer class="sd-dialog-foot">
                    <button type="button" wire:click="openAdjustment({{ $groupId }}, {{ $teamId }})" wire:confirm="Clear this form and load the latest group version?" wire:loading.attr="disabled" class="btn btn-ghost sd-btn-quiet sd-foot-start"><x-scorer.icon name="refresh" /> Reload adjustment form</button>
                    <button type="button" wire:click="closeAdjustment" class="btn btn-ghost">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" class="btn btn-primary"><x-scorer.icon name="check" /> Record adjustment</button>
                </footer>
            </form>
        @endif
    </dialog>
</div>
