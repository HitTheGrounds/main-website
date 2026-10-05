<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetUserRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:set-role {email} {role}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set the role (company, admin, scorer) for a user by email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $role = $this->argument('role');

        if (!in_array($role, ['company', 'admin', 'scorer'])) {
            $this->error("Invalid role '{$role}'. Must be one of: company, admin, scorer.");
            return 1;
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email '{$email}' not found.");
            return 1;
        }

        $user->role = $role;
        $user->save();

        $this->info("User '{$user->name}' ({$email}) is now assigned the role '{$role}'.");

        return 0;
    }
}
