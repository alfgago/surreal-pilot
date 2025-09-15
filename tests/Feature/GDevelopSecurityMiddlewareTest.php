<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\GDevelopGameSession;
use Illuminate\Support\Facades\RateLimiter;

describe('GDevelopSecurityMiddleware', function () {
    beforeEach(function () {
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine' => 'gdevelop'
        ]);
        $this->session = GDevelopGameSession::factory()->create([
            'workspace_id' => $this->workspace->id
        ]);
    });

    describe('rate limiting', function () {
        it('allows requests within rate limit', function () {
            $this->actingAs($this->user);

            $response = $this->postJson('/api/gdevelop/chat', [
                'message' => 'Create a simple game',
                'workspace_id' => $this->workspace->id
            ]);

            expect($response->status())->not->toBe(429);
        });

        it('blocks requests exceeding rate limit', function () {
            $this->actingAs($this->user);

            // Clear any existing rate limit state
            RateLimiter::clear('gdevelop-requests:' . $this->user->id);

            // Make 61 requests to exceed the 60 per minute limit
            for ($i = 0; $i < 61; $i++) {
                RateLimiter::hit('gdevelop-requests:' . $this->user->id, 60);
            }

            $response = $this->postJson('/api/gdevelop/chat', [
                'message' => 'Create a simple game',
                'workspace_id' => $this->workspace->id
            ]);

            expect($response->status())->toBe(429)
                ->and($response->json('error'))->toContain('Too many requests');
        });
    });

    describe('session ownership validation', function () {
        it('allows access to owned sessions', function () {
            $this->actingAs($this->user);

            $response = $this->getJson("/api/gdevelop/preview/{$this->session->session_id}");

            expect($response->status())->not->toBe(403);
        });

        it('blocks access to sessions from other companies', function () {
            // Create another company and user
            $otherCompany = Company::factory()->create();
            $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
            
            $this->actingAs($otherUser);

            $response = $this->getJson("/api/gdevelop/preview/{$this->session->session_id}");

            expect($response->status())->toBe(403)
                ->and($response->json('error'))->toContain('Unauthorized access');
        });

        it('blocks unauthenticated access to sessions', function () {
            $response = $this->getJson("/api/gdevelop/preview/{$this->session->session_id}");

            expect($response->status())->toBe(403);
        });
    });

    describe('request size validation', function () {
        it('allows normal sized requests', function () {
            $this->actingAs($this->user);

            $response = $this->postJson('/api/gdevelop/chat', [
                'message' => 'Create a simple platformer game with a player character',
                'workspace_id' => $this->workspace->id
            ]);

            expect($response->status())->not->toBe(413);
        });

        it('blocks oversized requests', function () {
            $this->actingAs($this->user);

            // Create a large message (over 10MB)
            $largeMessage = str_repeat('x', 11 * 1024 * 1024);

            $response = $this->postJson('/api/gdevelop/chat', [
                'message' => $largeMessage,
                'workspace_id' => $this->workspace->id
            ]);

            expect($response->status())->toBe(413)
                ->and($response->json('error'))->toContain('Request size too large');
        });
    });

    describe('message content validation', function () {
        it('allows safe message content', function () {
            $this->actingAs($this->user);

            $response = $this->postJson('/api/gdevelop/chat', [
                'message' => 'Create a tower defense game with enemies and towers',
                'workspace_id' => $this->workspace->id
            ]);

            expect($response->status())->not->toBe(400);
        });

        it('blocks messages with script tags', function () {
            $this->actingAs($this->user);

            $response = $this->postJson('/api/gdevelop/chat', [
                'message' => 'Create a game <script>alert("xss")</script>',
                'workspace_id' => $this->workspace->id
            ]);

            expect($response->status())->toBe(400)
                ->and($response->json('error'))->toContain('Invalid message content');
        });

        it('blocks messages with javascript protocol', function () {
            $this->actingAs($this->user);

            $response = $this->postJson('/api/gdevelop/chat', [
                'message' => 'Create a game javascript:alert("xss")',
                'workspace_id' => $this->workspace->id
            ]);

            expect($response->status())->toBe(400)
                ->and($response->json('error'))->toContain('Invalid message content');
        });

        it('blocks messages with dangerous PHP functions', function () {
            $this->actingAs($this->user);

            $response = $this->postJson('/api/gdevelop/chat', [
                'message' => 'Create a game eval("malicious code")',
                'workspace_id' => $this->workspace->id
            ]);

            expect($response->status())->toBe(400)
                ->and($response->json('error'))->toContain('Invalid message content');
        });

        it('blocks overly long messages', function () {
            $this->actingAs($this->user);

            $longMessage = str_repeat('Create a game ', 1000); // Over 5000 characters

            $response = $this->postJson('/api/gdevelop/chat', [
                'message' => $longMessage,
                'workspace_id' => $this->workspace->id
            ]);

            expect($response->status())->toBe(400)
                ->and($response->json('error'))->toContain('Invalid message content');
        });
    });

    describe('export endpoint security', function () {
        it('validates session ownership for export', function () {
            $this->actingAs($this->user);

            $response = $this->postJson("/api/gdevelop/export/{$this->session->session_id}", [
                'format' => 'html5',
                'mobile_optimized' => true
            ]);

            expect($response->status())->not->toBe(403);
        });

        it('blocks export for unauthorized sessions', function () {
            $otherCompany = Company::factory()->create();
            $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
            
            $this->actingAs($otherUser);

            $response = $this->postJson("/api/gdevelop/export/{$this->session->session_id}", [
                'format' => 'html5'
            ]);

            expect($response->status())->toBe(403);
        });
    });

    describe('middleware bypass attempts', function () {
        it('cannot bypass middleware with malformed session IDs', function () {
            $this->actingAs($this->user);

            $malformedSessionIds = [
                '../../../etc/passwd',
                '..\\..\\..\\windows\\system32',
                'session; rm -rf /',
                'session`whoami`',
                'session|cat /etc/passwd'
            ];

            foreach ($malformedSessionIds as $sessionId) {
                $response = $this->getJson("/api/gdevelop/preview/{$sessionId}");
                
                expect($response->status())->toBe(403);
            }
        });

        it('logs security violations', function () {
            $this->actingAs($this->user);

            // Clear rate limiter and exceed limit
            RateLimiter::clear('gdevelop-requests:' . $this->user->id);
            for ($i = 0; $i < 61; $i++) {
                RateLimiter::hit('gdevelop-requests:' . $this->user->id, 60);
            }

            $response = $this->postJson('/api/gdevelop/chat', [
                'message' => 'Test message',
                'workspace_id' => $this->workspace->id
            ]);

            expect($response->status())->toBe(429);
            
            // Check that the violation was logged
            $this->assertDatabaseHas('logs', [
                'level' => 'warning',
                'message' => 'GDevelop rate limit exceeded'
            ]);
        });
    });
});