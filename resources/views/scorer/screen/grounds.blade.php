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
                            {{-- Ground header bar: tag and name --}}
                            <header class="led-ground-head">
                                <div class="led-ground-head-title">
                                    <span class="led-ground-tag" aria-hidden="true">G{{ $groundNum }}</span>
                                    <h2 id="ground-heading-{{ $groundNum }}" class="led-ground-name">{{ $ground['venue'] }}</h2>
                                </div>
                                {{-- No LIVE NOW / NEXT pills: a ground with a current or next match shows just its name. --}}
                                <div class="led-ground-head-status">
                                    @if ($isLive || $isFeaturedNext)
                                        {{-- intentionally empty --}}
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
                                {{-- Left column: the ground (3D) on top, the current match (live, next, last result or free) below. --}}
                                <div class="led-ground-arena">
                                    {{-- 3D cricket ground with low-poly players (Blender source: resources/blender/cricket-ground.blend).
                                         Four match scenes in four sets of kits, one per ground (repeating after four). --}}
                                    @php
                                        $sceneNum = (($groundNum - 1) % 4) + 1;
                                    @endphp
                                    <div class="led-ground-art">
                                        {{-- 4-second looping animation (transparent WebM). The still is the poster and the fallback. --}}
                                        <video src="{{ asset('images/led/cricket-ground-'.$sceneNum.'.webm') }}" poster="{{ asset('images/led/cricket-ground-'.$sceneNum.'.webp') }}"
                                               width="1200" height="646" autoplay muted loop playsinline preload="auto" aria-hidden="true"></video>
                                    </div>

                                    <div class="led-ground-match">
                                    @if ($featuredMatch)
                                        {{-- Just who plays whom: live/next status and start time are already in the card header. --}}
                                        @php
                                            $team1 = $featuredMatch['team1'];
                                            $team2 = $featuredMatch['team2'];
                                        @endphp
                                        <div class="led-arena-teams is-row" aria-label="{{ $isLive ? 'In progress' : 'Next match' }}: {{ $team1['name'] }} versus {{ $team2['name'] }}">
                                            <div class="led-arena-team">
                                                <x-tournament.team-badge :name="$team1['name']" class="led-team-badge" />
                                                <span class="led-arena-team-name">{{ $team1['name'] }}</span>
                                            </div>
                                            <div class="led-arena-vs" aria-hidden="true"><span>VS</span></div>
                                            <div class="led-arena-team led-arena-team-right">
                                                <x-tournament.team-badge :name="$team2['name']" class="led-team-badge" />
                                                <span class="led-arena-team-name">{{ $team2['name'] }}</span>
                                            </div>
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
                                            <p class="led-empty-title">Ground available</p>
                                            <p class="led-empty-sub">No fixtures currently underway</p>
                                        </div>
                                    @endif
                                    </div>
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
                                                    <div class="led-sched-item-main">
                                                        <div class="led-sched-item-meta">
                                                            <span>Match {{ $m['display_number'] ?? '–' }}</span>
                                                            <span aria-hidden="true">·</span>
                                                            <span>{{ $itemStage }}</span>
                                                        </div>
                                                        <div class="led-sched-item-matchup">
                                                            <span class="led-sched-team">{{ $m['team1']['name'] }}</span>
                                                            <span class="led-sched-vs">vs</span>
                                                            <span class="led-sched-team">{{ $m['team2']['name'] }}</span>
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

                // Ground animations: start them explicitly (some kiosk browsers ignore the autoplay attribute)
                // and restart after the screen has been hidden.
                const playGrounds = () => document.querySelectorAll('.led-ground-art video').forEach((video) => {
                    if (video.paused) video.play().catch(() => { /* the poster stays visible */ });
                });
                document.querySelectorAll('.led-ground-art video').forEach((video) => {
                    video.addEventListener('canplay', playGrounds, { once: true });
                    // The browser may pause media when the page is briefly hidden or resized; resume right away.
                    video.addEventListener('pause', () => setTimeout(() => { if (!document.hidden) playGrounds(); }, 300));
                });
                document.addEventListener('visibilitychange', () => { if (!document.hidden) playGrounds(); });
                setInterval(() => { if (!document.hidden) playGrounds(); }, 5000);  // backstop for an unattended wall
                playGrounds();

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
