@props(['match', 'slot', 'isFinal' => false])

<div class="card bg-base-100 shadow-xl border {{ $isFinal ? 'border-none' : 'border-base-300' }} overflow-hidden">
    @if(!$match)
        <div class="card-body p-4 text-center opacity-40">
            <div class="text-xs font-bold mb-2">TBD (Slot {{ $slot }})</div>
            <div class="text-sm italic">Match not scheduled</div>
        </div>
    @else
        <div class="bg-base-200/50 px-3 py-1.5 flex justify-between items-center border-b border-base-300">
            <span class="text-[10px] font-bold text-base-content/70 uppercase">Slot {{ $match->bracket_position }}</span>
            @if($match->status === 'live')
                <span class="badge badge-success badge-sm animate-pulse gap-1"><div class="w-1.5 h-1.5 rounded-full bg-white"></div> LIVE</span>
            @elseif($match->status === 'finished')
                <span class="badge badge-neutral badge-sm text-[10px]">FINISHED</span>
            @else
                <span class="badge badge-ghost badge-sm text-[10px]">UPCOMING</span>
            @endif
        </div>
        
        <div class="flex flex-col">
            <!-- Team 1 -->
            <div class="flex justify-between items-center p-3 border-b border-base-200 {{ $match->status === 'finished' && $match->winner_id === $match->team1_id ? 'bg-success/10' : '' }}">
                <div class="font-bold truncate max-w-[120px] {{ $match->status === 'finished' && $match->winner_id === $match->team1_id ? 'text-success' : '' }}">
                    {{ $match->team1->team_name ?? 'TBD' }}
                </div>
                @if($match->status === 'finished')
                    <div class="text-right leading-tight">
                        <div class="font-bold text-sm">{{ $match->team1_score }}/{{ $match->team1_wickets }}</div>
                        <div class="text-[10px] opacity-70">{{ $match->team1_overs }}.{{ $match->team1_balls }}</div>
                    </div>
                @endif
            </div>
            
            <!-- Team 2 -->
            <div class="flex justify-between items-center p-3 {{ $match->status === 'finished' && $match->winner_id === $match->team2_id ? 'bg-success/10' : '' }}">
                <div class="font-bold truncate max-w-[120px] {{ $match->status === 'finished' && $match->winner_id === $match->team2_id ? 'text-success' : '' }}">
                    {{ $match->team2->team_name ?? 'TBD' }}
                </div>
                @if($match->status === 'finished')
                    <div class="text-right leading-tight">
                        <div class="font-bold text-sm">{{ $match->team2_score }}/{{ $match->team2_wickets }}</div>
                        <div class="text-[10px] opacity-70">{{ $match->team2_overs }}.{{ $match->team2_balls }}</div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
