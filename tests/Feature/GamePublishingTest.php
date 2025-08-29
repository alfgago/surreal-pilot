<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Game;
use App\Models\User;
use App\Models\Workspace;
use App\Services\GamePublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamePublishingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Workspace $workspace;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create();
        
        // Associate user with company
        $this->user->companies()->attach($this->company->id);
        $this->user->update(['current_company_id' => $this->company->id]);
        
        $this->workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
        ]);
        
        $this->game = Game::factory()->create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Test Game',
            'description' => 'A test game for publishing',
            'status' => 'draft',
            'build_status' => 'success',
        ]);
    }

    public function test_can_start_build_for_game(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson("/api/games/{$this->game->id}/build", [
            'minify' => true,
            'optimize_assets' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'build' => [
                    'id',
                    'version',
                    'status',
                    'started_at',
                ],
            ]);

        $this->assertDatabaseHas('game_builds', [
            'game_id' => $this->game->id,
            'status' => 'building',
        ]);
    }

    public function test_can_get_build_status(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson("/api/games/{$this->game->id}/build/status");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'build_status',
                'last_build_at',
                'latest_build',
            ]);
    }

    public function test_can_publish_game_with_successful_build(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson("/api/games/{$this->game->id}/publish", [
            'is_public' => true,
            'sharing_settings' => [
                'allow_embedding' => true,
                'show_controls' => true,
                'show_info' => true,
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'game' => [
                    'id',
                    'status',
                    'published_at',
                    'share_url',
                    'embed_url',
                ],
            ]);

        $this->game->refresh();
        $this->assertEquals('published', $this->game->status);
        $this->assertTrue($this->game->is_public);
        $this->assertNotNull($this->game->share_token);
    }

    public function test_cannot_publish_game_without_successful_build(): void
    {
        $this->game->update(['build_status' => 'failed']);
        $this->actingAs($this->user);

        $response = $this->postJson("/api/games/{$this->game->id}/publish", [
            'is_public' => true,
        ]);

        $response->assertStatus(500);
        
        $this->game->refresh();
        $this->assertEquals('draft', $this->game->status);
    }

    public function test_can_unpublish_game(): void
    {
        $this->game->update([
            'status' => 'published',
            'is_public' => true,
            'share_token' => 'test-token',
        ]);
        $this->actingAs($this->user);

        $response = $this->postJson("/api/games/{$this->game->id}/unpublish");

        $response->assertStatus(200);
        
        $this->game->refresh();
        $this->assertEquals('draft', $this->game->status);
        $this->assertFalse($this->game->is_public);
    }

    public function test_can_generate_share_token(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson("/api/games/{$this->game->id}/share-token");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'share_token',
                'share_url',
                'embed_url',
            ]);

        $this->game->refresh();
        $this->assertNotNull($this->game->share_token);
    }

    public function test_can_view_shared_game(): void
    {
        $this->game->update([
            'status' => 'published',
            'is_public' => true,
            'share_token' => 'test-share-token',
        ]);

        $response = $this->get("/games/shared/test-share-token");

        $response->assertStatus(200);
        
        // Check that play count was incremented
        $this->game->refresh();
        $this->assertEquals(1, $this->game->play_count);
    }

    public function test_cannot_view_unpublished_shared_game(): void
    {
        $this->game->update([
            'status' => 'draft',
            'share_token' => 'test-share-token',
        ]);

        $response = $this->get("/games/shared/test-share-token");

        $response->assertStatus(404);
    }

    public function test_can_view_embedded_game(): void
    {
        $this->game->update([
            'status' => 'published',
            'is_public' => true,
            'share_token' => 'test-embed-token',
            'sharing_settings' => ['allow_embedding' => true],
        ]);

        $response = $this->get("/games/embed/test-embed-token");

        $response->assertStatus(200);
    }

    public function test_cannot_embed_game_when_embedding_disabled(): void
    {
        $this->game->update([
            'status' => 'published',
            'is_public' => true,
            'share_token' => 'test-embed-token',
            'sharing_settings' => ['allow_embedding' => false],
        ]);

        $response = $this->get("/games/embed/test-embed-token");

        $response->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_access_game_publishing(): void
    {
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create();
        $otherUser->companies()->attach($otherCompany->id);
        $otherUser->update(['current_company_id' => $otherCompany->id]);
        
        $this->actingAs($otherUser);

        $response = $this->postJson("/api/games/{$this->game->id}/build");
        $response->assertStatus(403);

        $response = $this->postJson("/api/games/{$this->game->id}/publish");
        $response->assertStatus(403);
    }
}