<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['company', 'admin', 'scorer'])->default('company')->after('company_id');
        });

        // Handle orphan accounts warning (is_admin = false and company_id is null)
        $orphanAccounts = DB::table('users')
            ->where('is_admin', false)
            ->whereNull('company_id')
            ->get();

        if ($orphanAccounts->count() > 0) {
            Log::warning('Found ' . $orphanAccounts->count() . ' orphan user accounts (no company and not admin). They will be assigned the "company" role but have no company_id.');
            foreach ($orphanAccounts as $user) {
                Log::warning("Orphan Account: User ID {$user->id}, Email: {$user->email}");
            }
        }

        // Migrate existing data
        DB::table('users')->where('is_admin', true)->update(['role' => 'admin']);
        DB::table('users')->where('is_admin', false)->update(['role' => 'company']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('company_id');
        });

        // Migrate back
        DB::table('users')->where('role', 'admin')->update(['is_admin' => true]);
        DB::table('users')->whereIn('role', ['company', 'scorer'])->update(['is_admin' => false]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
