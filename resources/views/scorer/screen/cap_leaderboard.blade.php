<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'Cap leaderboard screen'])
    </head>
    <body class="min-h-screen bg-base-100 text-base-content">
        <div class="led-viewport">
            <main class="tc led-stage led-leaderboard" aria-labelledby="leaderboard-screen-heading">
                <header class="flex shrink-0 items-center justify-between border-b-2 border-primary pb-[.8cqh]">
                    <img src="{{ asset('images/htg-logo-horizontal.webp') }}" alt="Hit the Grounds {{ $year }}, powered by GTN" width="900" height="481" class="h-[6cqh] w-auto">
                    <h1 id="leaderboard-screen-heading" class="text-[3.4cqh] font-extrabold uppercase text-primary-ink">Cap leaderboards</h1>
                </header>
                <div class="led-cap-columns">
                    @foreach ($leaderboards as $board)
                        <section class="led-cap-panel led-cap-{{ $board['category'] }}" aria-labelledby="cap-{{ $board['category'] }}-heading">
                            <header class="led-cap-heading">
                                <img src="{{ asset('award/'.strtolower($board['cap']).'_cap.avif') }}" alt="" width="80" height="80">
                                <div>
                                    <h2 id="cap-{{ $board['category'] }}-heading">{{ $board['cap'] }} Cap</h2>
                                    <p>Top 5 · Most {{ $board['category'] }}</p>
                                </div>
                            </header>
                            @if ($board['entries'] === [])
                                <p class="led-cap-empty">No published {{ $board['category'] }} figures yet.</p>
                            @else
                                <table class="tc-stats-leaderboard led-cap-table">
                                    <caption class="sr-only">{{ $board['cap'] }} Cap leaderboard, top five players by {{ $board['category'] }}</caption>
                                    @php
                                        // Secondary stat beside the main figure: strike rate for runs, economy for wickets.
                                        // MOCK values for layout only: replace with real figures once balls faced / runs conceded are recorded.
                                        $secondary = match ($board['category']) {
                                            'runs' => ['title' => 'Strike rate', 'mock' => ['182.40', '165.00', '158.33', '149.67', '141.18']],
                                            'wickets' => ['title' => 'Economy', 'mock' => ['5.25', '6.10', '6.75', '7.00', '7.40']],
                                            default => null,
                                        };
                                    @endphp
                                    {{-- Columns: rank, team logo, player, [secondary stat,] score. --}}
                                    <colgroup>
                                        <col style="width: 9%"><col style="width: 15%"><col>
                                        @if ($secondary)<col style="width: 19%">@endif
                                        <col style="width: {{ $secondary ? 17 : 20 }}%">
                                    </colgroup>
                                    {{-- Only the stat columns get visible titles; rank, team and player stay for screen readers. --}}
                                    <thead class="led-cap-head"><tr>
                                        <th scope="col"><span class="sr-only">Rank</span></th>
                                        <th scope="col"><span class="sr-only">Team</span></th>
                                        <th scope="col"><span class="sr-only">Player</span></th>
                                        @if ($secondary)<th scope="col" class="led-cap-sr-head">{{ $secondary['title'] }}</th>@endif
                                        <th scope="col">{{ ucfirst($board['category']) }}</th>
                                    </tr></thead>
                                    <tbody>
                                        @foreach ($board['entries'] as $entry)
                                            <tr>
                                                <td class="font-semibold tabular-nums">{{ $entry['rank'] }}</td>
                                                <td><x-tournament.team-badge :name="$entry['team_name']" /></td>
                                                <th scope="row">
                                                    {{ $entry['player_name'] }}
                                                    <span class="tc-stats-team">{{ $entry['team_name'] }}</span>
                                                </th>
                                                @if ($secondary)
                                                    <td class="led-cap-sr tabular-nums">{{ $secondary['mock'][$loop->index] ?? '–' }}</td>
                                                @endif
                                                <td class="font-bold tabular-nums">
                                                    <span class="tc-stats-score">
                                                        @if (in_array($entry['player_id'], $board['leader_ids'], true))
                                                            <img src="{{ asset('award/'.strtolower($board['cap']).'_cap.avif') }}" alt="{{ $board['cap'] }} Cap holder" width="32" height="32">
                                                        @endif
                                                        <span>{{ $entry['value'] }}</span>
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                @if ($board['leader_ids'] === [])
                                    <p class="led-cap-note">No cap holder yet. All recorded figures are zero.</p>
                                @endif
                            @endif
                        </section>
                    @endforeach
                </div>
            </main>
        </div>
        <script>
            (() => {
                const rev = @js($rev);
                setInterval(async () => {
                    if (document.hidden) return;
                    try {
                        const response = await fetch(@js(route('tournament.rev')), { cache: 'no-store' });
                        if (response.ok && (await response.json()).rev !== rev) location.reload();
                    } catch (e) { /* Retry on the next tick after a network interruption. */ }
                }, 10000);
            })();
        </script>
    </body>
</html>
