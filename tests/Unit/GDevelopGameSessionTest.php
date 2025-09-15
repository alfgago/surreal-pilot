<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\GDevelopGameSession;
use App\Models\User;
use App\Models\Workspace;
use App\Services\GDevelopSessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GDevelopGameSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_gdevelop_game_session()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['current_company_id' => $company->id]);
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'engine_type' => 'gdevelop'
        ]);

        $session = GDevelopGameSession::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id
        ]);

        $this->assertInstanceOf(GDevelopGameSession::class, $session);
        $this->assertEquals($workspace->id, $session->workspace_id);
        $this->assertEquals($user->id, $session->user_id);
        $this->assertNotNull($session->session_id);
        $this->assertEquals('active', $session->status);
        $this->assertEquals(1, $session->version);
    }

    public function test_session_belongs_to_workspace_and_user()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['current_company_id' => $company->id]);
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'engine_type' => 'gdevelop'
        ]);

        $session = GDevelopGameSession::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id
        ]);

        $this->assertEquals($workspace->name, $session->workspace->name);
        $this->assertEquals($user->name, $session->user->name);
    }

    public function test_workspace_has_gdevelop_sessions_relationship()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['current_company_id' => $company->id]);
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'engine_type' => 'gdevelop'
        ]);

        $session = GDevelopGameSession::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id
        ]);

        $this->assertTrue($workspace->gdevelopGameSessions->contains($session));
        $this->assertTrue($workspace->isGDevelop());
    }

    public function test_user_has_gdevelop_sessions_relationship()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['current_company_id' => $company->id]);
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'engine_type' => 'gdevelop'
        ]);

        $session = GDevelopGameSession::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id
        ]);

        $this->assertTrue($user->gdevelopGameSessions->contains($session));
    }

    public function test_session_version_increments_when_game_json_changes()
    {
        $session = GDevelopGameSession::factory()->create();
        $originalVersion = $session->version;

        $session->updateGameJson(['test' => 'data']);

        $this->assertEquals($originalVersion + 1, $session->fresh()->version);
    }

    public function test_session_can_be_archived_and_restored()
    {
        $session = GDevelopGameSession::factory()->active()->create();

        $this->assertTrue($session->isActive());
        $this->assertFalse($session->isArchived());

        $session->archive();

        $this->assertFalse($session->isActive());
        $this->assertTrue($session->isArchived());

        $session->restore();

        $this->assertTrue($session->isActive());
        $this->assertFalse($session->isArchived());
    }

    public function test_session_manager_can_create_sessions()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['current_company_id' => $company->id]);
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'engine_type' => 'gdevelop'
        ]);

        $manager = new GDevelopSessionManager();
        $session = $manager->createSession($workspace->id, $user->id, 'Test Game');

        $this->assertInstanceOf(GDevelopGameSession::class, $session);
        $this->assertEquals('Test Game', $session->game_title);
        $this->assertEquals('active', $session->status);
    }

    public function test_session_manager_statistics()
    {
        GDevelopGameSession::factory()->active()->count(3)->create();
        GDevelopGameSession::factory()->archived()->count(2)->create();
        GDevelopGameSession::factory()->withError()->count(1)->create();

        $manager = new GDevelopSessionManager();
        $stats = $manager->getSessionStatistics();

        $this->assertEquals(6, $stats['total_sessions']);
        $this->assertEquals(3, $stats['active_sessions']);
        $this->assertEquals(2, $stats['archived_sessions']);
        $this->assertEquals(1, $stats['error_sessions']);
    }

    public function test_session_cleanup_functionality()
    {
        // Create sessions that should be archived
        GDevelopGameSession::factory()->oldEnoughToArchive(8)->count(2)->create();
        
        // Create sessions that should be cleaned up
        GDevelopGameSession::factory()->oldEnoughToCleanup(35)->count(3)->create();

        $manager = new GDevelopSessionManager();
        
        // Test archival
        $archivedCount = $manager->archiveInactiveSessions(7);
        $this->assertEquals(2, $archivedCount);
        
        // Test cleanup
        $cleanedCount = $manager->cleanupArchivedSessions(30);
        $this->assertEquals(3, $cleanedCount);
    }
}