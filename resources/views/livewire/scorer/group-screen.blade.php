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
    $formatNrr = function ($nrr) {
        if ($nrr === null) {
            return '-';
        }
        $value = \Brick\Math\BigDecimal::of((string) $nrr)->toScale(3, \Brick\Math\RoundingMode::HALF_UP);

        return $value->isPositive() ? '+'.$value : (string) $value;
    };
    // "Group A" -> "A"; any other name falls back to its first character.
    $groupMark = function (string $name) {
        $last = \Illuminate\Support\Str::afterLast(trim($name), ' ');

        return mb_strtoupper(mb_strlen($last) <= 2 ? $last : mb_substr(trim($name), 0, 1));
    };
@endphp
{{-- 14 ft × 7 ft LED canvas (2:1). Re-reads standings every 15 seconds. --}}
<div class="led-viewport" wire:poll.15s>
  <main class="led-stage">
    <header class="led-header">
        <div class="led-logo">
            <img src="{{ asset('images/htg-logo-horizontal.webp') }}" alt="Hit the Grounds {{ config('tournament.year') }}, powered by GTN" width="900" height="481">
        </div>
        <div class="led-title">
            <span class="led-live">Live</span>
            <h1>Group stage <span>standings</span></h1>
        </div>
    </header>

    <div class="led-groups">
        @forelse($groups as $group)
            <section wire:key="led-{{ $group['id'] }}" aria-labelledby="led-group-{{ $group['id'] }}" class="led-group">
                <div class="led-group-head">
                    <span class="led-group-letter" aria-hidden="true">{{ $groupMark($group['name']) }}</span>
                    <h2 id="led-group-{{ $group['id'] }}" class="led-group-name">{{ $group['name'] }}</h2>
                </div>
                <table class="led-table">
                    <colgroup>
                        {{-- NRR gets room for its widest form, e.g. "-12.345". --}}
                        <col style="width: 7%"><col style="width: 33%">
                        <col style="width: 6.5%"><col style="width: 6.5%"><col style="width: 6.5%"><col style="width: 6.5%">
                        <col style="width: 22%"><col style="width: 12%">
                    </colgroup>
                    <thead><tr>
                        <th scope="col">#</th>
                        <th scope="col" class="led-col-team">Team</th>
                        <th scope="col"><abbr title="Matches played">P</abbr></th>
                        <th scope="col"><abbr title="Won">W</abbr></th>
                        <th scope="col"><abbr title="Lost">L</abbr></th>
                        <th scope="col"><abbr title="Tied">T</abbr></th>
                        <th scope="col"><abbr title="Net run rate">NRR</abbr></th>
                        <th scope="col" class="led-pts-head"><abbr title="Points">Pts</abbr></th>
                    </tr></thead>
                    <tbody>@foreach($group['teams'] as $team)
                        <tr wire:key="led-{{ $group['id'] }}-{{ $team['id'] }}" @class(['led-qualified' => $team['qualified'] ?? false])>
                            <td class="led-rank">{{ $team['rank'] }}</td>
                            <th scope="row" class="led-col-team">
                                <span class="led-team">
                                    <x-tournament.team-badge :name="$team['name']" />
                                    <span class="led-team-name">{{ $team['name'] }}</span>
                                    @if($team['qualified'] ?? false)
                                        <span class="sr-only">(qualified)</span>
                                    @endif
                                </span>
                            </th>
                            <td>{{ $team['played'] }}</td>
                            <td class="led-w">{{ $team['wins'] }}</td>
                            <td class="led-l">{{ $team['losses'] }}</td>
                            <td class="led-t">{{ $team['ties'] }}</td>
                            <td class="led-nrr">{{ $formatNrr($team['nrr']) }}</td>
                            <td class="led-pts">{{ $team['points'] }}</td>
                        </tr>
                    @endforeach</tbody>
                </table>
            </section>
        @empty
            <p class="led-empty">No tournament groups yet.</p>
        @endforelse

        {{-- With 5 to 7 groups the centred bottom row leaves the outer columns empty; fill them with
             quiet brand line art. Absolutely positioned, so they take no grid cells. --}}
        @if(count($groups) >= 5 && count($groups) <= 7)
            <div class="led-ornament led-ornament-left" aria-hidden="true">
                <img src="{{ asset('images/led/ornament-bars.png') }}" alt="" width="241" height="800">
            </div>
            <div class="led-ornament led-ornament-right" aria-hidden="true">
                <img src="{{ asset('images/led/ornament-orbit.png') }}" alt="" width="417" height="800">
            </div>
        @endif
    </div>

  </main>
</div>
