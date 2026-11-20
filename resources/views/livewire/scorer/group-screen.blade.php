<?php

use App\Services\Tournament\ScorerStandingsService;
use Livewire\Volt\Component;

new class extends Component {
    public function with(): array
    {
        return ['groups' => app(ScorerStandingsService::class)->listing((int) config('tournament.year'))];
    }
}; ?>

@php
    $formatNrr = fn ($nrr) => $nrr === null ? '-'
        : (string) \Brick\Math\BigDecimal::of((string) $nrr)->toScale(3, \Brick\Math\RoundingMode::HALF_UP);
@endphp
{{-- 16:9 venue screen: sizes use vh so all groups fit any display. Re-reads standings every 15 seconds. --}}
<div class="flex h-screen flex-col gap-[1.2vh] px-[1.5vw] py-[1.5vh]" wire:poll.15s>
    <header class="flex items-center justify-between border-b-2 border-primary pb-[.8vh]">
        <img src="{{ asset('images/htg-logo-horizontal.webp') }}" alt="Hit the Grounds {{ config('tournament.year') }}, powered by GTN" width="900" height="481" class="h-[6vh] w-auto">
        <h1 class="text-[3.4vh] font-extrabold uppercase text-primary-ink">Group stage</h1>
    </header>
    <div class="grid min-h-0 flex-1 auto-rows-fr grid-cols-2 gap-x-[1.5vw] gap-y-[1.2vh]">
        @forelse($groups as $group)
            <section wire:key="led-{{ $group['id'] }}" aria-labelledby="led-group-{{ $group['id'] }}"
                     class="min-h-0 overflow-hidden rounded-xl border border-base-300 bg-white/60">
                <table class="w-full table-fixed border-collapse text-[2vh] tabular-nums [&_td]:px-[.5vw] [&_td]:py-[.2vh] [&_td]:text-center [&_th]:px-[.5vw] [&_th]:py-[.2vh] [&_th]:text-center [&_th]:whitespace-nowrap">
                    <caption id="led-group-{{ $group['id'] }}" class="border-b-[3px] border-primary bg-base-200 px-[1vw] py-[.3vh] text-left text-[2.1vh] font-extrabold uppercase tracking-wide">{{ $group['name'] }}</caption>
                    <thead class="text-[1.4vh] text-base-content/65"><tr>
                        <th scope="col" class="w-[6%]">#</th>
                        <th scope="col" class="w-[58%] !text-left">Team</th>
                        <th scope="col"><abbr title="Matches played" class="no-underline">P</abbr></th>
                        <th scope="col"><abbr title="Points" class="no-underline">Pts</abbr></th>
                        <th scope="col" class="w-[16%]"><abbr title="Net run rate" class="no-underline">NRR</abbr></th>
                    </tr></thead>
                    <tbody>@foreach($group['teams'] as $team)
                        <tr wire:key="led-{{ $group['id'] }}-{{ $team['id'] }}" class="border-t border-base-300">
                            <td>{{ $team['rank'] }}</td>
                            <th scope="row" class="!text-left font-bold">
                                <span class="flex min-w-0 items-center gap-[.6vw]">
                                    <x-tournament.team-badge :name="$team['name']" class="!h-[2.7vh] !w-[4vh] !rounded-md !p-[.2vh] !text-[1.1vh]" />
                                    <span class="truncate">{{ $team['name'] }}</span>
                                </span>
                            </th>
                            <td>{{ $team['played'] }}</td>
                            <td class="font-extrabold text-primary-ink">{{ $team['points'] }}</td>
                            <td>{{ $formatNrr($team['nrr']) }}</td>
                        </tr>
                    @endforeach</tbody>
                </table>
            </section>
        @empty
            <p class="text-[3vh]">No tournament groups yet.</p>
        @endforelse
        @if(count($groups) % 2 === 1)
            {{-- Odd group count: the spare grid cell carries the tournament logo. --}}
            <div class="flex min-h-0 items-center justify-center px-[2vw] py-[1vh]">
                <img src="/images/htg-logo-orange.avif" alt="" width="1500" height="710" class="max-h-full max-w-full object-contain">
            </div>
        @endif
    </div>
</div>
