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
{{-- 14 ft × 7 ft LED canvas. Re-reads standings every 15 seconds. --}}
<div class="led-viewport" wire:poll.15s>
  <main class="led-stage">
    <header class="flex shrink-0 items-center justify-between border-b-2 border-primary pb-[.8cqh]">
        <img src="{{ asset('images/htg-logo-horizontal.webp') }}" alt="Hit the Grounds {{ config('tournament.year') }}, powered by GTN" width="900" height="481" class="h-[6cqh] w-auto">
        <h1 class="text-[3.4cqh] font-extrabold uppercase text-primary-ink">Group stage</h1>
    </header>
    <div class="led-groups">
        @forelse($groups as $group)
            <section wire:key="led-{{ $group['id'] }}" aria-labelledby="led-group-{{ $group['id'] }}"
                     class="led-group min-h-0 min-w-0 overflow-hidden rounded-xl border border-base-300 bg-white/60">
                <table class="w-full table-fixed border-collapse text-[2.3cqh] tabular-nums [&_td]:px-[.35cqw] [&_td]:py-[.8cqh] [&_td]:text-center [&_th]:px-[.35cqw] [&_th]:py-[.8cqh] [&_th]:text-center">
                    <caption id="led-group-{{ $group['id'] }}" class="border-b-[3px] border-primary bg-base-200 px-[.7cqw] py-[1cqh] text-left text-[2.8cqh] font-extrabold uppercase tracking-wide">{{ $group['name'] }}</caption>
                    <thead class="text-[1.8cqh] text-base-content/80"><tr>
                        <th scope="col" class="w-[6%]">#</th>
                        <th scope="col" class="w-[54%] !text-left">Team</th>
                        <th scope="col"><abbr title="Matches played" class="no-underline">P</abbr></th>
                        <th scope="col"><abbr title="Points" class="no-underline">Pts</abbr></th>
                        <th scope="col" class="w-[20%]"><abbr title="Net run rate" class="no-underline">NRR</abbr></th>
                    </tr></thead>
                    <tbody>@foreach($group['teams'] as $team)
                        <tr wire:key="led-{{ $group['id'] }}-{{ $team['id'] }}" class="border-t border-base-300">
                            <td>{{ $team['rank'] }}</td>
                            <th scope="row" class="!text-left font-bold">
                                <span class="flex min-w-0 items-center gap-[.4cqw]">
                                    <x-tournament.team-badge :name="$team['name']" class="!h-[3cqh] !w-[4cqh] !rounded-md !p-[.2cqh] !text-[1.1cqh]" />
                                    <span class="min-w-0 leading-tight [overflow-wrap:anywhere]">{{ $team['name'] }}</span>
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
            <p class="col-span-full text-[3cqh]">No tournament groups yet.</p>
        @endforelse
    </div>
  </main>
</div>
