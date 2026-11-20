<?php

namespace Database\Seeders;

use App\Services\Tournament\TournamentSummaryRebuildService;
use App\Services\Tournament\TournamentWriteService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TournamentCompletedDemoSeeder extends Seeder
{
    public function run(TournamentWriteService $writes, TournamentSummaryRebuildService $rebuild): void
    {
        if (! app()->runningInConsole() || ! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo data is allowed only in local or testing environments.');
        }

        $year = config('tournament.year');
        if (! is_int($year) || $year < 1 || $year > 9999) {
            throw new RuntimeException('Set TOURNAMENT_YEAR to a year from 1 to 9999.');
        }

        $writes->run($year, function () use ($year, $rebuild) {
            foreach (['tournament_groups', 'group_teams', 'matches', 'player_tournament_stats'] as $table) {
                if (DB::table($table)->where('tournament_year', $year)->exists()) {
                    throw new RuntimeException('No demo data added: this tournament year already contains records. Use a separate demo database or an unused year.');
                }
            }

            // Reserved addresses cannot receive login email. Existing registrations are untouched.
            $company = DB::table('companies')->insertGetId(['name' => 'DEMO '.$year.' completed organisation', 'max_team_count' => 28]);
            $owner = DB::table('users')->insertGetId([
                'name' => 'DEMO '.$year.' completed owner',
                'email' => 'tournament-completed-demo-'.$year.'@example.invalid', 'company_id' => $company,
            ]);
            $number = 0;
            $players = $qualifiers = [];

            foreach (range(0, 6) as $groupIndex) {
                $letter = chr(65 + $groupIndex);
                $group = DB::table('tournament_groups')->insertGetId(['tournament_year' => $year, 'name' => 'DEMO '.$letter]);
                $teams = [];
                foreach (range(1, 4) as $position) {
                    $name = 'DEMO '.$year.' '.$letter.$position;
                    $team = DB::table('teams')->insertGetId([
                        'user_id' => $owner, 'company_id' => $company, 'team_name' => $name,
                        'captain_email' => strtolower($letter).$position.'@example.invalid',
                        'captain_phone' => '0000000000', 'approved' => true, 'locked' => true,
                    ]);
                    $teams[] = $team;
                    // Seven group winners plus the Group A runner-up fill the eight QF places.
                    $qualified = $position === 1 || ($groupIndex === 0 && $position === 2);
                    DB::table('group_teams')->insert([
                        'tournament_year' => $year, 'group_id' => $group, 'team_id' => $team, 'qualified' => $qualified,
                    ]);
                    if ($qualified) {
                        $qualifiers[] = $team;
                    }
                    foreach (range(0, 11) as $index) {
                        $player = ['name' => $name.' Player '.($index + 1), 'gender' => $index < 3 ? 'Female' : 'Male'];
                        $player['id'] = DB::table('team_members')->insertGetId([
                            'team_id' => $team, ...$player, 'is_captain' => $index === 3,
                        ]);
                        $players[$team][] = $player;
                    }
                }

                // The earlier team wins every pairing: group records are 3/2/1/0 wins.
                foreach ([[0, 1], [0, 2], [0, 3], [1, 2], [1, 3], [2, 3]] as [$one, $two]) {
                    $this->finishedMatch($year, ++$number, 'G', $teams[$one], $teams[$two], $players,
                        $groupIndex % 2 === 0 ? 4 : 6, $group);
                }
            }

            // Keep teams from the same group in opposite halves of the draw.
            $round = [$qualifiers[0], $qualifiers[2], $qualifiers[3], $qualifiers[4],
                $qualifiers[1], $qualifiers[5], $qualifiers[6], $qualifiers[7]];
            foreach (['QF', 'SF', 'F'] as $stage) {
                $winners = [];
                foreach (array_chunk($round, 2) as $slot => [$one, $two]) {
                    $this->finishedMatch($year, ++$number, $stage, $one, $two, $players, 6, null, $slot + 1);
                    $winners[] = $one;
                }
                $round = $winners;
            }

            // Use the production calculators, inside the same atomic year transaction.
            $rebuild->rebuild($year);
        });

        $this->command?->info('Created completed DEMO '.$year.': 7 groups, 28 teams, 336 players, 42 group matches, 4 quarterfinals, 2 semifinals and 1 final, with published results and player statistics. Champion: DEMO '.$year.' A1.');
    }

    private function finishedMatch(int $year, int $number, string $stage, int $one, int $two, array $players, int $balls, ?int $group = null, ?int $slot = null): void
    {
        $publishedAt = now();
        $match = DB::table('matches')->insertGetId([
            'tournament_year' => $year, 'display_number' => $number, 'stage' => $stage,
            'group_id' => $group, 'bracket_position' => $slot, 'team1_id' => $one, 'team2_id' => $two,
            'balls_per_over' => $balls, 'overs_per_innings' => 5, 'max_wickets' => 7,
            'status' => 'finished', 'venue' => 'Ground '.(($number - 1) % 4 + 1), 'scheduled_at' => now()->startOfDay()->addMinutes(30 * $number),
            'result_type' => 'win', 'result_method' => 'normal', 'winner_id' => $one, 'batting_first_id' => $one,
            'team1_runs' => 60, 'team2_runs' => 48, 'team1_wickets' => 3, 'team2_wickets' => 3,
            'team1_deliveries' => 5 * $balls, 'team2_deliveries' => 5 * $balls,
            'result_published_at' => $publishedAt, 'structure_locked_at' => $publishedAt,
            'player_stats_published_at' => $publishedAt, 'player_stats_revision' => 1,
        ]);

        foreach ([$one, $two] as $side => $team) {
            // Four entered batters account for all runs and both bowlers account for all deliveries.
            foreach ([0, 3, 4, 5] as $position => $index) {
                $player = $players[$team][$index];
                $runs = $side === 0 ? [24, 18, 12, 6] : [18, 12, 12, 6];
                DB::table('player_match_stats')->insert([
                    'match_id' => $match, 'team_id' => $team, 'team_member_id' => $player['id'],
                    'player_name' => $player['name'], 'player_gender' => $player['gender'],
                    'record_state' => 'published', 'pending_action' => null, 'published_at' => $publishedAt,
                    'runs_scored' => $runs[$position],
                    'balls_faced' => intdiv(5 * $balls, 4) + (int) ($position < 5 * $balls % 4),
                    'fours' => $position === 0 ? 3 : 0, 'sixes' => $position < 2 ? 1 : 0,
                    'batting_status' => $position < 3 ? 'out' : 'not_out',
                    'bowling_balls' => $position < 2 ? intdiv(5 * $balls, 2) : 0,
                    'runs_conceded' => $position < 2 ? ($side === 0 ? 24 : 30) : 0,
                    'wickets_taken' => $position < 2 ? 2 - $position : 0,
                    'dot_balls' => $position < 2 ? 3 + $position : 0,
                ]);
            }
        }
    }
}
