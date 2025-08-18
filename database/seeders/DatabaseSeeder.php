<?php

namespace Database\Seeders;

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
        // Avoid creating sample users/companies during automated tests to keep datasets predictable
        if (!app()->environment('testing')) {
            User::factory()->withPersonalCompany()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        $this->call([
            SubscriptionPlanSeeder::class,
            DemoTemplateSeeder::class,
        ]);
    }
}
