<?php

use App\Models\TournamentGroup;
use App\Models\GroupTeam;
use App\Models\TournamentMatch;
use Livewire\Volt\Component;

new class extends Component {
    public $groups;
    
    public bool $showQualifyModal = false;
    public ?int $qualifyingGroupId = null;
    public array $selectedTeams = [];
    public bool $hasPendingMatches = false;
    
    protected $listeners = ['match-created' => '$refresh', 'match-updated' => '$refresh'];

    public function mount()
    {
        $this->loadGroups();
    }
    
    public function loadGroups()
    {
        $this->groups = TournamentGroup::with(['groupTeams' => function($q) {
            $q->orderBy('points', 'desc')->orderBy('nrr', 'desc');
        }, 'groupTeams.team'])->get();
    }

    public function openQualifyModal(int $groupId)
    {
        $this->qualifyingGroupId = $groupId;
        
        $group = TournamentGroup::find($groupId);
        if (!$group) return;
        
        $topTeams = $group->groupTeams()
            ->orderBy('points', 'desc')
            ->orderBy('nrr', 'desc')
            ->take(config('tournament.teams_qualify_per_group', 2))
            ->pluck('team_id')
            ->toArray();
            
        $this->selectedTeams = $topTeams;
        
        $this->hasPendingMatches = TournamentMatch::where('stage', 'G')
            ->where('group_id', $groupId)
            ->whereIn('status', ['upcoming', 'live'])
            ->exists();
            
        $this->showQualifyModal = true;
    }

    public function saveQualifications()
    {
        $maxQualify = config('tournament.teams_qualify_per_group', 2);
        
        if (count($this->selectedTeams) > $maxQualify) {
            $this->addError('selectedTeams', "You can only qualify up to {$maxQualify} teams.");
            return;
        }
        
        $group = TournamentGroup::find($this->qualifyingGroupId);
        if (!$group) return;
        
        $currentQualified = $group->groupTeams()->where('qualified', true)->pluck('team_id')->toArray();
        $unqualifying = array_diff($currentQualified, $this->selectedTeams);
        
        if (!empty($unqualifying)) {
            $hasKnockout = TournamentMatch::whereIn('stage', ['QF', 'SF', 'F'])
                ->where(function($q) use ($unqualifying) {
                    $q->whereIn('team1_id', $unqualifying)
                      ->orWhereIn('team2_id', $unqualifying);
                })->exists();
                
            if ($hasKnockout) {
                $this->addError('selectedTeams', 'Cannot un-qualify a team that already has a scheduled knockout match.');
                return;
            }
        }

        foreach ($group->groupTeams as $gt) {
            $isQualified = in_array($gt->team_id, $this->selectedTeams);
            $gt->update(['qualified' => $isQualified]);
        }
        
        $this->showQualifyModal = false;
        $this->loadGroups();
    }
}; ?>

<div>
    @foreach($groups as $group)
        <div class="card bg-base-100 shadow-sm border-base-300 border-1 mb-6">
            <div class="card-body p-4 md:p-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-4">
                    <h2 class="card-title text-xl">{{ $group->name }} Standings</h2>
                    <x-mary-button wire:click="openQualifyModal({{ $group->id }})" class="btn-sm btn-outline btn-primary" icon="o-check-badge">Qualify Teams</x-mary-button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="table table-sm table-zebra">
                        <thead>
                            <tr>
                                <th>Pos</th>
                                <th>Team</th>
                                <th>P</th>
                                <th>W</th>
                                <th>L</th>
                                <th>D</th>
                                <th>Pts</th>
                                <th>NRR</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($group->groupTeams as $index => $gt)
                                <tr class="{{ $gt->qualified ? 'bg-success/10 font-medium' : ($index < config('tournament.teams_qualify_per_group', 2) ? 'border-l-2 border-primary' : '') }}">
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            {{ $gt->team->team_name }}
                                            @if($gt->qualified)
                                                <x-mary-icon name="o-check-circle" class="w-4 h-4 text-success" tooltip="Qualified" />
                                            @endif
                                        </div>
                                    </td>
                                    <td>{{ $gt->matches_played }}</td>
                                    <td>{{ $gt->wins }}</td>
                                    <td>{{ $gt->losses }}</td>
                                    <td>{{ $gt->draws }}</td>
                                    <td class="font-bold">{{ $gt->points }}</td>
                                    <td class="{{ $gt->nrr >= 0 ? 'text-success' : 'text-error' }}">{{ number_format($gt->nrr, 4) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-base-content/50">No teams assigned to this group yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endforeach

    <x-mary-modal wire:model="showQualifyModal" title="Qualify Teams" class="backdrop-blur">
        @if($hasPendingMatches)
            <div class="alert alert-warning mb-4">
                <x-mary-icon name="o-exclamation-triangle" class="w-6 h-6" />
                <span><strong>Warning:</strong> This group still has upcoming or live matches. Standings may change.</span>
            </div>
        @endif

        <p class="mb-4">Select the teams that will advance to the Quarter-Finals. The top 2 teams are auto-selected.</p>

        @php
            $currentGroup = collect($groups)->firstWhere('id', $qualifyingGroupId);
        @endphp

        @if($currentGroup)
            <div class="flex flex-col gap-2">
                @foreach($currentGroup->groupTeams as $index => $gt)
                    <label class="cursor-pointer flex items-center justify-between p-3 border rounded-lg hover:bg-base-200 {{ in_array($gt->team_id, $selectedTeams) ? 'border-primary bg-primary/5' : 'border-base-300' }}">
                        <div class="flex items-center gap-3">
                            <input type="checkbox" wire:model="selectedTeams" value="{{ $gt->team_id }}" class="checkbox checkbox-primary" />
                            <span class="font-bold">{{ $gt->team->team_name }}</span>
                        </div>
                        <div class="text-sm opacity-70">
                            Pos: {{ $index + 1 }} | Pts: {{ $gt->points }} | NRR: {{ number_format($gt->nrr, 4) }}
                        </div>
                    </label>
                @endforeach
            </div>
            
            @error('selectedTeams')
                <div class="text-error text-sm mt-2">{{ $message }}</div>
            @enderror
        @endif

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('showQualifyModal', false)" class="btn-ghost" />
            <x-mary-button label="Confirm Qualification" wire:click="saveQualifications" class="btn-primary" icon="o-check" />
        </x-slot:actions>
    </x-mary-modal>
</div>
