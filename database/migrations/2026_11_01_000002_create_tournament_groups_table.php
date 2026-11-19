<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tournament_year');
            $table->string('name', 100);
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['tournament_year', 'name']);

            // Required by the same-year composite foreign key in group_teams.
            $table->unique(['id', 'tournament_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_groups');
    }
};
