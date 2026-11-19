<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $identity = match (DB::getDriverName()) {
            'pgsql' => 'BIGSERIAL PRIMARY KEY',
            'sqlite' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            default => throw new RuntimeException('Player statistics require PostgreSQL or SQLite.'),
        };

        // Only these fixed column names are used to build SQL, never request values.
        $metrics = ['runs_scored', 'balls_faced', 'fours', 'sixes', 'bowling_balls', 'runs_conceded', 'wickets_taken', 'dot_balls'];
        $columns = [];
        $coverageChecks = [];

        foreach ($metrics as $metric) {
            $count = $metric.'_recorded_count';
            $columns[] = "{$metric} BIGINT NULL CHECK ({$metric} BETWEEN 0 AND 9223372036854775807 AND {$metric} = CAST({$metric} AS BIGINT))";
            $columns[] = "{$count} INTEGER NOT NULL DEFAULT 0 CHECK ({$count} BETWEEN 0 AND 2147483647 AND {$count} = CAST({$count} AS INTEGER))";
            $coverageChecks[] = "CONSTRAINT pts_{$metric}_coverage CHECK (({$count} = 0 AND {$metric} IS NULL) OR ({$count} > 0 AND {$metric} IS NOT NULL))";
        }

        $metricColumns = implode(",\n", $columns);
        $coverageConstraints = implode(",\n", $coverageChecks);

        // Shared CHECK constraints keep null/zero coverage semantics on both engines.
        DB::statement(<<<SQL
            CREATE TABLE player_tournament_stats (
                id {$identity},
                tournament_year INTEGER NOT NULL CHECK (tournament_year BETWEEN 1 AND 9999 AND tournament_year = CAST(tournament_year AS INTEGER)),
                team_member_id BIGINT NOT NULL,
                team_id BIGINT NOT NULL,
                player_name VARCHAR(255) NOT NULL,
                player_gender VARCHAR(16) NOT NULL CHECK (player_gender IN ('Male', 'Female', 'Other')),

                {$metricColumns},

                highest_score INTEGER NULL CHECK (highest_score BETWEEN 0 AND 2147483647 AND highest_score = CAST(highest_score AS INTEGER)),
                highest_score_match_id BIGINT NULL,
                updated_at TIMESTAMP NULL,

                {$coverageConstraints},

                CONSTRAINT player_tournament_stats_year_player_unique UNIQUE (tournament_year, team_member_id),
                CONSTRAINT pts_highest_score_consistency_check CHECK (
                    (runs_scored IS NULL AND highest_score IS NULL)
                    OR (runs_scored IS NOT NULL AND highest_score IS NOT NULL AND highest_score <= runs_scored)
                ),
                CONSTRAINT player_tournament_stats_highest_match_check CHECK (
                    highest_score IS NOT NULL OR highest_score_match_id IS NULL
                ),
                CONSTRAINT player_tournament_stats_member_fk FOREIGN KEY (team_member_id)
                    REFERENCES team_members (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT player_tournament_stats_team_fk FOREIGN KEY (team_id)
                    REFERENCES teams (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT player_tournament_stats_best_match_fk FOREIGN KEY (highest_score_match_id)
                    REFERENCES matches (id) ON DELETE RESTRICT ON UPDATE RESTRICT
            )
            SQL);

        // The six public ranking categories; gender can filter these small lists.
        foreach (['runs_scored', 'wickets_taken', 'sixes', 'fours', 'dot_balls', 'highest_score'] as $metric) {
            DB::statement("CREATE INDEX pts_{$metric}_ranking_idx ON player_tournament_stats (tournament_year, {$metric}, team_member_id)");
        }

        DB::statement('CREATE INDEX player_tournament_stats_member_idx ON player_tournament_stats (team_member_id)');
        DB::statement('CREATE INDEX player_tournament_stats_team_idx ON player_tournament_stats (team_id)');
        DB::statement('CREATE INDEX player_tournament_stats_best_match_idx ON player_tournament_stats (highest_score_match_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('player_tournament_stats');
    }
};
