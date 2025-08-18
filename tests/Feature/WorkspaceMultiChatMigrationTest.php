<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Company;
use App\Models\Game;
use App\Models\Patch;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class WorkspaceMultiChatMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user and company
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_migration_creates_default_conversations_for_existing_workspaces()
    {
        // Create test workspaces without conversations
        $workspace1 = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Test Workspace 1',
            'engine_type' => 'playcanvas'
        ]);
        
        $workspace2 = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Test Workspace 2',
            'engine_type' => 'unreal'
        ]);

        // Verify no conversations exist initially
        $this->assertEquals(0, ChatConversation::count());

        // Run migration
        Artisan::call('migrate:workspaces-to-multichat');

        // Verify conversations were created
        $this->assertEquals(2, ChatConversation::count());
        
        // Verify each workspace has a default conversation
        $this->assertTrue($workspace1->conversations()->exists());
        $this->assertTrue($workspace2->conversations()->exists());
        
        $conversation1 = $workspace1->conversations()->first();
        $conversation2 = $workspace2->conversations()->first();
        
        $this->assertEquals('Default Chat', $conversation1->title);
        $this->assertEquals('Default Chat', $conversation2->title);
        $this->assertStringContainsString('migration', $conversation1->description);
        $this->assertStringContainsString('migration', $conversation2->description);
    }

    public function test_migration_preserves_existing_workspace_data()
    {
        // Create workspace with metadata
        $originalMetadata = ['test' => 'data', 'preserved' => true];
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Test Workspace',
            'engine_type' => 'playcanvas',
            'metadata' => $originalMetadata,
            'preview_url' => 'https://example.com/preview',
            'published_url' => 'https://example.com/published'
        ]);

        // Run migration
        Artisan::call('migrate:workspaces-to-multichat');

        // Verify workspace data is preserved
        $workspace->refresh();
        $this->assertEquals('Test Workspace', $workspace->name);
        $this->assertEquals('playcanvas', $workspace->engine_type);
        $this->assertEquals($originalMetadata, $workspace->metadata);
        $this->assertEquals('https://example.com/preview', $workspace->preview_url);
        $this->assertEquals('https://example.com/published', $workspace->published_url);
    }

    public function test_migration_migrates_chat_history_from_patches()
    {
        // Create workspace
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas'
        ]);

        // Create patches with chat history
        $envelope1 = [
            'messages' => [
                ['role' => 'user', 'content' => 'Create a simple game'],
                ['role' => 'assistant', 'content' => 'I\'ll create a simple game for you.']
            ]
        ];
        
        $envelope2 = [
            'messages' => [
                ['role' => 'user', 'content' => 'Add a player character'],
                ['role' => 'assistant', 'content' => 'Adding a player character now.']
            ]
        ];

        $patch1 = Patch::create([
            'workspace_id' => $workspace->id,
            'patch_id' => 'patch_1',
            'envelope_json' => json_encode($envelope1),
            'tokens_used' => 100,
            'success' => true,
            'created_at' => now()->subHours(2)
        ]);

        $patch2 = Patch::create([
            'workspace_id' => $workspace->id,
            'patch_id' => 'patch_2',
            'envelope_json' => json_encode($envelope2),
            'tokens_used' => 150,
            'success' => true,
            'created_at' => now()->subHour()
        ]);

        // Run migration
        Artisan::call('migrate:workspaces-to-multichat');

        // Verify conversation was created
        $conversation = $workspace->conversations()->first();
        $this->assertNotNull($conversation);

        // Verify messages were migrated
        $messages = $conversation->messages()->orderBy('created_at')->get();
        $this->assertEquals(4, $messages->count());

        // Verify first patch messages
        $this->assertEquals('user', $messages[0]->role);
        $this->assertEquals('Create a simple game', $messages[0]->content);
        $this->assertTrue($messages[0]->metadata['migrated_from_patch']);
        $this->assertEquals('patch_1', $messages[0]->metadata['patch_id']);
        $this->assertEquals(100, $messages[0]->metadata['tokens_used']);

        $this->assertEquals('assistant', $messages[1]->role);
        $this->assertEquals('I\'ll create a simple game for you.', $messages[1]->content);

        // Verify second patch messages
        $this->assertEquals('user', $messages[2]->role);
        $this->assertEquals('Add a player character', $messages[2]->content);
        $this->assertEquals('patch_2', $messages[2]->metadata['patch_id']);

        $this->assertEquals('assistant', $messages[3]->role);
        $this->assertEquals('Adding a player character now.', $messages[3]->content);
    }

    public function test_migration_updates_existing_games_with_conversation_reference()
    {
        // Create workspace
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas'
        ]);

        // Create games without conversation reference
        $game1 = Game::create([
            'workspace_id' => $workspace->id,
            'title' => 'Test Game 1',
            'description' => 'A test game',
            'conversation_id' => null
        ]);

        $game2 = Game::create([
            'workspace_id' => $workspace->id,
            'title' => 'Test Game 2',
            'description' => 'Another test game',
            'conversation_id' => null
        ]);

        // Run migration
        Artisan::call('migrate:workspaces-to-multichat');

        // Verify games were updated with conversation reference
        $conversation = $workspace->conversations()->first();
        
        $game1->refresh();
        $game2->refresh();
        
        $this->assertEquals($conversation->id, $game1->conversation_id);
        $this->assertEquals($conversation->id, $game2->conversation_id);
    }

    public function test_migration_validates_data_integrity()
    {
        // Create workspace
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas'
        ]);

        // Run migration
        $exitCode = Artisan::call('migrate:workspaces-to-multichat');

        // Verify migration succeeded
        $this->assertEquals(0, $exitCode);

        // Verify data integrity
        $this->assertTrue($workspace->conversations()->exists());
        $conversation = $workspace->conversations()->first();
        $this->assertNotNull($conversation->workspace);
    }

    public function test_migration_dry_run_mode()
    {
        // Create workspace
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas'
        ]);

        // Run migration in dry-run mode
        $exitCode = Artisan::call('migrate:workspaces-to-multichat', ['--dry-run' => true]);

        // Verify migration succeeded but no changes were made
        $this->assertEquals(0, $exitCode);
        $this->assertEquals(0, ChatConversation::count());
        $this->assertFalse($workspace->conversations()->exists());
    }

    public function test_migration_skips_workspaces_with_existing_conversations()
    {
        // Create workspace with existing conversation
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas'
        ]);

        $existingConversation = ChatConversation::create([
            'workspace_id' => $workspace->id,
            'title' => 'Existing Chat',
            'description' => 'Already exists'
        ]);

        // Run migration
        $exitCode = Artisan::call('migrate:workspaces-to-multichat');

        // Verify migration succeeded and didn't create duplicate conversations
        $this->assertEquals(0, $exitCode);
        $this->assertEquals(1, $workspace->conversations()->count());
        $this->assertEquals('Existing Chat', $workspace->conversations()->first()->title);
    }

    public function test_migration_force_flag_processes_workspaces_with_existing_conversations()
    {
        // Create workspace with existing conversation
        $workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas'
        ]);

        $existingConversation = ChatConversation::create([
            'workspace_id' => $workspace->id,
            'title' => 'Existing Chat',
            'description' => 'Already exists'
        ]);

        // Run migration with force flag
        $exitCode = Artisan::call('migrate:workspaces-to-multichat', ['--force' => true]);

        // Verify migration succeeded and created additional conversation
        $this->assertEquals(0, $exitCode);
        $this->assertEquals(2, $workspace->conversations()->count());
        
        $conversations = $workspace->conversations()->orderBy('created_at')->get();
        $this->assertEquals('Existing Chat', $conversations[0]->title);
        $this->assertEquals('Default Chat', $conversations[1]->title);
    }

    public function test_migration_handles_empty_database()
    {
        // Ensure no workspaces exist
        $this->assertEquals(0, Workspace::count());

        // Run migration
        $exitCode = Artisan::call('migrate:workspaces-to-multichat');

        // Verify migration succeeded with no workspaces to process
        $this->assertEquals(0, $exitCode);
        $this->assertEquals(0, ChatConversation::count());
    }
}