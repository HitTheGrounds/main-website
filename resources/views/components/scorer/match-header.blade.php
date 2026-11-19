@props(['match', 'active', 'title'])
{{-- Shared header for a match's scorer pages (Team result, Player figures):
     back link, match strip and tabs between the two pages. --}}
@php
    $stageLabels = ['G' => 'Group', 'QF' => 'Quarter-final', 'SF' => 'Semi-final', 'F' => 'Final'];
    $statusLabels = ['upcoming' => 'Upcoming', 'live' => 'In progress', 'finished' => 'Finished'];
    $tabs = [
        'result' => ['scorer.matches.result', 'Team result', 'scoreboard'],
        'players' => ['scorer.matches.players', 'Player figures', 'users'],
    ];
@endphp
<header class="sr-header">
    <div class="sr-header-top">
        <a href="{{ route('scorer.dashboard') }}" class="sr-back">
            <x-scorer.icon name="arrow-left" /> Back to matches
        </a>
        <span class="sd-status sd-status-{{ $match->status }}">
            @if($match->status === 'live')<i class="sd-live-dot" aria-hidden="true"></i>
            @else<x-scorer.icon :name="$match->status === 'finished' ? 'check' : 'clock'" />@endif
            {{ $statusLabels[$match->status] }}
        </span>
    </div>

    <h1 class="sr-title">{{ $title }}</h1>
    <div class="sr-strip">
        <p class="sr-teams">
            <span class="sr-team"><x-tournament.team-badge :name="$match->team1->team_name" /><span>{{ $match->team1->team_name }}</span></span>
            <span class="sr-vs" aria-hidden="true">vs</span><span class="sr-only"> versus </span>
            <span class="sr-team"><x-tournament.team-badge :name="$match->team2->team_name" /><span>{{ $match->team2->team_name }}</span></span>
        </p>
        <p class="sr-context">
            <span>{{ $match->group?->name ?? $stageLabels[$match->stage] ?? $match->stage }}</span>
            @if($match->display_number !== null)<span aria-hidden="true">·</span><span>Match {{ $match->display_number }}</span>@endif
            <span aria-hidden="true">·</span><span>{{ $match->balls_per_over }} balls per over | Five overs | Seven wickets</span>
        </p>
    </div>

    <nav class="sr-tabs" aria-label="Match tools">
        @foreach($tabs as $key => [$route, $label, $icon])
            <a href="{{ route($route, ['matchId' => $match->id]) }}" @if($active === $key) aria-current="page" @endif @class(['sr-tab', 'is-active' => $active === $key])>
                <x-scorer.icon :name="$icon" /> {{ $label }}
            </a>
        @endforeach
    </nav>
</header>
