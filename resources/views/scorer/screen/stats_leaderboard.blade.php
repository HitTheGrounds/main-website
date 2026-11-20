<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'Stats leaderboard screen'])
    </head>
    <body class="min-h-screen bg-base-100 text-base-content">
        <div class="led-viewport">
            <main class="tc led-stage led-leaderboard led-stats-leaderboard" aria-labelledby="stats-screen-heading">
                <header class="flex shrink-0 items-center justify-between border-b-2 border-primary pb-[.8cqh]">
                    <img src="{{ asset('images/htg-logo-horizontal.webp') }}" alt="Hit the Grounds {{ $year }}, powered by GTN" width="900" height="481" class="h-[6cqh] w-auto">
                    <h1 id="stats-screen-heading" class="text-[3.4cqh] font-extrabold uppercase text-primary-ink">Player leaderboards</h1>
                </header>
                <div class="led-cap-columns led-stats-columns">
                    @foreach ($leaderboards as $board)
                        <section class="led-cap-panel led-stat-{{ $board['category'] }}" aria-labelledby="stat-{{ $board['category'] }}-heading">
                            <header class="led-cap-heading">
                                <img src="{{ asset('images/led/stat-'.str_replace('_', '-', $board['category']).'.png') }}" alt="" class="led-stat-icon">
                                <div>
                                    <h2 id="stat-{{ $board['category'] }}-heading">{{ $board['title'] }}</h2>
                                    <p>Top 5 players</p>
                                </div>
                            </header>
                            @if ($board['entries'] === [])
                                <p class="led-cap-empty">No published {{ strtolower($board['metric']) }} figures yet.</p>
                            @else
                                <table class="tc-stats-leaderboard led-cap-table">
                                    <caption class="sr-only">{{ $board['title'] }}, top five players</caption>
                                    {{-- Fixed layout takes widths from the first row, which is the hidden header,
                                         so the columns are sized here: rank, team logo, player, score. --}}
                                    <colgroup>
                                        <col style="width: 11%"><col style="width: 21%"><col><col style="width: 17%">
                                    </colgroup>
                                    <thead class="sr-only"><tr>
                                        <th scope="col">Rank</th><th scope="col">Team</th><th scope="col">Player</th><th scope="col">{{ $board['metric'] }}</th>
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
                                                <td class="font-bold tabular-nums"><span class="tc-stats-score">{{ $entry['value'] }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
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
