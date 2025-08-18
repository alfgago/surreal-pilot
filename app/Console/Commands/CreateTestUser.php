<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateTestUser extends Command
{
    protected $signature = 'create:test-user';
    protected $description = 'Create a test user with company and API token';

    public function handle()
    {
        // Delete existing test user if exists
        $existingUser = User::where('email', 'test@example.com')->first();
        if ($existingUser) {
            $existingUser->companies()->detach();
            $existingUser->delete();
        }

        // Create user
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now()
        ]);

        // Create company
        $company = Company::create([
            'name' => 'Test Company',
            'user_id' => $user->id,
            'personal_company' => true,
            'credits' => 1000,
            'plan' => 'pro'
        ]);

        // Attach user to company
        $user->companies()->attach($company, ['role' => 'owner']);

        // Set as current company
        $user->update(['current_company_id' => $company->id]);

        // Create API token
        $token = $user->createToken('test-token')->plainTextToken;

        $this->info('Test user created successfully!');
        $this->info('Email: test@example.com');
        $this->info('Password: password123');
        $this->info('API Token: ' . $token);
        $this->info('Company Credits: 1000');

        return 0;
    }
}
