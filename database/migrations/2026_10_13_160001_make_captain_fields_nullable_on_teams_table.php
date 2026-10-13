<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    /**
     * Run the migrations.
     * Makes captain_email and captain_phone nullable on teams.
     * These fields are now populated by member management logic,
     * not the team creation form.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('captain_email')->nullable()->change();
            $table->string('captain_phone')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('captain_email')->nullable(false)->change();
            $table->string('captain_phone')->nullable(false)->change();
        });
    }
};
