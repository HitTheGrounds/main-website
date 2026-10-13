<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds nullable email and phone to team_members.
     * Only captain members will have these populated.
     */
    public function up(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->string('email')->nullable()->after('name');
            $table->string('phone')->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropColumn(['email', 'phone']);
        });
    }
};
