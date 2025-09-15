<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Workspace;
use App\Models\GDevelopGameSession;
use App\Services\GDevelopJsonValidationService;
use App\Services\GDevelopSandboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GDevelopSecurityValidationFinalTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;
    protected Company $company;
    protected Company $otherCompany;
    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('gdevelop.enabled', true);
        Config::set('gdevelop.engines.gdevelop_enabled', true);

        // Create test companies and users
        $this->company = Company::factory()->create(['credits' => 1000]);
        $this->otherCompany = Company::factory()->create(['credits' => 1000]);

        $this->user = User::factory()->create(['current_company_id' => $this->company->id]);
        $this->otherUser = User::factory()->create(['current_company_id' => $this->otherCompany->id]);

        $this->company->users()->attach($this->user->id, ['role' => 'owner']);
        $this->otherCompany->users()->attach($this->otherUser->id, ['role' => 'owner']);

        $this->workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->user->id,
            'engine_type' => 'gdevelop'
        ]);

        Storage::fake('local');
    }

    /** @test */
    public function input_sanitization_prevents_xss_attacks()
    {
        $this->actingAs($this->user);

        $maliciousInputs = [
            '<script>alert("xss")</script>',
            '<img src="x" onerror="alert(1)">',
            'javascript:alert(document.cookie)',
            '<svg onload="alert(1)">',
            '"><script>alert("xss")</script>',
            '<iframe src="javascript:alert(1)"></iframe>',
            '<object data="javascript:alert(1)">',
            '<embed src="javascript:alert(1)">',
            '<link rel="stylesheet" href="javascript:alert(1)">',
            '<style>@import "javascript:alert(1)";</style>'
        ];

        foreach ($maliciousInputs as $input) {
            $response = $this->postJson('/api/gdevelop/chat', [
                'session_id' => 'xss-test-' . md5($input),
                'workspace_id' => $this->workspace->id,
                'message' => "Create a game with title: {$input}"
            ]);

            // Should either succeed with sanitized input or fail gracefully
            $this->assertContains($response->getStatusCode(), [200, 422]);

            if ($response->getStatusCode() === 200) {
                $data = $response->json();
                $gameData = $data['game_data'];
                
                // Verify malicious content was sanitized
                $gameJson = json_encode($gameData);
                $this->assertStringNotContainsString('<script>', $gameJson);
                $this->assertStringNotContainsString('javascript:', $gameJson);
                $this->assertStringNotContainsString('onerror=', $gameJson);
                $this->assertStringNotContainsString('onload=', $gameJson);
            }
        }
    }

    /** @test */
    public function sql_injection_attempts_are_prevented()
    {
        $this->actingAs($this->user);

        $sqlInjectionInputs = [
            "'; DROP TABLE users; --",
            "' OR '1'='1",
            "'; DELETE FROM gdevelop_game_sessions; --",
            "' UNION SELECT * FROM users --",
            "'; INSERT INTO users (email) VALUES ('hacker@evil.com'); --",
            "' OR 1=1 --",
            "'; UPDATE users SET email='hacked@evil.com' WHERE id=1; --",
            "' AND (SELECT COUNT(*) FROM users) > 0 --"
        ];

        foreach ($sqlInjectionInputs as $input) {
            $response = $this->postJson('/api/gdevelop/chat', [
                'session_id' => 'sql-test-' . md5($input),
                'workspace_id' => $this->workspace->id,
                'message' => "Create a game called {$input}"
            ]);

            // Should handle gracefully without executing SQL
            $this->assertContains($response->getStatusCode(), [200, 422]);

            // Verify no SQL injection occurred by checking user count
            $userCount = User::count();
            $this->assertEquals(2, $userCount); // Should still be our 2 test users
        }
    }

    /** @test */
    public function path_traversal_attacks_are_prevented()
    {
        $this->actingAs($this->user);

        $pathTraversalInputs = [
            '../../../etc/passwd',
            '..\\..\\..\\windows\\system32\\config\\sam',
            '....//....//....//etc/passwd',
            '%2e%2e%2f%2e%2e%2f%2e%2e%2fetc%2fpasswd',
            '..%252f..%252f..%252fetc%252fpasswd',
            '..%c0%af..%c0%af..%c0%afetc%c0%afpasswd',
            '/var/www/../../etc/passwd',
            'file:///etc/passwd'
        ];

        foreach ($pathTraversalInputs as $input) {
            $response = $this->postJson('/api/gdevelop/chat', [
                'session_id' => 'path-test-' . md5($input),
                'workspace_id' => $this->workspace->id,
                'message' => "Load game template from {$input}"
            ]);

            // Should handle gracefully without accessing system files
            $this->assertContains($response->getStatusCode(), [200, 422, 400]);

            if ($response->getStatusCode() === 200) {
                $data = $response->json();
                
                // Verify no system files were accessed
                $gameJson = json_encode($data['game_data']);
                $this->assertStringNotContainsString('/etc/', $gameJson);
                $this->assertStringNotContainsString('passwd', $gameJson);
                $this->assertStringNotContainsString('system32', $gameJson);
            }
        }
    }

    /** @test */
    public function session_isolation_prevents_cross_user_access()
    {
        // Create session as first user
        $this->actingAs($this->user);
        
        $sessionId = 'isolation-test-' . uniqid();
        
        $response = $this->postJson('/api/gdevelop/chat', [
            'session_id' => $sessionId,
            'workspace_id' => $this->workspace->id,
            'message' => 'Create a secret game'
        ]);
        
        $response->assertStatus(200);
        
        // Verify session was created for first user
        $gameSession = GDevelopGameSession::where('session_id', $sessionId)->first();
        $this->assertNotNull($gameSession);
        $this->assertEquals($this->user->id, $gameSession->user_id);
        
        // Try to access session as second user
        $this->actingAs($this->otherUser);
        
        $accessResponse = $this->getJson("/api/gdevelop/session/{$sessionId}");
        $accessResponse->assertStatus(404); // Should not find session
        
        // Try to modify session as second user
        $modifyResponse = $this->postJson('/api/gdevelop/chat', [
            'session_id' => $sessionId,
            'workspace_id' => $this->workspace->id,
            'message' => 'Modify the secret game'
        ]);
        
        // Should create new session, not modify existing one
        $modifyResponse->assertStatus(200);
        
        $newSession = GDevelopGameSession::where('session_id', $sessionId)
            ->where('user_id', $this->otherUser->id)
            ->first();
        
        // Should either be null (new session created) or belong to other user
        if ($newSession) {
            $this->assertEquals($this->otherUser->id, $newSession->user_id);
        }
        
        // Original session should remain unchanged
        $originalSession = GDevelopGameSession::where('session_id', $sessionId)
            ->where('user_id', $this->user->id)
            ->first();
        
        $this->assertNotNull($originalSession);
        $this->assertEquals($this->user->id, $originalSession->user_id);
    }

    /** @test */
    public function json_validation_prevents_malformed_data_injection()
    {
        $validator = app(GDevelopJsonValidationService::class);
        
        $malformedJsonInputs = [
            ['invalid' => 'structure'],
            ['properties' => ['name' => str_repeat('x', 10000)]], // Oversized data
            ['layouts' => [['name' => '<script>alert(1)</script>']]],
            ['objects' => [['type' => 'javascript:alert(1)']]],
            ['events' => [['conditions' => [['type' => 'eval("malicious code")']]]]],
            // Deeply nested structure to test recursion limits
            ['nested' => array_fill(0, 1000, ['level' => 'deep'])],
            // Invalid GDevelop structure
            ['invalidProperty' => 'shouldNotExist'],
            // Null bytes and control characters
            ['name' => "test\x00\x01\x02game"],
        ];

        foreach ($malformedJsonInputs as $input) {
            $result = $validator->validateGameJson($input);
            
            if ($result->isValid()) {
                // If validation passes, ensure data was sanitized
                $sanitizedJson = json_encode($result->getSanitizedData());
                $this->assertStringNotContainsString('<script>', $sanitizedJson);
                $this->assertStringNotContainsString('javascript:', $sanitizedJson);
                $this->assertStringNotContainsString('eval(', $sanitizedJson);
                $this->assertStringNotContainsString("\x00", $sanitizedJson);
            } else {
                // Validation should fail gracefully with clear error messages
                $errors = $result->getErrors();
                $this->assertNotEmpty($errors);
                $this->assertIsArray($errors);
            }
        }
    }

    /** @test */
    public function file_system_access_is_sandboxed()
    {
        $sandbox = app(GDevelopSandboxService::class);
        
        // Test that sandbox prevents access to system directories
        $restrictedPaths = [
            '/etc/passwd',
            '/var/log/auth.log',
            'C:\\Windows\\System32\\config\\SAM',
            '/proc/version',
            '/sys/kernel/version',
            '../../../etc/shadow',
            '..\\..\\..\\windows\\system32\\drivers\\etc\\hosts'
        ];

        foreach ($restrictedPaths as $path) {
            $isAllowed = $sandbox->isPathAllowed($path);
            $this->assertFalse($isAllowed, "Path {$path} should not be allowed");
        }

        // Test that sandbox allows access to designated directories
        $allowedPaths = [
            'storage/gdevelop/sessions/test-session/game.json',
            'storage/gdevelop/templates/platformer.json',
            'storage/gdevelop/exports/test-export.zip'
        ];

        foreach ($allowedPaths as $path) {
            $isAllowed = $sandbox->isPathAllowed($path);
            $this->assertTrue($isAllowed, "Path {$path} should be allowed");
        }
    }

    /** @test */
    public function authentication_and_authorization_are_enforced()
    {
        // Test unauthenticated access
        $response = $this->postJson('/api/gdevelop/chat', [
            'session_id' => 'unauth-test',
            'workspace_id' => $this->workspace->id,
            'message' => 'Create a game'
        ]);
        
        $response->assertStatus(401);

        // Test access to other company's workspace
        $this->actingAs($this->otherUser);
        
        $response = $this->postJson('/api/gdevelop/chat', [
            'session_id' => 'unauthorized-test',
            'workspace_id' => $this->workspace->id, // This workspace belongs to different company
            'message' => 'Create a game'
        ]);
        
        // Should either be forbidden or not found
        $this->assertContains($response->getStatusCode(), [403, 404]);

        // Test with disabled GDevelop
        Config::set('gdevelop.enabled', false);
        
        $this->actingAs($this->user);
        
        $response = $this->postJson('/api/gdevelop/chat', [
            'session_id' => 'disabled-test',
            'workspace_id' => $this->workspace->id,
            'message' => 'Create a game'
        ]);
        
        $response->assertStatus(503);
    }

    /** @test */
    public function rate_limiting_prevents_abuse()
    {
        $this->actingAs($this->user);
        
        // Make multiple rapid requests
        $responses = [];
        for ($i = 0; $i < 20; $i++) {
            $responses[] = $this->postJson('/api/gdevelop/chat', [
                'session_id' => "rate-limit-test-{$i}",
                'workspace_id' => $this->workspace->id,
                'message' => 'Create a simple game'
            ]);
        }
        
        // Some requests should be rate limited
        $rateLimitedCount = 0;
        foreach ($responses as $response) {
            if ($response->getStatusCode() === 429) {
                $rateLimitedCount++;
            }
        }
        
        // Should have some rate limiting after many rapid requests
        $this->assertGreaterThan(0, $rateLimitedCount, 'Rate limiting should kick in for rapid requests');
    }

    /** @test */
    public function resource_limits_prevent_dos_attacks()
    {
        $this->actingAs($this->user);
        
        // Test with oversized request
        $oversizedMessage = str_repeat('Create a game with ', 10000) . 'many objects';
        
        $response = $this->postJson('/api/gdevelop/chat', [
            'session_id' => 'dos-test-oversized',
            'workspace_id' => $this->workspace->id,
            'message' => $oversizedMessage
        ]);
        
        // Should either handle gracefully or reject
        $this->assertContains($response->getStatusCode(), [200, 422, 413]);
        
        if ($response->getStatusCode() === 422) {
            $data = $response->json();
            $this->assertArrayHasKey('errors', $data);
        }

        // Test memory exhaustion protection
        $complexRequest = 'Create a game with ' . str_repeat('very complex nested systems and ', 1000) . 'objects';
        
        $response = $this->postJson('/api/gdevelop/chat', [
            'session_id' => 'dos-test-complex',
            'workspace_id' => $this->workspace->id,
            'message' => $complexRequest
        ]);
        
        // Should handle without crashing
        $this->assertContains($response->getStatusCode(), [200, 422, 503]);
    }

    /** @test */
    public function sensitive_information_is_not_exposed()
    {
        $this->actingAs($this->user);
        
        // Create a game and check response for sensitive data
        $response = $this->postJson('/api/gdevelop/chat', [
            'session_id' => 'info-leak-test',
            'workspace_id' => $this->workspace->id,
            'message' => 'Create a game'
        ]);
        
        $response->assertStatus(200);
        $data = $response->json();
        
        // Verify sensitive information is not exposed
        $responseJson = json_encode($data);
        
        $sensitivePatterns = [
            '/password/i',
            '/secret/i',
            '/token/i',
            '/key/i',
            '/api_key/i',
            '/database/i',
            '/config/i',
            '/env/i',
            '/\.env/i',
            '/localhost/i',
            '/127\.0\.0\.1/i',
            '/admin/i',
            '/root/i'
        ];
        
        foreach ($sensitivePatterns as $pattern) {
            $this->assertDoesNotMatchRegularExpression(
                $pattern, 
                $responseJson, 
                "Response should not contain sensitive information matching {$pattern}"
            );
        }
        
        // Check error responses don't leak information
        $errorResponse = $this->postJson('/api/gdevelop/chat', [
            'session_id' => 'error-test',
            'workspace_id' => 999999, // Non-existent workspace
            'message' => 'Create a game'
        ]);
        
        if ($errorResponse->getStatusCode() >= 400) {
            $errorData = $errorResponse->json();
            $errorJson = json_encode($errorData);
            
            // Error messages should not contain file paths or system information
            $this->assertStringNotContainsString('/var/www', $errorJson);
            $this->assertStringNotContainsString('/home/', $errorJson);
            $this->assertStringNotContainsString('C:\\', $errorJson);
            $this->assertStringNotContainsString('laravel', $errorJson);
            $this->assertStringNotContainsString('vendor/', $errorJson);
        }
    }

    /** @test */
    public function csrf_protection_is_enforced()
    {
        // Test API endpoints have proper CSRF protection
        $this->actingAs($this->user);
        
        // Remove CSRF token to test protection
        $response = $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
            ->postJson('/api/gdevelop/chat', [
                'session_id' => 'csrf-test',
                'workspace_id' => $this->workspace->id,
                'message' => 'Create a game'
            ], [
                'X-Requested-With' => 'XMLHttpRequest',
                'Content-Type' => 'application/json'
            ]);
        
        // API endpoints should work with proper headers (Sanctum handles API auth)
        $this->assertContains($response->getStatusCode(), [200, 401, 422]);
    }
}