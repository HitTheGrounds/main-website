<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'Grounds screen'])
    </head>
    {{-- Grounds venue LED screen: 2:1 aspect ratio, no scorer navigation, auto-refreshing. --}}
    <body class="led-body min-h-screen">
        <div class="led-viewport">
            <main class="tc led-stage led-grounds-stage" aria-labelledby="grounds-screen-heading">
                <header class="led-header">
                    <div class="led-logo">
                        <img src="{{ asset('images/htg-logo-horizontal.webp') }}" alt="Hit the Grounds {{ $year }}, powered by GTN" width="900" height="481">
                    </div>
                    <div class="led-title">
                        <span class="led-live">Live</span>
                        <h1 id="grounds-screen-heading">Grounds <span>Schedule</span></h1>
                    </div>
                    <div class="led-header-clock" aria-label="Current venue time">
                        <span class="led-clock-dot" aria-hidden="true"></span>
                        <time id="venue-clock">--:--</time>
                    </div>
                </header>

                @php
                    $stages = ['G' => 'Group stage', 'QF' => 'Quarter-final', 'SF' => 'Semi-final', 'F' => 'Final'];
                    $tz = config('tournament.timezone');
                    $formatTime = fn (?string $iso) => $iso ? \Carbon\CarbonImmutable::parse($iso)->setTimezone($tz)->format('g:i A') : null;
                    $grounds = $groundsData['grounds'] ?? [];
                @endphp

                <div class="led-grounds-grid" role="region" aria-label="Ground schedules">
                    @forelse ($grounds as $ground)
                        @php
                            $groundNum = $loop->iteration;
                            $liveMatch = $ground['live_match'] ?? null;
                            $upcoming = $ground['upcoming_matches'] ?? [];
                            $featuredMatch = $liveMatch ?? ($upcoming[0] ?? null);
                            $isLive = $liveMatch !== null;
                            $isFeaturedNext = ! $isLive && $featuredMatch !== null;
                            $scheduleList = $isLive ? $upcoming : array_slice($upcoming, 1);
                            $latestFinished = $ground['latest_finished'] ?? null;
                            $hasMatches = $featuredMatch !== null || ! empty($scheduleList) || $latestFinished !== null;
                        @endphp

                        <section class="led-ground-card led-ground-card-{{ $groundNum }}" aria-labelledby="ground-heading-{{ $groundNum }}">
                            {{-- Cricket ground header bar with stadium emblem and live status --}}
                            <header class="led-ground-head">
                                <div class="led-ground-head-title">
                                    <span class="led-ground-tag" aria-hidden="true">G{{ $groundNum }}</span>
                                    <svg class="led-ground-icon" viewBox="0 0 32 20" fill="none" stroke="currentColor" aria-hidden="true">
                                        <ellipse cx="16" cy="10" rx="14" ry="8" stroke-width="1.5" stroke-dasharray="3 2" />
                                        <rect x="13.5" y="5.5" width="5" height="9" rx="0.5" fill="currentColor" fill-opacity="0.3" stroke-width="1" />
                                        <line x1="13.5" y1="7" x2="18.5" y2="7" stroke-width="0.8" />
                                        <line x1="13.5" y1="13" x2="18.5" y2="13" stroke-width="0.8" />
                                    </svg>
                                    <h2 id="ground-heading-{{ $groundNum }}" class="led-ground-name">{{ $ground['venue'] }}</h2>
                                </div>
                                <div class="led-ground-head-status">
                                    @if ($isLive)
                                        <span class="led-ground-pill is-live">
                                            <span class="led-pill-pulse" aria-hidden="true"></span>
                                            LIVE NOW
                                        </span>
                                    @elseif ($isFeaturedNext && $featuredMatch['scheduled_at'])
                                        <span class="led-ground-pill is-upcoming">
                                            <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                            NEXT: {{ $formatTime($featuredMatch['scheduled_at']) }}
                                        </span>
                                    @elseif ($latestFinished && empty($upcoming))
                                        <span class="led-ground-pill is-concluded">
                                            CONCLUDED
                                        </span>
                                    @else
                                        <span class="led-ground-pill is-idle">
                                            AVAILABLE
                                        </span>
                                    @endif
                                </div>
                            </header>

                            <div class="led-ground-body">
                                {{-- Left Column: Active / Next Match Arena --}}
                                <div class="led-ground-arena">
                                    @if ($featuredMatch)
                                        @php
                                            $stageLabel = $featuredMatch['group']['name'] ?? ($stages[$featuredMatch['stage']] ?? $featuredMatch['stage']);
                                            $matchNum = $featuredMatch['display_number'];
                                            $team1 = $featuredMatch['team1'];
                                            $team2 = $featuredMatch['team2'];
                                            $battingFirstId = $featuredMatch['batting_first_id'] ?? null;
                                        @endphp
                                        <div class="led-arena-top">
                                            <div class="led-arena-badge-group">
                                                <span @class(['led-arena-badge', 'is-live' => $isLive, 'is-next' => ! $isLive])>
                                                    @if ($isLive)
                                                        <span class="led-pill-pulse-sm" aria-hidden="true"></span>
                                                        In progress
                                                    @else
                                                        Next match
                                                    @endif
                                                </span>
                                                <span class="led-arena-stage">
                                                    {{ $stageLabel }}@if ($matchNum !== null) · Match {{ $matchNum }}@endif
                                                </span>
                                            </div>
                                            @if (! $isLive && $featuredMatch['scheduled_at'])
                                                <time class="led-arena-time" datetime="{{ $featuredMatch['scheduled_at'] }}">
                                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                                    {{ $formatTime($featuredMatch['scheduled_at']) }}
                                                </time>
                                            @endif
                                        </div>

                                        <div class="led-arena-teams">
                                            <div class="led-arena-team">
                                                <x-tournament.team-badge :name="$team1['name']" class="led-team-badge" />
                                                <div class="led-arena-team-info">
                                                    <span class="led-arena-team-name" title="{{ $team1['name'] }}">{{ $team1['name'] }}</span>
                                                    @if ($battingFirstId !== null && (int) $battingFirstId === (int) $team1['id'])
                                                        <span class="led-arena-bat-pill">Bats first</span>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="led-arena-vs" aria-hidden="true">
                                                <span>VS</span>
                                            </div>

                                            <div class="led-arena-team">
                                                <x-tournament.team-badge :name="$team2['name']" class="led-team-badge" />
                                                <div class="led-arena-team-info">
                                                    <span class="led-arena-team-name" title="{{ $team2['name'] }}">{{ $team2['name'] }}</span>
                                                    @if ($battingFirstId !== null && (int) $battingFirstId === (int) $team2['id'])
                                                        <span class="led-arena-bat-pill">Bats first</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        <div class="led-arena-foot">
                                            <div class="led-arena-rules">
                                                <span>{{ $featuredMatch['overs_per_innings'] }} overs</span>
                                                <span>·</span>
                                                <span>{{ $featuredMatch['balls_per_over'] }} balls/over</span>
                                                <span>·</span>
                                                <span>Max {{ $featuredMatch['max_wickets'] }} wkts</span>
                                            </div>
                                            @if ($isLive)
                                                <span class="led-arena-live-note">Scores published on completion</span>
                                            @endif
                                        </div>
                                    @elseif ($latestFinished)
                                        {{-- If all matches finished on this ground, show latest result --}}
                                        <div class="led-arena-concluded">
                                            <span class="led-concluded-badge">All matches completed</span>
                                            <div class="led-concluded-result">
                                                <p class="led-concluded-label">Latest ground result</p>
                                                <p class="led-concluded-desc">
                                                    {{ $latestFinished['result']['description'] ?? ($latestFinished['team1']['name'].' vs '.$latestFinished['team2']['name']) }}
                                                </p>
                                            </div>
                                        </div>
                                    @else
                                        {{-- No matches scheduled --}}
                                        <div class="led-arena-empty">
                                            <svg class="led-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                <ellipse cx="12" cy="12" rx="10" ry="6" stroke-dasharray="3 3"/>
                                                <line x1="12" y1="8" x2="12" y2="16"/>
                                            </svg>
                                            <p class="led-empty-title">Ground available</p>
                                            <p class="led-empty-sub">No fixtures currently underway</p>
                                        </div>
                                    @endif

                                    {{-- Cricket ground watermark in the background --}}
                                    <svg class="led-ground-watermark" viewBox="0 0 100 60" fill="none" stroke="currentColor" aria-hidden="true">
                                        <ellipse cx="50" cy="30" rx="46" ry="26" stroke-width="0.8" stroke-dasharray="3 3" />
                                        <ellipse cx="50" cy="30" rx="32" ry="18" stroke-width="0.8" stroke-dasharray="2 2" />
                                        <rect x="45" y="20" width="10" height="20" rx="1" stroke-width="0.8" />
                                        <line x1="45" y1="24" x2="55" y2="24" stroke-width="0.6" />
                                        <line x1="45" y1="36" x2="55" y2="36" stroke-width="0.6" />
                                    </svg>
                                </div>

                                {{-- Right Column: Upcoming Schedule on this Ground --}}
                                <div class="led-ground-schedule">
                                    <div class="led-sched-head">
                                        <h3 class="led-sched-title">Upcoming</h3>
                                        <span class="led-sched-count">{{ count($scheduleList) }} fixture{{ count($scheduleList) === 1 ? '' : 's' }}</span>
                                    </div>

                                    @if (! empty($scheduleList))
                                        <div class="led-sched-list" role="list">
                                            @foreach (array_slice($scheduleList, 0, 3) as $m)
                                                @php
                                                    $itemStage = $m['group']['name'] ?? ($stages[$m['stage']] ?? $m['stage']);
                                                @endphp
                                                <div class="led-sched-item" role="listitem">
                                                    <div class="led-sched-item-time">
                                                        {{ $formatTime($m['scheduled_at']) ?? 'TBD' }}
                                                    </div>
                                                    <div class="led-sched-item-main">
                                                        <div class="led-sched-item-meta">
                                                            <span>Match {{ $m['display_number'] ?? '–' }}</span>
                                                            <span aria-hidden="true">·</span>
                                                            <span>{{ $itemStage }}</span>
                                                        </div>
                                                        <div class="led-sched-item-matchup">
                                                            <span class="truncate">{{ $m['team1']['name'] }}</span>
                                                            <span class="led-sched-vs" aria-hidden="true">vs</span>
                                                            <span class="truncate">{{ $m['team2']['name'] }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="led-sched-empty">
                                            @if ($featuredMatch && ! $isLive)
                                                <p>Next match shown in active pane</p>
                                            @elseif ($latestFinished)
                                                <p>All scheduled matches completed</p>
                                            @else
                                                <p>No upcoming matches</p>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </section>
                    @empty
                        <p class="led-empty">No grounds configured for this tournament year.</p>
                    @endforelse
                </div>
            </main>
        </div>

        <script>
            (() => {
                // Live venue clock in Sri Lanka time (Asia/Colombo)
                const clockEl = document.getElementById('venue-clock');
                const updateClock = () => {
                    if (!clockEl) return;
                    try {
                        const now = new Date();
                        clockEl.textContent = now.toLocaleTimeString('en-US', {
                            timeZone: @js(config('tournament.timezone')),
                            hour: 'numeric',
                            minute: '2-digit',
                            hour12: true
                        });
                    } catch (e) {
                        const now = new Date();
                        clockEl.textContent = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
                    }
                };
                updateClock();
                setInterval(updateClock, 1000);

                // Polling tournament revision for real-time match state updates
                const rev = @js($rev);
                setInterval(async () => {
                    if (document.hidden) return;
                    try {
                        const response = await fetch(@js(route('tournament.rev')), { cache: 'no-store' });
                        if (response.ok && (await response.json()).rev !== rev) location.reload();
                    } catch (e) { /* Retry on next tick */ }
                }, 10000);

                // Periodic fallback reload every 60s to advance schedule and times
                setInterval(() => {
                    if (document.hidden) return;
                    location.reload();
                }, 60000);
            })();
        </script>
    </body>
</html>
