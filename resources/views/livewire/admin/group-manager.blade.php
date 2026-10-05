<?php

use App\Models\Team;
use App\Models\TournamentGroup;
use App\Models\GroupTeam;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public string $newGroupName = '';
    public array $teamAssignments = []; // group_id => team_id

    public function with(): array
    {
        $groups = TournamentGroup::with('teams')->get();
        // Get all approved teams that are not yet assigned to any group
        $unassignedTeams = Team::where('approved', true)
                               ->whereDoesntHave('groupTeam')
                               ->get();

        return compact('groups', 'unassignedTeams');
    }

    public function createGroup(): void
    {
        $this->validate([
            'newGroupName' => ['required', 'string', 'max:50', 'unique:tournament_groups,name']
        ]);

        TournamentGroup::create(['name' => $this->newGroupName]);
        $this->reset('newGroupName');
        $this->dispatch('group-created');
    }

    public function assignTeam(int $groupId): void
    {
        if (empty($this->teamAssignments[$groupId])) {
            return;
        }

        $teamId = $this->teamAssignments[$groupId];

        // Check if team is already assigned
        if (GroupTeam::where('team_id', $teamId)->exists()) {
            $this->addError("assign.{$groupId}", "This team is already assigned to a group.");
            return;
        }

        GroupTeam::create([
            'group_id' => $groupId,
            'team_id' => $teamId,
        ]);

        $this->teamAssignments[$groupId] = '';
        $this->dispatch('team-assigned');
    }
    
    public function removeTeam(int $groupId, int $teamId): void
    {
        GroupTeam::where('group_id', $groupId)->where('team_id', $teamId)->delete();
        $this->dispatch('team-removed');
    }
}; ?>

<div>
    <div class="mb-6 flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-bold text-base-content">Tournament Groups</h1>
            <p class="mt-2 text-base-content/70">Manage groups and team assignments</p>
        </div>
        
        <form wire:submit="createGroup" class="flex gap-2 items-end">
            <x-mary-input wire:model="newGroupName" label="New Group Name" placeholder="e.g. Group A" required />
            <x-mary-button type="submit" class="btn-primary" icon="o-plus">Create</x-mary-button>
        </form>
    </div>
    
    @error('newGroupName')
        <div class="text-error text-sm mb-4">{{ $message }}</div>
    @enderror

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach($groups as $group)
            <div class="card bg-base-100 shadow-sm border-base-300 border-1">
                <div class="card-body">
                    <h2 class="card-title text-xl border-b border-base-200 pb-2 mb-4">{{ $group->name }}</h2>
                    
                    @error("assign.{$group->id}")
                        <div class="text-error text-sm mb-2">{{ $message }}</div>
                    @enderror

                    <div class="flex gap-2 mb-4">
                        <select wire:model="teamAssignments.{{ $group->id }}" class="select select-bordered flex-1">
                            <option value="">Select a team to assign...</option>
                            @foreach($unassignedTeams as $team)
                                <option value="{{ $team->id }}">{{ $team->team_name }} ({{ $team->company->name }})</option>
                            @endforeach
                        </select>
                        <x-mary-button wire:click="assignTeam({{ $group->id }})" class="btn-secondary" icon="o-check">Assign</x-mary-button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Team</th>
                                    <th>Company</th>
                                    <th class="w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($group->teams as $team)
                                    <tr>
                                        <td class="font-semibold">{{ $team->team_name }}</td>
                                        <td class="text-xs">{{ $team->company->name }}</td>
                                        <td>
                                            <button wire:click="removeTeam({{ $group->id }}, {{ $team->id }})" wire:confirm="Remove this team from {{ $group->name }}?" class="btn btn-ghost btn-xs text-error">
                                                <x-mary-icon name="o-trash" class="w-4 h-4" />
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-base-content/50 text-sm">No teams assigned yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
        
        @if($groups->isEmpty())
            <div class="col-span-full text-center py-12 text-base-content/50 bg-base-200/50 rounded-box">
                <x-mary-icon name="o-queue-list" class="w-12 h-12 mx-auto mb-3 opacity-50" />
                <p>No tournament groups created yet.</p>
                <p class="text-sm">Create a group using the form above.</p>
            </div>
        @endif
    </div>
</div>
