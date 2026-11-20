<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_tournament_stats', function (Blueprint $table) {
            // Twelfths represent both four- and six-ball overs exactly.
            $table->unsignedBigInteger('bowling_overs_units')->nullable();
            $table->boolean('strike_rate_complete')->default(false);
            $table->boolean('economy_complete')->default(false);
        });
        // Existing summaries remain unavailable for rate tie-breaks until the
        // normal publication path or tournament:rebuild-summaries recalculates.
    }

    public function down(): void
    {
        Schema::table('player_tournament_stats', function (Blueprint $table) {
            $table->dropColumn(['bowling_overs_units', 'strike_rate_complete', 'economy_complete']);
        });
    }
};
