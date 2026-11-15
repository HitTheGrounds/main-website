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

    private function year(): int
    {
        return (int) config('tournament.year');
    }
}; ?>

<div class="space-y-6">
    <header class="text-center space-y-2">
        <h1 class="text-2xl font-bold sm:text-3xl">Group standings</h1>
        <p class="text-sm">Published team results only. Ordered by points, then NRR. Equal values remain tied; display order does not decide qualification.</p>
        <p class="text-sm">Player figures are entered separately. Confirm advancing teams in <a href="{{ route('scorer.knockouts') }}" class="link">Qualification &amp; bracket</a>.</p>
        <button type="button" wire:click="$refresh" wire:loading.attr="disabled" class="btn btn-outline btn-sm">Refresh standings</button>
    </header>
    <div aria-live="polite">
        @if($notice)<p role="status" class="alert alert-success">{{ $notice }}</p>@endif
        @if($errors->any())<div role="alert" class="alert alert-error"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
        <p wire:loading role="status" class="text-center text-sm">Updating standings...</p>
    </div>
    @forelse($groups as $group)
        <section wire:key="standings-{{ $group['id'] }}" class="card border border-base-300 bg-base-100" aria-labelledby="group-heading-{{ $group['id'] }}"><div class="card-body space-y-4">
            <h2 id="group-heading-{{ $group['id'] }}" class="text-center text-xl font-bold">{{ $group['name'] }}</h2>
            <p class="text-center text-sm">{{ $group['coverage']['published'] }} of {{ $group['coverage']['expected'] }} expected fixtures published.
                {{ $group['coverage']['missing'] }} fixtures not created. {{ $group['coverage']['unpublished'] }} created fixtures awaiting publication.</p>
            <p class="text-center text-sm">{{ $group['coverage']['complete'] ? 'All group fixtures published.' : 'Group fixture coverage is incomplete.' }}</p>
            <div class="overflow-x-auto"><table class="table w-full">
                <caption class="sr-only">{{ $group['name'] }} published standings</caption>
                <thead><tr>@foreach(['Team', 'Played', 'Won', 'Lost', 'Tied', 'No result', 'Base points', 'Adjustment', 'Points', 'NRR', 'Action'] as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead>
                <tbody>@forelse($group['teams'] as $team)
                    <tr wire:key="standing-team-{{ $group['id'] }}-{{ $team['id'] }}"><th scope="row">{{ $team['name'] }}</th>
                        @foreach(['played', 'wins', 'losses', 'ties', 'no_results', 'base_points'] as $field)<td>{{ $team[$field] }}</td>@endforeach
                        <td>{{ $team['points'] - $team['base_points'] }}</td><td class="font-bold">{{ $team['points'] }}</td><td>{{ $team['nrr'] ?? '-' }}</td>
                        <td><button type="button" wire:click="openAdjustment({{ $group['id'] }}, {{ $team['id'] }})" wire:loading.attr="disabled" class="btn btn-outline btn-sm">Adjust points<span class="sr-only"> for {{ $team['name'] }}</span></button></td>
                    </tr>
                @empty<tr><td colspan="11">No teams assigned.</td></tr>@endforelse</tbody>
            </table></div>
            <p class="text-sm text-center">NRR is '-' until both rate denominators are known. Abandoned/no-result matches are excluded from NRR.</p>
            @foreach($group['teams'] as $team)
                @if($team['adjustments'])<details class="rounded-lg border border-base-300 p-3"><summary class="cursor-pointer font-semibold">{{ $team['name'] }}: point adjustment history</summary>
                    <ul class="mt-3 space-y-2">@foreach($team['adjustments'] as $adjustment)<li class="break-words">{{ $adjustment['points'] }} points: {{ $adjustment['reason'] }}
                        @if(isset($adjustment['actor_id'])) | Scorer #{{ $adjustment['actor_id'] }} @endif
                        @if(isset($adjustment['recorded_at'])) | {{ $adjustment['recorded_at'] }} @endif</li>@endforeach</ul>
                </details>@endif
            @endforeach
            @if($groupId === $group['id'])
                <form wire:submit="saveAdjustment" wire:confirm="Record this official point adjustment?" class="rounded-lg border border-primary p-4 space-y-4">
                    <h3 class="text-center font-bold">Adjust points: {{ collect($group['teams'])->firstWhere('id', $teamId)['name'] ?? 'Team no longer assigned' }}</h3>
                    <p class="text-sm text-center">Use a negative number for a penalty, or a positive number to add points. To correct a previous entry, add a compensating adjustment with a reason. History is retained.</p>
                    <label><span class="label">Point change</span><input type="number" step="1" wire:model="points" class="input w-full" /></label>
                    <label><span class="label">Official reason</span><textarea wire:model="reason" maxlength="1000" required class="textarea w-full"></textarea></label>
                    <button type="submit" wire:loading.attr="disabled" class="btn btn-primary">Record adjustment</button>
                    <button type="button" wire:click="openAdjustment({{ $group['id'] }}, {{ $teamId }})" wire:confirm="Clear this form and load the latest group version?" wire:loading.attr="disabled" class="btn btn-ghost">Reload adjustment form</button>
                </form>
            @endif
        </div></section>
    @empty<p class="text-center">No tournament groups yet. Add groups in <a href="{{ route('scorer.groups.index') }}" class="link">Group setup</a>.</p>@endforelse
</div>
