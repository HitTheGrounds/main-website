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
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->enum('stage', ['G', 'QF', 'SF', 'F']);
            $table->enum('status', ['upcoming', 'live', 'finished'])->default('upcoming');
            $table->foreignId('group_id')->nullable()->constrained('tournament_groups')->nullOnDelete();
            $table->tinyInteger('bracket_position')->nullable();
            
            $table->foreignId('team1_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('team2_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('batting_first_id')->nullable()->constrained('teams')->nullOnDelete();
            
            $table->integer('team1_score')->nullable();
            $table->tinyInteger('team1_overs')->nullable();
            $table->tinyInteger('team1_balls')->nullable();
            $table->tinyInteger('team1_wickets')->nullable();
            
            $table->integer('team2_score')->nullable();
            $table->tinyInteger('team2_overs')->nullable();
            $table->tinyInteger('team2_balls')->nullable();
            $table->tinyInteger('team2_wickets')->nullable();
            
            $table->boolean('is_draw')->default(false);
            $table->foreignId('winner_id')->nullable()->constrained('teams')->nullOnDelete();
            
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
