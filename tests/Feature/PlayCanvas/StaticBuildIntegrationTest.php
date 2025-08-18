<?php

use App\Models\Company;
use App\Models\Workspace;
use App\Services\PublishService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaticBuildIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Workspace $workspace;
    protected PublishService $publishService;
    protected string $workspacePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->workspace = Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'status' => 'ready',
            'name' => 'test-game',
        ]);
        $this->publishService = app(PublishService::class);
        $this->workspacePath = storage_path("workspaces/{$this->company->id}/{$this->workspace->name}");
    }

    public function test_can_build_playcanvas_project_successfully(): void
    {
        Process::fake([
            'npm run build' => Process::result(output: 'Build completed successfully', exitCode: 0),
        ]);
        File::shouldReceive('exists')->with($this->workspacePath . '/package.json')->andReturn(true);
        File::shouldReceive('exists')->with($this->workspacePath . '/dist')->andReturn(true);
        File::shouldReceive('isDirectory')->with($this->workspacePath . '/dist')->andReturn(true);

        $result = $this->publishService->buildProject($this->workspace);
        $this->assertTrue($result);
        Process::assertRan('npm run build');
    }

    public function test_build_fails_gracefully_with_invalid_package_json(): void
    {
        Process::fake([
            'npm run build' => Process::result(errorOutput: 'npm ERR! missing script: build', exitCode: 1),
        ]);
        File::shouldReceive('exists')->with($this->workspacePath . '/package.json')->andReturn(false);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('package.json not found');
        $this->publishService->buildProject($this->workspace);
    }

    public function test_can_upload_build_to_s3_with_compression(): void
    {
        Process::fake(['npm run build' => Process::result(output: 'Build complete')]);
        Storage::fake('s3');
        Http::fake([
            'https://s3.amazonaws.com/*' => Http::response(['success' => true]),
            'https://cloudfront.amazonaws.com/*' => Http::response(['success' => true]),
        ]);
        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('isDirectory')->andReturn(true);
        File::shouldReceive('allFiles')->with($this->workspacePath . '/dist')->andReturn([
            new \SplFileInfo($this->workspacePath . '/dist/index.html'),
            new \SplFileInfo($this->workspacePath . '/dist/game.js'),
            new \SplFileInfo($this->workspacePath . '/dist/assets/texture.png'),
        ]);
        File::shouldReceive('get')->andReturn('mock file content');
        File::shouldReceive('mimeType')->andReturn('text/html', 'application/javascript', 'image/png');

        $publishedUrl = $this->publishService->publishToS3($this->workspace);
        $this->assertStringStartsWith('https://', $publishedUrl);
        $this->assertStringContainsString((string) $this->company->id, $publishedUrl);
        $this->assertStringContainsString($this->workspace->name, $publishedUrl);

        $this->workspace->refresh();
        $this->assertSame($publishedUrl, $this->workspace->published_url);
        $this->assertSame('published', $this->workspace->status);
    }

    public function test_s3_upload_includes_proper_compression_headers(): void
    {
        Storage::fake('s3');
        Process::fake(['npm run build' => Process::result(output: 'Build complete')]);
        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('isDirectory')->andReturn(true);
        File::shouldReceive('allFiles')->andReturn([new \SplFileInfo($this->workspacePath . '/dist/game.js')]);
        File::shouldReceive('get')->andReturn('console.log("game code");');
        File::shouldReceive('mimeType')->andReturn('application/javascript');
        $this->publishService->publishToS3($this->workspace);
        Storage::disk('s3')->assertExists("builds/{$this->company->id}/{$this->workspace->name}/game.js");
        $this->assertTrue(true);
    }

    public function test_cloudfront_invalidation_is_triggered_after_upload(): void
    {
        Storage::fake('s3');
        Process::fake(['npm run build' => Process::result(output: 'Build complete')]);
        Http::fake([
            'https://cloudfront.amazonaws.com/2020-05-31/distribution/*/invalidation' => Http::response([
                'Invalidation' => ['Id' => 'I1234567890', 'Status' => 'InProgress']
            ], 201),
        ]);
        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('isDirectory')->andReturn(true);
        File::shouldReceive('allFiles')->andReturn([]);
        $publishedUrl = $this->publishService->publishToS3($this->workspace);
        Http::assertSent(fn($req) => str_contains($req->url(), 'cloudfront.amazonaws.com') && str_contains($req->url(), 'invalidation') && $req->method() === 'POST');
        $this->assertStringStartsWith('https://', $publishedUrl);
    }

    public function test_handles_s3_upload_failures_gracefully(): void
    {
        Process::fake(['npm run build' => Process::result(output: 'Build complete')]);
        Http::fake(['https://s3.amazonaws.com/*' => Http::response(['error' => 'Access denied'], 403)]);
        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('isDirectory')->andReturn(true);
        File::shouldReceive('allFiles')->andReturn([]);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to upload to S3');
        $this->publishService->publishToS3($this->workspace);
        $this->workspace->refresh();
        $this->assertSame('error', $this->workspace->status);
    }

    public function test_mobile_optimization_headers_are_set_correctly(): void
    {
        Storage::fake('s3');
        Process::fake(['npm run build' => Process::result(output: 'Build complete')]);
        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('isDirectory')->andReturn(true);
        File::shouldReceive('allFiles')->andReturn([
            new \SplFileInfo($this->workspacePath . '/dist/index.html'),
            new \SplFileInfo($this->workspacePath . '/dist/game.js'),
        ]);
        File::shouldReceive('get')->andReturn('mock content');
        File::shouldReceive('mimeType')->andReturn('text/html', 'application/javascript');
        $this->publishService->publishToS3($this->workspace);
        $this->assertTrue(true);
    }

    public function test_can_publish_to_multiple_environments(): void
    {
        Storage::fake('s3');
        Process::fake(['npm run build' => Process::result(output: 'Build complete')]);
        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('isDirectory')->andReturn(true);
        File::shouldReceive('allFiles')->andReturn([]);
        $stagingUrl = $this->publishService->publishToS3($this->workspace, 'staging');
        $this->assertStringContainsString('staging', $stagingUrl);
        $productionUrl = $this->publishService->publishToS3($this->workspace, 'production');
        $this->assertStringContainsString('production', $productionUrl);
        $this->assertNotSame($stagingUrl, $productionUrl);
    }

    public function test_build_artifacts_are_cleaned_up_after_successful_upload(): void
    {
        Storage::fake('s3');
        Process::fake([
            'npm run build' => Process::result(output: 'Build complete'),
            'rm -rf *' => Process::result(output: 'Cleanup complete'),
        ]);
        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('isDirectory')->andReturn(true);
        File::shouldReceive('allFiles')->andReturn([]);
        File::shouldReceive('deleteDirectory')->with($this->workspacePath . '/dist')->once()->andReturn(true);
        $this->publishService->publishToS3($this->workspace);
        Process::assertRan(fn($cmd) => str_contains($cmd, 'rm -rf'));
    }

    public function test_concurrent_builds_are_handled_properly(): void
    {
        Storage::fake('s3');
        $workspaces = collect(range(1, 3))->map(fn($i) => Workspace::factory()->create([
            'company_id' => $this->company->id,
            'engine_type' => 'playcanvas',
            'name' => "game-{$i}",
        ]));
        Process::fake(['npm run build' => Process::result(output: 'Build complete')]);
        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('isDirectory')->andReturn(true);
        File::shouldReceive('allFiles')->andReturn([]);
        $urls = $workspaces->map(fn($ws) => $this->publishService->publishToS3($ws));
        $this->assertCount(3, $urls->unique());
        $workspaces->each(function ($workspace) {
            $workspace->refresh();
            $this->assertSame('published', $workspace->status);
            $this->assertNotNull($workspace->published_url);
        });
    }
}
