<?php

use App\Models\TournamentMatch;
use App\Services\TournamentStandingsService;
use App\Models\TournamentGroup;
use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {
    public string $stage;
    public string $statusFilter = 'all';

    #[On('match-created')]
    #[On('match-updated')]
    public function refreshMatches() {}

    public function with(): array
    {
        $query = TournamentMatch::with(['team1', 'team2', 'group'])
            ->where('stage', $this->stage)
            ->orderBy('created_at', 'desc');

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return [
            'matches' => $query->get()
        ];
    }

    public function updateStatus(int $matchId, string $newStatus)
    {
        $match = TournamentMatch::find($matchId);
        if (!$match) return;

        $oldStatus = $match->status;
        
        if ($newStatus === 'finished') {
            if ($match->winner_id === null && !$match->is_draw) {
                $this->dispatch('open-match-edit', matchId: $matchId);
                return;
            }
        }

        $match->update(['status' => $newStatus]);
        
        if ($oldStatus === 'finished' && $newStatus !== 'finished' && $match->stage === 'G' && $match->group_id) {
            $group = TournamentGroup::find($match->group_id);
            if ($group) {
                app(TournamentStandingsService::class)->recalculateForGroup($group);
            }
        }
    }

    public function editMatch(int $matchId)
    {
        $this->dispatch('open-match-edit', matchId: $matchId);
    }
}; ?>

<div>
    <div class="card bg-base-100 shadow-sm border-base-300 border-1 mb-6">
        <div class="card-body p-4 md:p-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <h2 class="card-title text-xl">Matches</h2>
                
                <div class="join">
                    <button wire:click="$set('statusFilter', 'all')" class="btn btn-sm join-item {{ $statusFilter === 'all' ? 'btn-neutral' : 'btn-ghost border-base-300' }}">All</button>
                    <button wire:click="$set('statusFilter', 'upcoming')" class="btn btn-sm join-item {{ $statusFilter === 'upcoming' ? 'btn-neutral' : 'btn-ghost border-base-300' }}">Upcoming</button>
                    <button wire:click="$set('statusFilter', 'live')" class="btn btn-sm join-item {{ $statusFilter === 'live' ? 'btn-neutral' : 'btn-ghost border-base-300' }}">Live</button>
                    <button wire:click="$set('statusFilter', 'finished')" class="btn btn-sm join-item {{ $statusFilter === 'finished' ? 'btn-neutral' : 'btn-ghost border-base-300' }}">Finished</button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4">
                @forelse($matches as $match)
                    <div class="border border-base-200 rounded-lg p-4 bg-base-200/30 flex flex-col md:flex-row gap-4 items-center justify-between">
                        <!-- Match Info -->
                        <div class="flex-1 w-full">
                            <div class="flex items-center gap-2 mb-2">
                                @if($match->status === 'live')
                                    <span class="badge badge-success badge-sm animate-pulse">LIVE</span>
                                @elseif($match->status === 'finished')
                                    <span class="badge badge-info badge-sm">FINISHED</span>
                                @else
                                    <span class="badge badge-ghost badge-sm">UPCOMING</span>
                                @endif
                                
                                @if($match->stage === 'G' && $match->group)
                                    <span class="text-xs font-semibold opacity-70">{{ $match->group->name }}</span>
                                @endif
                            </div>
                            
                            <div class="flex items-center justify-between md:justify-start md:gap-8 font-bold text-lg">
                                <div class="text-right flex-1 md:flex-none md:w-32 truncate" title="{{ $match->team1->team_name }}">
                                    {{ $match->team1->team_name }}
                                </div>
                                <div class="text-base-content/40 font-normal text-sm">vs</div>
                                <div class="text-left flex-1 md:flex-none md:w-32 truncate" title="{{ $match->team2->team_name }}">
                                    {{ $match->team2->team_name }}
                                </div>
                            </div>

                            @if($match->status === 'finished')
                                <div class="mt-2 text-sm">
                                    <span class="text-success font-semibold">
                                        @if($match->is_draw)
                                            Match Drawn
                                        @else
                                            {{ $match->winner->team_name ?? 'Unknown' }} Won
                                        @endif
                                    </span>
                                    <span class="opacity-70 ml-2">
                                        ({{ $match->team1_score }}/{{ $match->team1_wickets }} vs {{ $match->team2_score }}/{{ $match->team2_wickets }})
                                    </span>
                                </div>
                            @endif
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2 w-full md:w-auto mt-4 md:mt-0 pt-4 md:pt-0 border-t md:border-t-0 border-base-200">
                            @if($match->status === 'upcoming')
                                <x-mary-button wire:click="updateStatus({{ $match->id }}, 'live')" class="btn-success btn-sm btn-outline flex-1 md:flex-none" icon="o-play">Start</x-mary-button>
                            @elseif($match->status === 'live')
                                <x-mary-button wire:click="updateStatus({{ $match->id }}, 'finished')" class="btn-info btn-sm btn-outline flex-1 md:flex-none" icon="o-flag">Finish</x-mary-button>
                            @elseif($match->status === 'finished')
                                <x-mary-button wire:click="updateStatus({{ $match->id }}, 'live')" class="btn-warning btn-sm btn-outline flex-1 md:flex-none" icon="o-arrow-uturn-left" tooltip="Revert to Live" />
                            @endif
                            
                            <x-mary-button wire:click="editMatch({{ $match->id }})" class="btn-ghost btn-sm flex-1 md:flex-none" icon="o-pencil-square">Edit</x-mary-button>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12 text-base-content/50 bg-base-200/50 rounded-box">
                        <x-mary-icon name="o-inbox" class="w-12 h-12 mx-auto mb-3 opacity-50" />
                        <p>No matches found.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
