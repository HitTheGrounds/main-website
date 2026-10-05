<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('group_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('tournament_groups')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->integer('matches_played')->default(0);
            $table->integer('wins')->default(0);
            $table->integer('losses')->default(0);
            $table->integer('draws')->default(0);
            $table->integer('points')->default(0);
            $table->integer('total_balls_faced')->default(0);
            $table->integer('total_runs_scored')->default(0);
            $table->integer('total_balls_bowled')->default(0);
            $table->integer('total_runs_conceded')->default(0);
            $table->decimal('nrr', 8, 4)->default(0.0000);
            $table->boolean('qualified')->default(false);
            $table->timestamps();
            
            $table->unique(['group_id', 'team_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_teams');
    }
};
