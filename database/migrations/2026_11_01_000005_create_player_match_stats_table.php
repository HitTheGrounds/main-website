<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Use one CHECK-constrained definition for both supported databases.
        // Laravel's schema builder does not expose table-level CHECK constraints.
        $identity = match (DB::getDriverName()) {
            'pgsql' => 'BIGSERIAL PRIMARY KEY',
            'sqlite' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            default => throw new RuntimeException('Player statistics require PostgreSQL or SQLite.'),
        };

        DB::statement(<<<SQL
            CREATE TABLE player_match_stats (
                id {$identity},
                match_id BIGINT NOT NULL,
                team_id BIGINT NOT NULL,
                team_member_id BIGINT NOT NULL,
                record_state VARCHAR(16) NOT NULL DEFAULT 'draft',
                player_name VARCHAR(255) NOT NULL,
                player_gender VARCHAR(16) NOT NULL,

                runs_scored INTEGER NULL CHECK (runs_scored BETWEEN 0 AND 2147483647 AND runs_scored = CAST(runs_scored AS INTEGER)),
                balls_faced INTEGER NULL CHECK (balls_faced BETWEEN 0 AND 2147483647 AND balls_faced = CAST(balls_faced AS INTEGER)),
                fours INTEGER NULL CHECK (fours BETWEEN 0 AND 2147483647 AND fours = CAST(fours AS INTEGER)),
                sixes INTEGER NULL CHECK (sixes BETWEEN 0 AND 2147483647 AND sixes = CAST(sixes AS INTEGER)),
                batting_status VARCHAR(32) NULL,
                bowling_balls INTEGER NULL CHECK (bowling_balls BETWEEN 0 AND 2147483647 AND bowling_balls = CAST(bowling_balls AS INTEGER)),
                runs_conceded INTEGER NULL CHECK (runs_conceded BETWEEN 0 AND 2147483647 AND runs_conceded = CAST(runs_conceded AS INTEGER)),
                wickets_taken INTEGER NULL CHECK (wickets_taken BETWEEN 0 AND 11 AND wickets_taken = CAST(wickets_taken AS INTEGER)),
                dot_balls INTEGER NULL CHECK (dot_balls BETWEEN 0 AND 2147483647 AND dot_balls = CAST(dot_balls AS INTEGER)),

                pending_action VARCHAR(16) NULL DEFAULT 'replace',
                published_at TIMESTAMP NULL,
                version BIGINT NOT NULL DEFAULT 1 CHECK (version >= 1 AND version = CAST(version AS BIGINT)),
                updated_by BIGINT NULL,
                published_by BIGINT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,

                CONSTRAINT player_match_stats_identity_unique UNIQUE (match_id, team_member_id, record_state),
                CONSTRAINT player_match_stats_state_check CHECK (record_state IN ('draft', 'published')),
                CONSTRAINT player_match_stats_gender_check CHECK (player_gender IN ('Male', 'Female', 'Other')),
                CONSTRAINT player_match_stats_action_check CHECK (pending_action IS NULL OR pending_action IN ('replace', 'withdraw')),
                CONSTRAINT player_match_stats_publication_check CHECK (
                    (record_state = 'draft' AND pending_action IS NOT NULL AND published_at IS NULL AND published_by IS NULL)
                    OR (record_state = 'published' AND pending_action IS NULL AND published_at IS NOT NULL)
                ),
                CONSTRAINT player_match_stats_dots_check CHECK (
                    dot_balls IS NULL OR bowling_balls IS NULL OR dot_balls <= bowling_balls
                ),
                CONSTRAINT player_match_stats_boundaries_check CHECK (
                    runs_scored IS NULL OR fours IS NULL OR sixes IS NULL
                    OR CAST(fours AS BIGINT) * 4 + CAST(sixes AS BIGINT) * 6 <= runs_scored
                ),
                CONSTRAINT player_match_stats_match_fk FOREIGN KEY (match_id)
                    REFERENCES matches (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT player_match_stats_team_fk FOREIGN KEY (team_id)
                    REFERENCES teams (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT player_match_stats_member_fk FOREIGN KEY (team_member_id)
                    REFERENCES team_members (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT player_match_stats_updated_by_fk FOREIGN KEY (updated_by)
                    REFERENCES users (id) ON DELETE SET NULL ON UPDATE RESTRICT,
                CONSTRAINT player_match_stats_published_by_fk FOREIGN KEY (published_by)
                    REFERENCES users (id) ON DELETE SET NULL ON UPDATE RESTRICT
            )
            SQL);

        DB::statement('CREATE INDEX player_match_stats_player_state_index ON player_match_stats (team_member_id, record_state)');
        DB::statement('CREATE INDEX player_match_stats_team_index ON player_match_stats (team_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('player_match_stats');
    }
};
