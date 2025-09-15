<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\Game;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['current_company_id' => $this->company->id]);
    $this->workspace = Workspace::factory()->create([
        'company_id' => $this->company->id,
        'engine_type' => 'playcanvas'
    ]);
});

test('game preview endpoint returns valid preview url', function () {
    $game = Game::factory()->create([
        'workspace_id' => $this->workspace->id,
        'metadata' => ['engine_type' => 'playcanvas'],
        'status' => 'published'
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/games/{$game->id}/preview");

    $response->assertOk()
        ->assertJsonStructure([
            'preview_url',
            'game_id',
            'last_updated'
        ]);
});

test('game preview loads within two seconds', function () {
    $game = Game::factory()->create([
        'workspace_id' => $this->workspace->id,
        'metadata' => ['engine_type' => 'playcanvas'],
        'status' => 'published'
    ]);

    $startTime = microtime(true);
    
    $response = $this->actingAs($this->user)
        ->getJson("/api/games/{$game->id}/preview");

    $endTime = microtime(true);
    $loadTime = $endTime - $startTime;

    $response->assertOk();
    expect($loadTime)->toBeLessThan(2.0);
});

test('game preview includes performance metrics', function () {
    $game = Game::factory()->create([
        'workspace_id' => $this->workspace->id,
        'metadata' => ['engine_type' => 'playcanvas'],
        'status' => 'published'
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/games/{$game->id}/preview");

    $response->assertOk()
        ->assertJsonStructure([
            'preview_url',
            'performance' => [
                'load_time',
                'file_size',
                'asset_count'
            ]
        ]);
});