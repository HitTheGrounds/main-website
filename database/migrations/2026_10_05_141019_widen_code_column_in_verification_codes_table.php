<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Widen the code column from varchar(6) to varchar(255) to store bcrypt hashes
     * instead of plain-text OTP codes.
     */
    public function up(): void
    {
        Schema::table('verification_codes', function (Blueprint $table) {
            $table->string('code', 255)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('verification_codes', function (Blueprint $table) {
            $table->string('code', 6)->change();
        });
    }
};
