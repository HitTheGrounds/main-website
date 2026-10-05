<?php

use App\Models\TournamentMatch;
use Livewire\Volt\Component;

new class extends Component {
    public string $statusFilter = 'all';

    public function with(): array
    {
        $query = TournamentMatch::with(['team1', 'team2', 'group', 'winner', 'battingFirst'])
            ->orderByRaw("CASE status WHEN 'live' THEN 1 WHEN 'upcoming' THEN 2 WHEN 'finished' THEN 3 ELSE 4 END")
            ->orderBy('created_at', 'desc');

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return [
            'matches' => $query->get()
        ];
    }
    
    public function getStageName(string $stage)
    {
        return match ($stage) {
            'G' => 'Group Stage',
            'QF' => 'Quarter-Final',
            'SF' => 'Semi-Final',
            'F' => 'The Final',
            default => 'Match',
        };
    }
}; ?>

<div>
    <div class="mb-8 flex justify-center overflow-x-auto pb-2">
        <div class="join shadow-sm">
            <button wire:click="$set('statusFilter', 'all')" class="btn join-item {{ $statusFilter === 'all' ? 'btn-neutral' : 'btn-base-100 border-base-300' }}">All</button>
            <button wire:click="$set('statusFilter', 'live')" class="btn join-item {{ $statusFilter === 'live' ? 'btn-neutral' : 'btn-base-100 border-base-300' }}">🟢 Live</button>
            <button wire:click="$set('statusFilter', 'upcoming')" class="btn join-item {{ $statusFilter === 'upcoming' ? 'btn-neutral' : 'btn-base-100 border-base-300' }}">🕐 Upcoming</button>
            <button wire:click="$set('statusFilter', 'finished')" class="btn join-item {{ $statusFilter === 'finished' ? 'btn-neutral' : 'btn-base-100 border-base-300' }}">✅ Finished</button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @forelse($matches as $match)
            <div class="card bg-base-100 shadow-xl border border-base-200 hover:border-primary/30 transition-colors">
                <div class="card-body p-5">
                    <!-- Header -->
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-base-content/50">
                                {{ $this->getStageName($match->stage) }}
                                @if($match->stage === 'G' && $match->group)
                                    • {{ $match->group->name }}
                                @endif
                            </span>
                        </div>
                        <div>
                            @if($match->status === 'live')
                                <span class="badge badge-success badge-sm animate-pulse gap-1">
                                    <div class="w-1.5 h-1.5 rounded-full bg-white"></div> LIVE
                                </span>
                            @elseif($match->status === 'finished')
                                <span class="badge badge-neutral badge-sm">Finished</span>
                            @else
                                <span class="badge badge-ghost badge-sm">Upcoming</span>
                            @endif
                        </div>
                    </div>

                    <!-- Teams & Scores -->
                    <div class="flex flex-col gap-4">
                        <!-- Team 1 Row -->
                        <div class="flex justify-between items-center">
                            <div class="flex items-center gap-2">
                                <div class="font-bold text-lg {{ $match->status === 'finished' && $match->winner_id === $match->team1_id ? 'text-primary' : '' }}">
                                    {{ $match->team1->team_name }}
                                </div>
                                @if($match->batting_first_id === $match->team1_id && $match->status !== 'upcoming')
                                    <x-mary-icon name="o-bolt" class="w-4 h-4 text-warning" title="Batted First" />
                                @endif
                            </div>
                            @if($match->status === 'finished')
                                <div class="text-right">
                                    <div class="font-bold text-xl">{{ $match->team1_score }}<span class="text-base font-normal opacity-50">/{{ $match->team1_wickets }}</span></div>
                                    <div class="text-xs opacity-50">{{ $match->team1_overs }}.{{ $match->team1_balls }} ov</div>
                                </div>
                            @endif
                        </div>

                        <!-- VS divider for upcoming -->
                        @if($match->status === 'upcoming')
                            <div class="divider my-0">VS</div>
                        @else
                            <div class="divider my-0 opacity-20"></div>
                        @endif

                        <!-- Team 2 Row -->
                        <div class="flex justify-between items-center">
                            <div class="flex items-center gap-2">
                                <div class="font-bold text-lg {{ $match->status === 'finished' && $match->winner_id === $match->team2_id ? 'text-primary' : '' }}">
                                    {{ $match->team2->team_name }}
                                </div>
                                @if($match->batting_first_id === $match->team2_id && $match->status !== 'upcoming')
                                    <x-mary-icon name="o-bolt" class="w-4 h-4 text-warning" title="Batted First" />
                                @endif
                            </div>
                            @if($match->status === 'finished')
                                <div class="text-right">
                                    <div class="font-bold text-xl">{{ $match->team2_score }}<span class="text-base font-normal opacity-50">/{{ $match->team2_wickets }}</span></div>
                                    <div class="text-xs opacity-50">{{ $match->team2_overs }}.{{ $match->team2_balls }} ov</div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Footer / Result -->
                    @if($match->status === 'finished')
                        <div class="mt-4 pt-4 border-t border-base-200 text-center">
                            <span class="text-primary font-bold">
                                @if($match->is_draw)
                                    Match Tied
                                @else
                                    {{ $match->winner->team_name ?? 'Unknown' }} Won
                                @endif
                            </span>
                        </div>
                    @elseif($match->status === 'live')
                        <div class="mt-4 pt-4 border-t border-base-200 text-center text-success/80 text-sm italic">
                            Match currently in progress...
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-16 bg-base-200/50 rounded-2xl">
                <x-mary-icon name="o-calendar" class="w-16 h-16 mx-auto mb-4 opacity-20" />
                <h3 class="text-xl font-bold opacity-50">No matches found</h3>
                <p class="opacity-40">Check back later for updates.</p>
            </div>
        @endforelse
    </div>
</div>
