<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tournament_year');
            $table->unsignedInteger('display_number')->nullable();
            $table->enum('stage', ['G', 'QF', 'SF', 'F']);
            $table->foreignId('group_id')->nullable();
            $table->unsignedTinyInteger('bracket_position')->nullable();

            $table->foreignId('team1_id')->constrained('teams')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('team2_id')->constrained('teams')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('batting_first_id')->nullable()->constrained('teams')->restrictOnUpdate()->restrictOnDelete();

            // The scorer supplies the selected format; stage defaults live in config.
            $table->unsignedTinyInteger('balls_per_over');
            $table->unsignedTinyInteger('overs_per_innings')->default(5);
            $table->unsignedTinyInteger('max_wickets')->default(11);
            $table->enum('status', ['upcoming', 'live', 'finished'])->default('upcoming');
            $table->timestampTz('scheduled_at')->nullable();
            $table->string('venue')->nullable();

            // Official innings stay unknown until entered; zero is a real score.
            $table->unsignedInteger('team1_runs')->nullable();
            $table->unsignedTinyInteger('team1_wickets')->nullable();
            $table->unsignedInteger('team1_deliveries')->nullable();
            $table->unsignedInteger('team2_runs')->nullable();
            $table->unsignedTinyInteger('team2_wickets')->nullable();
            $table->unsignedInteger('team2_deliveries')->nullable();

            $table->enum('result_type', ['win', 'tie', 'no_result', 'abandoned'])->nullable();
            $table->foreignId('winner_id')->nullable()->constrained('teams')->restrictOnUpdate()->restrictOnDelete();
            $table->enum('result_method', ['normal', 'super_over', 'coin_toss', 'revised_target', 'official_decision'])->nullable();
            $table->text('result_note')->nullable();
            $table->unsignedInteger('revised_target')->nullable();

            // Backend validation must allowlist fields and bound document sizes.
            $table->jsonb('nrr_inputs')->nullable();
            $table->jsonb('draft_result')->nullable();

            $table->timestampTz('result_published_at')->nullable();
            $table->timestampTz('structure_locked_at')->nullable();
            $table->timestampTz('player_stats_published_at')->nullable();
            $table->unsignedBigInteger('player_stats_revision')->default(0);
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnUpdate()->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnUpdate()->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnUpdate()->nullOnDelete();
            $table->timestamps();

            $table->index(['tournament_year', 'status', 'stage']);
            $table->index(['group_id', 'tournament_year']);
            $table->index('team1_id');
            $table->index('team2_id');

            $table->foreign(['group_id', 'tournament_year'])
                ->references(['id', 'tournament_year'])
                ->on('tournament_groups')
                ->restrictOnUpdate()
                ->restrictOnDelete();
        });

        // CASE expressions and partial unique indexes work in PostgreSQL and SQLite.
        // Normalize only the index key; preserve the scorer's Team 1/Team 2 order.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX matches_group_pair_unique ON matches (
                tournament_year,
                group_id,
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

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
