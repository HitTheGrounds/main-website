<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_teams', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tournament_year');
            $table->foreignId('group_id');
            $table->foreignId('team_id')->constrained('teams')->restrictOnUpdate()->restrictOnDelete();

            $table->integer('played')->default(0);
            $table->integer('wins')->default(0);
            $table->integer('losses')->default(0);
            $table->integer('ties')->default(0);
            $table->integer('no_results')->default(0);
            $table->integer('base_points')->default(0);
            $table->jsonb('point_adjustments')->default('[]');
            // Official penalties may make the final points total negative.
            $table->integer('points')->default(0);
            $table->integer('runs_for')->default(0);
            $table->integer('runs_against')->default(0);
            // Decimal overs, never cricket notation such as "3.2".
            $table->decimal('nrr_overs_faced', 18, 8)->default(0);
            $table->decimal('nrr_overs_bowled', 18, 8)->default(0);
            $table->decimal('nrr', 18, 6)->nullable();
            $table->boolean('qualified')->default(false);
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['tournament_year', 'team_id']);
            $table->index(['group_id', 'tournament_year']);
            $table->index('team_id');

            $table->foreign(['group_id', 'tournament_year'])
                ->references(['id', 'tournament_year'])
                ->on('tournament_groups')
                ->restrictOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_teams');
    }
};
