<?php

use App\Models\Game;
use App\Models\Workspace;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['current_company_id' => $this->company->id]);
    $this->workspace = Workspace::factory()->create([
        'company_id' => $this->company->id,
        'engine_type' => 'playcanvas'
    ]);
});

test('game has preview url property', function () {
    $game = Game::factory()->create([
        'workspace_id' => $this->workspace->id,
        'preview_url' => 'https://example.com/game-preview',
        'metadata' => ['engine_type' => 'playcanvas']
    ]);

    expect($game->preview_url)->not->toBeNull();
    expect($game->preview_url)->toBe('https://example.com/game-preview');
});

test('game has preview method', function () {
    $game = Game::factory()->create([
        'workspace_id' => $this->workspace->id,
        'preview_url' => 'https://example.com/game-preview'
    ]);

    expect($game->hasPreview())->toBeTrue();

    $gameWithoutPreview = Game::factory()->create([
        'workspace_id' => $this->workspace->id,
        'preview_url' => null
    ]);

    expect($gameWithoutPreview->hasPreview())->toBeFalse();
});

test('game interaction count increments', function () {
    $game = Game::factory()->create([
        'workspace_id' => $this->workspace->id,
        'metadata' => ['interaction_count' => 0]
    ]);

    expect($game->getInteractionCount())->toBe(0);

    $game->incrementInteractionCount();
    expect($game->fresh()->getInteractionCount())->toBe(1);

    $game->incrementInteractionCount();
    expect($game->fresh()->getInteractionCount())->toBe(2);
});

test('game thinking process can be added', function () {
    $game = Game::factory()->create([
        'workspace_id' => $this->workspace->id,
        'metadata' => ['thinking_history' => []]
    ]);

    $thinkingProcess = [
        'step' => 'analysis',
        'reasoning' => 'Analyzing user request for tower defense game',
        'decisions' => ['Add tower placement', 'Implement enemy waves'],
        'implementation' => 'Creating tower defense mechanics'
    ];

    $game->addThinkingProcess($thinkingProcess);

    $history = $game->fresh()->metadata['thinking_history'];
    expect($history)->toHaveCount(1);
    expect($history[0]['step'])->toBe('analysis');
    expect($history[0]['reasoning'])->toBe('Analyzing user request for tower defense game');
    expect($history[0])->toHaveKey('timestamp');
    expect($history[0])->toHaveKey('interaction');
});

test('game can get latest thinking process', function () {
    $game = Game::factory()->create([
        'workspace_id' => $this->workspace->id,
        'metadata' => ['thinking_history' => []]
    ]);

    expect($game->getLatestThinking())->toBeNull();

    $firstThinking = [
        'step' => 'analysis',
        'reasoning' => 'First analysis'
    ];

    $secondThinking = [
        'step' => 'implementation',
        'reasoning' => 'Second implementation'
    ];

    $game->addThinkingProcess($firstThinking);
    $game->addThinkingProcess($secondThinking);

    $latest = $game->getLatestThinking();
    expect($latest['step'])->toBe('implementation');
    expect($latest['reasoning'])->toBe('Second implementation');
});

test('game mechanics can be updated', function () {
    $game = Game::factory()->create([
        'workspace_id' => $this->workspace->id,
        'metadata' => ['game_mechanics' => []]
    ]);

    $mechanics = [
        'towers' => ['basic', 'advanced'],
        'enemies' => ['grunt', 'boss'],
        'currency' => 100
    ];

    $game->updateGameMechanics($mechanics);

    expect($game->getGameMechanic('towers'))->toBe(['basic', 'advanced']);
    expect($game->getGameMechanic('enemies'))->toBe(['grunt', 'boss']);
    expect($game->getGameMechanic('currency'))->toBe(100);
});

test('game mechanics merge with existing', function () {
    $game = Game::factory()->create([
        'workspace_id' => $this->workspace->id,
        'metadata' => [
            'game_mechanics' => [
                'towers' => ['basic'],
                'currency' => 50
            ]
        ]
    ]);

    $newMechanics = [
        'towers' => ['basic', 'advanced'],
        'enemies' => ['grunt']
    ];

    $game->updateGameMechanics($newMechanics);

    expect($game->getGameMechanic('towers'))->toBe(['basic', 'advanced']);
    expect($game->getGameMechanic('enemies'))->toBe(['grunt']);
});

test('game build status methods', function () {
    $game = Game::factory()->create([
        'workspace_id' => $this->workspace->id,
        'build_status' => 'building'
    ]);

    expect($game->isBuilding())->toBeTrue();
    expect($game->hasSuccessfulBuild())->toBeFalse();
    expect($game->hasBuildFailed())->toBeFalse();

    $game->update(['build_status' => 'success']);
    expect($game->isBuilding())->toBeFalse();
    expect($game->hasSuccessfulBuild())->toBeTrue();
    expect($game->hasBuildFailed())->toBeFalse();

    $game->update(['build_status' => 'failed']);
    expect($game->isBuilding())->toBeFalse();
    expect($game->hasSuccessfulBuild())->toBeFalse();
    expect($game->hasBuildFailed())->toBeTrue();
});

test('game display url returns published or preview', function () {
    $game = Game::factory()->create([
        'workspace_id' => $this->workspace->id,
        'preview_url' => 'https://example.com/preview',
        'published_url' => null
    ]);

    expect($game->getDisplayUrl())->toBe('https://example.com/preview');

    $game->update(['published_url' => 'https://example.com/published']);
    expect($game->getDisplayUrl())->toBe('https://example.com/published');
});

test('game share token generation', function () {
    $game = Game::factory()->create([
        'workspace_id' => $this->workspace->id,
        'share_token' => null
    ]);

    expect($game->getShareUrl())->toBeNull();

    $token = $game->generateShareToken();
    expect($token)->not->toBeNull();
    expect(strlen($token))->toBe(32);
    expect($game->fresh()->share_token)->not->toBeNull();
    expect($game->getShareUrl())->toContain($token);
});

test('game embed url generation', function () {
    $game = Game::factory()->create([
        'workspace_id' => $this->workspace->id,
        'share_token' => 'test-token-123'
    ]);

    $embedUrl = $game->getEmbedUrl();
    expect($embedUrl)->toContain('/games/embed/test-token-123');
});

test('game play count increments', function () {
    $game = Game::factory()->create([
        'workspace_id' => $this->workspace->id,
        'play_count' => 0,
        'last_played_at' => null
    ]);

    expect($game->play_count)->toBe(0);
    expect($game->last_played_at)->toBeNull();

    $game->incrementPlayCount();
    $game = $game->fresh();

    expect($game->play_count)->toBe(1);
    expect($game->last_played_at)->not->toBeNull();
});

test('game engine type detection', function () {
    $game = Game::factory()->create([
        'workspace_id' => $this->workspace->id,
        'metadata' => ['engine_type' => 'playcanvas']
    ]);

    expect($game->getEngineType())->toBe('playcanvas');

    $gameWithoutEngine = Game::factory()->create([
        'workspace_id' => $this->workspace->id,
        'metadata' => []
    ]);

    // Should fall back to workspace engine type
    expect($gameWithoutEngine->getEngineType())->toBe('playcanvas');
});