<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Do not rewrite or delete historical rows. Existing contradictions
            // can be corrected or withdrawn; all new/replacement figures obey this rule.
            DB::statement('ALTER TABLE player_match_stats ADD CONSTRAINT player_match_stats_known_figures_check CHECK ('.$this->condition().') NOT VALID');
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT' => 'insert', 'UPDATE' => 'update'] as $event => $suffix) {
                DB::unprepared('CREATE TRIGGER player_match_stats_known_figures_'.$suffix.' BEFORE '.$event.' ON player_match_stats
                    FOR EACH ROW WHEN NOT ('.$this->condition('NEW.').')
                    BEGIN SELECT RAISE(ABORT, \'Known player figures are inconsistent.\'); END');
            }
        } else {
            throw new RuntimeException('Player statistics require PostgreSQL or SQLite.');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE player_match_stats DROP CONSTRAINT IF EXISTS player_match_stats_known_figures_check');
        } else {
            DB::statement('DROP TRIGGER IF EXISTS player_match_stats_known_figures_insert');
            DB::statement('DROP TRIGGER IF EXISTS player_match_stats_known_figures_update');
        }
    }

    private function condition(string $prefix = ''): string
    {
        $col = fn (string $name) => $prefix.$name;
        $knownBoundaries = 'CAST(COALESCE('.$col('fours').', 0) AS BIGINT) * 4 + CAST(COALESCE('.$col('sixes').', 0) AS BIGINT) * 6';
        $noBatting = implode(' AND ', array_map(fn ($field) => 'COALESCE('.$col($field).', 0) = 0', ['runs_scored', 'balls_faced', 'fours', 'sixes']));

        return '('.$col('record_state')." = 'draft' AND ".$col('pending_action')." = 'withdraw') OR (("
            .$col('runs_scored').' IS NULL OR '.$knownBoundaries.' <= '.$col('runs_scored').') AND ('
            .$col('batting_status').' IS NULL OR '.$col('batting_status')." <> 'did_not_bat' OR (".$noBatting.')))';
    }
};
