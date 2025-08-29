<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find or create test user
        $user = User::firstOrCreate(
            ['email' => 'alfredo@5e.cr'],
            [
                'name' => 'Alfredo Test',
                'password' => Hash::make('Test123!'),
                'email_verified_at' => now(),
            ]
        );

        // Find or create test company
        $company = Company::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Test Company'],
            [
                'personal_company' => false,
                'credits' => 1000.00,
                'plan' => 'starter',
                'stripe_id' => 'cus_test_123',
            ]
        );

        // Update company plan if needed
        $company->update(['plan' => 'starter']);

        // Set current company for user
        $user->update(['current_company_id' => $company->id]);

        // Attach user to company if not already attached
        if (!$company->users()->where('user_id', $user->id)->exists()) {
            $company->users()->attach($user->id, ['role' => 'owner']);
        }

        $this->command->info('Test user created/updated: alfredo@5e.cr / Test123!');
    }
}