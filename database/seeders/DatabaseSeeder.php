<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'is_admin' => true,
        ]);

        $company = Company::create([
            'name' => 'Devil plays Cricket',
            'phone' => '0766606660',
            'description' => 'Lucifer is also a god',
        ]);

        User::factory()->create([
            'name' => 'Devil plays Cricket',
            'email' => 'devil@hell.com',
            'company_id' => $company->id,
        ]);
    }
}
