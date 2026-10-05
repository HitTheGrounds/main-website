<?php

use App\Models\TournamentGroup;
use Livewire\Volt\Component;

new class extends Component {
    public function with(): array
    {
        $groups = TournamentGroup::with(['groupTeams' => function($q) {
            $q->orderBy('points', 'desc')->orderBy('nrr', 'desc');
        }, 'groupTeams.team'])->get();
        
        return [
            'groups' => $groups
        ];
    }
}; ?>

<div>
    <div class="flex flex-col gap-6">
        @foreach($groups as $group)
            <div class="card bg-base-100 shadow-xl border border-base-200">
                <div class="card-body p-4 md:p-5">
                    <h2 class="card-title text-lg border-b border-base-200 pb-2 mb-2">{{ $group->name }} Standings</h2>
                    
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr class="text-base-content/60">
                                    <th>#</th>
                                    <th>Team</th>
                                    <th>P</th>
                                    <th>Pts</th>
                                    <th>NRR</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($group->groupTeams as $index => $gt)
                                    <tr class="{{ $gt->qualified ? 'bg-success/5 font-semibold' : '' }}">
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            <div class="flex items-center gap-1">
                                                <span class="truncate max-w-[120px] md:max-w-[200px]" title="{{ $gt->team->team_name }}">
                                                    {{ $gt->team->team_name }}
                                                </span>
                                                @if($gt->qualified)
                                                    <x-mary-icon name="o-check-badge" class="w-4 h-4 text-success flex-shrink-0" title="Qualified for Knockouts" />
                                                @endif
                                            </div>
                                        </td>
                                        <td>{{ $gt->matches_played }}</td>
                                        <td class="font-bold text-primary">{{ $gt->points }}</td>
                                        <td class="text-xs {{ $gt->nrr >= 0 ? 'text-success' : 'text-error' }}">{{ number_format($gt->nrr, 3) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 opacity-50">No teams assigned.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
