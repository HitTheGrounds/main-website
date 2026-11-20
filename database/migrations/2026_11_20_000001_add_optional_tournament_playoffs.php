<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('tournament_year')->primary();
            $table->boolean('playoffs_enabled')->default(false);
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();
        });

        $this->stages(['G', 'PO', 'QF', 'SF', 'F']);
    }

    public function down(): void
    {
        if (Schema::hasTable('matches')) {
            if (DB::table('matches')->where('stage', 'PO')->exists()) {
                throw new RuntimeException('Retain the Playoffs migration while Playoff match history exists.');
            }
            $this->stages(['G', 'QF', 'SF', 'F']);
        }
        Schema::dropIfExists('tournament_settings');
    }

    private function stages(array $stages): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE matches DROP CONSTRAINT IF EXISTS matches_stage_check');
            $values = implode(', ', array_map(fn ($stage) => "'".$stage."'", $stages));
            DB::statement('ALTER TABLE matches ADD CONSTRAINT matches_stage_check CHECK (stage IN ('.$values.'))');
        } else {
            // SQLite's table rebuild cannot round-trip expression indexes or
            // partial predicates through the schema builder. Recreate them verbatim.
            DB::statement('DROP INDEX matches_group_pair_unique');
            DB::statement('DROP INDEX matches_knockout_slot_unique');
            Schema::table('matches', fn (Blueprint $table) => $table->enum('stage', $stages)->change());
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX matches_group_pair_unique ON matches (
                    tournament_year, group_id,
                    (CASE WHEN team1_id < team2_id THEN team1_id ELSE team2_id END),
                    (CASE WHEN team1_id < team2_id THEN team2_id ELSE team1_id END)
                ) WHERE stage = 'G'
                SQL);
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX matches_knockout_slot_unique ON matches (
                    tournament_year, stage, bracket_position
                ) WHERE stage IN ('QF', 'SF', 'F')
                SQL);
        }
    }
};
