<?php

namespace Database\Seeders;

use App\Services\Tournament\TournamentSummaryRebuildService;
use App\Services\Tournament\TournamentWriteService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TournamentDemoSeeder extends Seeder
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
            // Reserved .invalid addresses cannot receive real login email.
            // No factories, mail, files, permission changes or existing-row edits.
            $company = DB::table('companies')->insertGetId(['name' => 'DEMO '.$year.' organisation', 'max_team_count' => 28]);
            $owner = DB::table('users')->insertGetId(['name' => 'DEMO '.$year.' owner',
                'email' => 'tournament-demo-'.$year.'@example.invalid', 'company_id' => $company]);
            $number = 0;
            foreach (range(0, 6) as $groupIndex) {
                $letter = chr(65 + $groupIndex);
                $group = DB::table('tournament_groups')->insertGetId(['tournament_year' => (int) $year, 'name' => 'DEMO '.$letter]);
                $teams = $players = [];
                foreach (range(1, 4) as $position) {
                    $name = 'DEMO '.$year.' '.$letter.$position;
                    $team = DB::table('teams')->insertGetId(['user_id' => $owner, 'company_id' => $company,
                        'team_name' => $name, 'captain_email' => strtolower($letter).$position.'@example.invalid',
                        'captain_phone' => '0000000000', 'approved' => true, 'locked' => true]);
                    $teams[] = $team;
                    DB::table('group_teams')->insert(['tournament_year' => (int) $year, 'group_id' => $group, 'team_id' => $team]);
                    foreach (range(0, 11) as $index) {
                        $playerName = $name.' Player '.($index + 1);
                        $gender = $index < 3 ? 'Female' : 'Male';
                        $id = DB::table('team_members')->insertGetId(['team_id' => $team, 'name' => $playerName,
                            'gender' => $gender, 'is_captain' => $index === 3]);
                        $players[$team][] = ['id' => $id, 'name' => $playerName, 'gender' => $gender];
                    }
                }
                foreach ([[0, 1], [0, 2], [0, 3], [1, 2], [1, 3], [2, 3]] as $fixture => [$one, $two]) {
                    $this->match((int) $year, $group, ++$number, $fixture, $groupIndex, $teams[$one], $teams[$two], $players);
                }
            }
            // Use the same calculators as normal publication, atomically.
            $rebuild->rebuild((int) $year);
        });
        $this->command?->info('Created DEMO '.$year.': 7 groups, 28 teams, 336 players and 42 group matches, with published rankings and unpublished drafts.');
    }

    private function match(int $year, int $group, int $number, int $fixture, int $groupIndex, int $one, int $two, array $players): void
    {
        $balls = $groupIndex % 2 === 0 ? 4 : 6;
        $published = $fixture < 5;
        $eligible = $fixture < 4;
        $tie = $fixture === 3;
        $match = DB::table('matches')->insertGetId(['tournament_year' => $year, 'display_number' => $number,
            'stage' => 'G', 'group_id' => $group, 'team1_id' => $one, 'team2_id' => $two, 'balls_per_over' => $balls,
            'status' => $published ? 'finished' : ($groupIndex % 2 === 0 ? 'upcoming' : 'live'),
            'venue' => 'Ground '.(($number - 1) % 4 + 1), 'scheduled_at' => now()->startOfDay()->addMinutes(30 * $number),
            'result_type' => $published ? ($eligible ? ($tie ? 'tie' : 'win') : 'no_result') : null,
            'result_method' => $eligible ? 'normal' : null, 'winner_id' => $fixture < 3 ? $one : null,
            'batting_first_id' => $eligible ? $one : null,
            'team1_runs' => $eligible ? 60 : null, 'team2_runs' => $eligible ? ($tie ? 60 : 48) : null,
            'team1_wickets' => $eligible ? 3 : null, 'team2_wickets' => $eligible ? 3 : null,
            'team1_deliveries' => $eligible ? 5 * $balls : null, 'team2_deliveries' => $eligible ? 5 * $balls : null,
            'result_published_at' => $published ? now() : null, 'structure_locked_at' => $published ? now() : null,
            'player_stats_published_at' => $eligible ? now() : null, 'player_stats_revision' => $eligible ? 1 : 0]);
        if (! $eligible) {
            return;
        }
        foreach ([$one, $two] as $side => $team) {
            // Entered players include both genders; others deliberately remain unentered.
            foreach ([0, 3, 4, 5] as $position => $index) {
                $player = $players[$team][$index];
                $runs = $side === 0 || $tie ? [24, 18, 12, 6] : [18, 12, 12, 6];
                $row = ['match_id' => $match, 'team_id' => $team, 'team_member_id' => $player['id'],
                    'player_name' => $player['name'], 'player_gender' => $player['gender'], 'record_state' => 'published', 'pending_action' => null,
                    'published_at' => now(), 'runs_scored' => $runs[$position], 'balls_faced' => intdiv(5 * $balls, 4) + (int) ($position < 5 * $balls % 4),
                    'fours' => $position === 0 ? 3 : 0, 'sixes' => $position < 2 ? 1 : 0,
                    'batting_status' => $position < 3 ? 'out' : 'not_out',
                    'bowling_balls' => $position < 2 ? intdiv(5 * $balls, 2) : 0,
                    'runs_conceded' => $position < 2 ? (($side === 0 && ! $tie) ? 24 : 30) : 0,
                    'wickets_taken' => $position < 2 ? 2 - $position : 0, 'dot_balls' => $position < 2 ? 3 + $position : 0];
                DB::table('player_match_stats')->insert($row);
                if ($fixture === 0 && $side === 0 && $position === 0) {
                    // Pending correction: public figures must stay unchanged until published.
                    $row['record_state'] = 'draft';
                    $row['pending_action'] = 'replace';
                    $row['published_at'] = null;
                    $row['runs_scored'] = 25;
                    DB::table('player_match_stats')->insert($row);
                }
            }
        }
        if ($fixture === 1) {
            $player = $players[$one][1];
            DB::table('player_match_stats')->insert(['match_id' => $match, 'team_id' => $one, 'team_member_id' => $player['id'],
                'player_name' => $player['name'], 'player_gender' => $player['gender'], 'record_state' => 'draft',
                'pending_action' => 'replace', 'dot_balls' => 0]);
        }
    }
}
