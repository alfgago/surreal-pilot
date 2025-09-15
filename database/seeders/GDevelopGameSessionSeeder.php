<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GDevelopGameSessionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a company and user for testing
        $company = \App\Models\Company::factory()->create(['name' => 'GDevelop Test Company']);
        $user = \App\Models\User::factory()->create([
            'name' => 'GDevelop Test User',
            'email' => 'gdevelop@test.com',
            'current_company_id' => $company->id
        ]);

        // Create a GDevelop workspace
        $workspace = \App\Models\Workspace::factory()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'name' => 'GDevelop Test Workspace',
            'engine_type' => 'gdevelop',
            'status' => 'ready'
        ]);

        // Create some test sessions
        \App\Models\GDevelopGameSession::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'game_title' => 'Tower Defense Game',
            'status' => 'active'
        ]);

        \App\Models\GDevelopGameSession::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'game_title' => 'Platformer Game',
            'status' => 'active'
        ]);

        // Create an old session that should be archived
        \App\Models\GDevelopGameSession::factory()->oldEnoughToArchive(10)->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'game_title' => 'Old Game Session'
        ]);

        // Create an archived session that should be cleaned up
        \App\Models\GDevelopGameSession::factory()->oldEnoughToCleanup(40)->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'game_title' => 'Very Old Archived Session'
        ]);

        $this->command->info('Created GDevelop test sessions successfully!');
    }
}
