<?php

use App\Models\TournamentMatch;
use Livewire\Volt\Component;

new class extends Component {
    public function with(): array
    {
        return [
            'qfMatches' => TournamentMatch::with(['team1', 'team2', 'winner'])->where('stage', 'QF')->get()->keyBy('bracket_position'),
            'sfMatches' => TournamentMatch::with(['team1', 'team2', 'winner'])->where('stage', 'SF')->get()->keyBy('bracket_position'),
            'finalMatch' => TournamentMatch::with(['team1', 'team2', 'winner'])->where('stage', 'F')->first(),
        ];
    }
}; ?>

<div class="overflow-x-auto pb-8 relative w-full rounded-2xl bg-base-200/20 pt-8 border border-base-200">
    <div class="min-w-[900px] flex justify-center gap-10 lg:gap-16 px-4">
        
        <!-- Quarter Finals -->
        <div class="flex flex-col justify-around gap-6 w-64 relative">
            <div class="text-center font-bold text-primary uppercase tracking-widest mb-6 border-b border-primary/20 pb-2">Quarter-Finals</div>
            
            <x-bracket-node :match="$qfMatches[1] ?? null" slot="1" />
            <x-bracket-node :match="$qfMatches[2] ?? null" slot="2" />
            
            <div class="h-6"></div> <!-- spacer -->
            
            <x-bracket-node :match="$qfMatches[3] ?? null" slot="3" />
            <x-bracket-node :match="$qfMatches[4] ?? null" slot="4" />
        </div>

        <!-- Semi Finals -->
        <div class="flex flex-col justify-around py-12 gap-16 w-64 relative">
            <div class="text-center font-bold text-primary uppercase tracking-widest absolute top-0 w-full border-b border-primary/20 pb-2">Semi-Finals</div>
            
            <x-bracket-node :match="$sfMatches[1] ?? null" slot="1" />
            <x-bracket-node :match="$sfMatches[2] ?? null" slot="2" />
        </div>

        <!-- The Final -->
        <div class="flex flex-col justify-center py-4 w-64 relative">
            <div class="text-center font-bold text-primary uppercase tracking-widest absolute top-0 w-full border-b border-primary/20 pb-2">The Final</div>
            
            <div class="p-1 rounded-2xl bg-gradient-to-br from-primary to-secondary shadow-2xl">
                <x-bracket-node :match="$finalMatch" slot="1" is-final="true" />
            </div>
            
            @if($finalMatch && $finalMatch->status === 'finished' && $finalMatch->winner)
                <div class="mt-8 text-center animate-fade-in-up">
                    <x-mary-icon name="o-trophy" class="w-20 h-20 text-warning mx-auto mb-2 drop-shadow-xl" />
                    <div class="text-2xl font-black text-transparent bg-clip-text bg-gradient-to-r from-primary to-secondary">
                        {{ $finalMatch->winner->team_name }}
                    </div>
                    <div class="text-sm font-bold uppercase tracking-widest text-base-content/50 mt-1">Champions</div>
                </div>
            @endif
        </div>
    </div>
</div>
