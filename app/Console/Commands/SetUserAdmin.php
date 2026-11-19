<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetUserAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:set-admin {email} {--revoke : Revoke admin privileges}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set or revoke admin privileges for a user by email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $revoke = $this->option('revoke');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email '{$email}' not found.");

            return 1;
        }

        $isAdmin = ! $revoke;
        if ($isAdmin && $user->is_scorer) {
            $this->error('Cannot promote scorer accounts to admin. Use a separate admin account.');

            return 1;
        }

        $user->is_admin = $isAdmin;
        $user->save();

        if ($isAdmin) {
            $this->info("User '{$user->name}' ({$email}) is now an admin.");
        } else {
            $this->info("Admin privileges revoked for user '{$user->name}' ({$email}).");
        }

        return 0;
    }
}
