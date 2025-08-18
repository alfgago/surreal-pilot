<?php

namespace Tests\Unit\Services;

use App\Models\Workspace;
use App\Models\Company;
use App\Models\MultiplayerSession;
use App\Services\CloudFrontCleanupService;
use Aws\CloudFront\CloudFrontClient;
use Aws\Exception\AwsException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class CloudFrontCleanupServiceTest extends TestCase
{
    use RefreshDatabase;

    private CloudFrontCleanupService $service;
    private $cloudFrontClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cloudFrontClient = Mockery::mock(CloudFrontClient::class);
        $this->service = new CloudFrontCleanupService($this->cloudFrontClient);

        // Mock configuration
        config(['services.aws.cloudfront_distribution_id' => 'E1234567890']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_cleanup_workspace_paths_success()
    {
        // Arrange
        $company = Company::factory()->create();
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'published_url' => 'https://cdn.example.com/builds/1/2/index.html',
        ]);

        $session = MultiplayerSession::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $expectedPaths = [
            "/builds/{$company->id}/{$workspace->id}/*",
            "/workspaces/{$company->id}/{$workspace->id}/*",
            "/api/workspace/{$workspace->id}/*",
            "/api/workspace/{$workspace->id}/status",
            "/api/workspace/{$workspace->id}/preview",
            "/builds/1/2/index.html*",
            "/multiplayer/session/{$session->id}/*",
            "/api/multiplayer/session/{$session->id}/*",
        ];

        $this->cloudFrontClient
            ->shouldReceive('createInvalidation')
            ->once()
            ->with(Mockery::on(function ($args) use ($expectedPaths) {
                $this->assertEquals('E1234567890', $args['DistributionId']);
                $this->assertEquals(count($expectedPaths), $args['InvalidationBatch']['Paths']['Quantity']);
                
                // Check that all expected paths are included (order may vary)
                $actualPaths = $args['InvalidationBatch']['Paths']['Items'];
                foreach ($expectedPaths as $expectedPath) {
                    $this->assertContains($expectedPath, $actualPaths);
                }
                
                return true;
            }))
            ->andReturn([
                'Invalidation' => [
                    'Id' => 'I1234567890',
                ],
            ]);

        // Act
        $result = $this->service->cleanupWorkspacePaths($workspace);

        // Assert
        $this->assertEquals(count($expectedPaths), $result);
    }

    public function test_cleanup_workspace_paths_no_distribution_id()
    {
        // Arrange
        config(['services.aws.cloudfront_distribution_id' => null]);
        
        $company = Company::factory()->create();
        $workspace = Workspace::factory()->create(['company_id' => $company->id]);

        // Act
        $result = $this->service->cleanupWorkspacePaths($workspace);

        // Assert
        $this->assertEquals(0, $result);
    }

    public function test_cleanup_workspace_paths_aws_exception()
    {
        // Arrange
        $company = Company::factory()->create();
        $workspace = Workspace::factory()->create(['company_id' => $company->id]);

        $this->cloudFrontClient
            ->shouldReceive('createInvalidation')
            ->once()
            ->andThrow(new AwsException('CloudFront error', Mockery::mock('Aws\Command\CommandInterface')));

        // Act
        $result = $this->service->cleanupWorkspacePaths($workspace);

        // Assert
        $this->assertEquals(0, $result);
    }

    public function test_cleanup_multiplayer_session_paths()
    {
        // Arrange
        $sessionIds = ['session-1', 'session-2'];
        
        $expectedPaths = [
            '/multiplayer/session/session-1/*',
            '/api/multiplayer/session/session-1/*',
            '/multiplayer/session/session-2/*',
            '/api/multiplayer/session/session-2/*',
        ];

        $this->cloudFrontClient
            ->shouldReceive('createInvalidation')
            ->once()
            ->with(Mockery::on(function ($args) use ($expectedPaths) {
                $this->assertEquals('E1234567890', $args['DistributionId']);
                $this->assertEquals(count($expectedPaths), $args['InvalidationBatch']['Paths']['Quantity']);
                $this->assertEquals($expectedPaths, $args['InvalidationBatch']['Paths']['Items']);
                return true;
            }))
            ->andReturn([
                'Invalidation' => [
                    'Id' => 'I1234567890',
                ],
            ]);

        // Act
        $result = $this->service->cleanupMultiplayerSessionPaths($sessionIds);

        // Assert
        $this->assertEquals(4, $result);
    }

    public function test_cleanup_multiplayer_session_paths_empty_sessions()
    {
        // Act
        $result = $this->service->cleanupMultiplayerSessionPaths([]);

        // Assert
        $this->assertEquals(0, $result);
    }

    public function test_cleanup_stale_cache()
    {
        // Arrange
        $expectedPaths = [
            '/builds/*',
            '/workspaces/*',
            '/multiplayer/*',
            '/api/workspace/*/status',
            '/api/multiplayer/*/status',
        ];

        $this->cloudFrontClient
            ->shouldReceive('createInvalidation')
            ->once()
            ->with(Mockery::on(function ($args) use ($expectedPaths) {
                $this->assertEquals('E1234567890', $args['DistributionId']);
                $this->assertEquals(count($expectedPaths), $args['InvalidationBatch']['Paths']['Quantity']);
                $this->assertEquals($expectedPaths, $args['InvalidationBatch']['Paths']['Items']);
                return true;
            }))
            ->andReturn([
                'Invalidation' => [
                    'Id' => 'I1234567890',
                ],
            ]);

        // Act
        $result = $this->service->cleanupStaleCache();

        // Assert
        $this->assertEquals(1, $result);
    }

    public function test_cleanup_stale_cache_no_distribution_id()
    {
        // Arrange
        config(['services.aws.cloudfront_distribution_id' => null]);

        // Act
        $result = $this->service->cleanupStaleCache();

        // Assert
        $this->assertEquals(0, $result);
    }

    public function test_get_invalidation_status()
    {
        // Arrange
        $invalidationId = 'I1234567890';
        $expectedResponse = [
            'Invalidation' => [
                'Id' => $invalidationId,
                'Status' => 'Completed',
                'CreateTime' => '2023-01-01T00:00:00Z',
                'InvalidationBatch' => [
                    'Paths' => [
                        'Quantity' => 5,
                    ],
                ],
            ],
        ];

        $this->cloudFrontClient
            ->shouldReceive('getInvalidation')
            ->once()
            ->with([
                'DistributionId' => 'E1234567890',
                'Id' => $invalidationId,
            ])
            ->andReturn($expectedResponse);

        // Act
        $result = $this->service->getInvalidationStatus($invalidationId);

        // Assert
        $this->assertEquals([
            'id' => $invalidationId,
            'status' => 'Completed',
            'create_time' => '2023-01-01T00:00:00Z',
            'paths_count' => 5,
        ], $result);
    }

    public function test_get_invalidation_status_no_distribution_id()
    {
        // Arrange
        config(['services.aws.cloudfront_distribution_id' => null]);

        // Act
        $result = $this->service->getInvalidationStatus('I1234567890');

        // Assert
        $this->assertNull($result);
    }

    public function test_get_invalidation_status_aws_exception()
    {
        // Arrange
        $this->cloudFrontClient
            ->shouldReceive('getInvalidation')
            ->once()
            ->andThrow(new AwsException('Not found', Mockery::mock('Aws\Command\CommandInterface')));

        // Act
        $result = $this->service->getInvalidationStatus('I1234567890');

        // Assert
        $this->assertNull($result);
    }

    public function test_list_recent_invalidations()
    {
        // Arrange
        $expectedResponse = [
            'InvalidationList' => [
                'Items' => [
                    [
                        'Id' => 'I1234567890',
                        'Status' => 'Completed',
                        'CreateTime' => '2023-01-01T00:00:00Z',
                    ],
                    [
                        'Id' => 'I0987654321',
                        'Status' => 'InProgress',
                        'CreateTime' => '2023-01-02T00:00:00Z',
                    ],
                ],
            ],
        ];

        $this->cloudFrontClient
            ->shouldReceive('listInvalidations')
            ->once()
            ->with([
                'DistributionId' => 'E1234567890',
                'MaxItems' => 10,
            ])
            ->andReturn($expectedResponse);

        // Act
        $result = $this->service->listRecentInvalidations(10);

        // Assert
        $this->assertEquals([
            [
                'id' => 'I1234567890',
                'status' => 'Completed',
                'create_time' => '2023-01-01T00:00:00Z',
            ],
            [
                'id' => 'I0987654321',
                'status' => 'InProgress',
                'create_time' => '2023-01-02T00:00:00Z',
            ],
        ], $result);
    }

    public function test_list_recent_invalidations_no_distribution_id()
    {
        // Arrange
        config(['services.aws.cloudfront_distribution_id' => null]);

        // Act
        $result = $this->service->listRecentInvalidations();

        // Assert
        $this->assertEquals([], $result);
    }

    public function test_list_recent_invalidations_aws_exception()
    {
        // Arrange
        $this->cloudFrontClient
            ->shouldReceive('listInvalidations')
            ->once()
            ->andThrow(new AwsException('Access denied', Mockery::mock('Aws\Command\CommandInterface')));

        // Act
        $result = $this->service->listRecentInvalidations();

        // Assert
        $this->assertEquals([], $result);
    }

    public function test_is_enabled_with_distribution_id()
    {
        // Act & Assert
        $this->assertTrue($this->service->isEnabled());
    }

    public function test_is_enabled_without_distribution_id()
    {
        // Arrange
        config(['services.aws.cloudfront_distribution_id' => null]);

        // Act & Assert
        $this->assertFalse($this->service->isEnabled());
    }

    public function test_workspace_paths_generation_without_published_url()
    {
        // Arrange
        $company = Company::factory()->create();
        $workspace = Workspace::factory()->create([
            'company_id' => $company->id,
            'published_url' => null,
        ]);

        $this->cloudFrontClient
            ->shouldReceive('createInvalidation')
            ->once()
            ->with(Mockery::on(function ($args) use ($company, $workspace) {
                $paths = $args['InvalidationBatch']['Paths']['Items'];
                
                // Should not contain published URL path
                foreach ($paths as $path) {
                    $this->assertStringNotContainsString('index.html*', $path);
                }
                
                // Should contain basic workspace paths
                $this->assertContains("/builds/{$company->id}/{$workspace->id}/*", $paths);
                $this->assertContains("/workspaces/{$company->id}/{$workspace->id}/*", $paths);
                
                return true;
            }))
            ->andReturn([
                'Invalidation' => [
                    'Id' => 'I1234567890',
                ],
            ]);

        // Act
        $result = $this->service->cleanupWorkspacePaths($workspace);

        // Assert
        $this->assertGreaterThan(0, $result);
    }
}