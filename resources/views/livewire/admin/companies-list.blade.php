<?php

use App\Models\Company;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $filter = 'verified';
    public $editingCompanyId = null;
    public $maxTeamCount = 2;

    public function editMaxTeamCount($companyId, $currentMax)
    {
        $this->editingCompanyId = $companyId;
        $this->maxTeamCount = $currentMax;
    }

    public function updateMaxTeamCount($companyId)
    {
        $this->validate([
            'maxTeamCount' => 'required|integer|min:0|max:100',
        ]);

        $company = Company::findOrFail($companyId);
        $company->update(['max_team_count' => $this->maxTeamCount]);

        $this->editingCompanyId = null;
        $this->dispatch('team-count-updated');
    }

    public function cancelEdit()
    {
        $this->editingCompanyId = null;
        $this->maxTeamCount = 2;
    }

    public function with(): array
    {
        $query = Company::withCount(['users', 'teams']);

        if ($this->filter === 'verified') {
            $query->whereHas('users', function ($q) {
                $q->whereNotNull('email_verified_at');
            });
        }

        return [
            'companies' => $query->latest()->paginate(10),
        ];
    }
}; ?>

<div>
    <div class="card bg-base-100 border-base-300 border-2">
        <div class="card-body">
            <!-- Filter tabs -->
            <div class="tabs tabs-boxed mb-4">
                <a wire:click="$set('filter', 'all')" class="tab {{ $filter === 'all' ? 'tab-active' : '' }}">All Companies</a>
                <a wire:click="$set('filter', 'verified')" class="tab {{ $filter === 'verified' ? 'tab-active' : '' }}">Verified</a>
            </div>

            <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Company Name</th>
                            <th>Phone</th>
                            <th>Users</th>
                            <th>Teams</th>
                            <th>Max Teams</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($companies as $company)
                            <tr>
                                <td>{{ $company->id }}</td>
                                <td class="font-semibold">{{ $company->name }}</td>
                                <td>{{ $company->phone ?? 'N/A' }}</td>
                                <td>{{ $company->users_count }}</td>
                                <td>
                                    <span class="badge badge-{{ $company->teams_count >= $company->max_team_count ? 'error' : 'success' }}">
                                        {{ $company->teams_count }}
                                    </span>
                                </td>
                                <td>
                                    @if($editingCompanyId === $company->id)
                                        <div class="flex items-center gap-2">
                                            <input type="number"
                                                   wire:model="maxTeamCount"
                                                   class="input input-bordered input-sm w-20"
                                                   min="0"
                                                   max="100">
                                            <button wire:click="updateMaxTeamCount({{ $company->id }})"
                                                    class="btn btn-success btn-xs">
                                                Save
                                            </button>
                                            <button wire:click="cancelEdit"
                                                    class="btn btn-ghost btn-xs">
                                                Cancel
                                            </button>
                                        </div>
                                    @else
                                        {{ $company->max_team_count }}
                                    @endif
                                </td>
                                <td>{{ $company->created_at->format('M d, Y') }}</td>
                                <td>
                                    @if($editingCompanyId !== $company->id)
                                        <button wire:click="editMaxTeamCount({{ $company->id }}, {{ $company->max_team_count }})"
                                                class="btn btn-sm btn-ghost">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                            </svg>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-base-content/70">No companies found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $companies->links() }}
            </div>
        </div>
    </div>
</div>
