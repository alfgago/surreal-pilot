<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\User;
use App\Services\ErrorMonitoringService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ErrorMonitoringServiceTest extends TestCase
{
    use RefreshDatabase;

    private ErrorMonitoringService $errorMonitoring;

    protected function setUp(): void
    {
        parent::setUp();
        $this->errorMonitoring = new ErrorMonitoringService();
        
        // Create the api_error_logs table
        $this->artisan('migrate');
    }

    public function test_tracks_error_occurrence()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company);

        $this->errorMonitoring->trackError(
            'insufficient_credits',
            'Company has insufficient credits',
            $user,
            $company,
            ['credits_needed' => 100]
        );

        $this->assertDatabaseHas('api_error_logs', [
            'error_type' => 'insufficient_credits',
            'message' => 'Company has insufficient credits',
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);
    }

    public function test_gets_company_error_stats()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        
        // Create some test error logs
        DB::table('api_error_logs')->insert([
            [
                'error_type' => 'insufficient_credits',
                'message' => 'Not enough credits',
                'user_id' => $user->id,
                'company_id' => $company->id,
                'context' => json_encode([]),
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subHours(2),
            ],
            [
                'error_type' => 'provider_unavailable',
                'message' => 'OpenAI unavailable',
                'user_id' => $user->id,
                'company_id' => $company->id,
                'context' => json_encode([]),
                'created_at' => now()->subHours(1),
                'updated_at' => now()->subHours(1),
            ],
            [
                'error_type' => 'insufficient_credits',
                'message' => 'Not enough credits again',
                'user_id' => $user->id,
                'company_id' => $company->id,
                'context' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $stats = $this->errorMonitoring->getCompanyErrorStats(
            $company,
            now()->subDays(1),
            now()
        );

        $this->assertEquals(3, $stats['total_errors']);
        $this->assertEquals(2, $stats['errors_by_type']['insufficient_credits']);
        $this->assertEquals(1, $stats['errors_by_type']['provider_unavailable']);
        $this->assertEquals('insufficient_credits', $stats['most_common_error']);
    }

    public function test_gets_recent_errors()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        
        // Create errors at different times
        DB::table('api_error_logs')->insert([
            [
                'error_type' => 'old_error',
                'message' => 'Old error',
                'user_id' => $user->id,
                'company_id' => $company->id,
                'context' => json_encode([]),
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ],
            [
                'error_type' => 'recent_error',
                'message' => 'Recent error',
                'user_id' => $user->id,
                'company_id' => $company->id,
                'context' => json_encode([]),
                'created_at' => now()->subHours(1),
                'updated_at' => now()->subHours(1),
            ],
        ]);

        $recentErrors = $this->errorMonitoring->getRecentErrors($company, 24);

        $this->assertCount(1, $recentErrors);
        $this->assertEquals('recent_error', $recentErrors[0]->error_type);
    }

    public function test_detects_high_error_rate()
    {
        $company = Company::factory()->create();
        
        // Simulate high error rate by setting cache
        Cache::put("error_monitoring:rate:{$company->id}", 15, 300);
        
        $this->assertTrue($this->errorMonitoring->isHighErrorRate($company));
        
        // Test normal error rate
        Cache::put("error_monitoring:rate:{$company->id}", 5, 300);
        
        $this->assertFalse($this->errorMonitoring->isHighErrorRate($company));
    }

    public function test_gets_error_patterns()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        
        // Create errors with specific patterns - use current time to ensure they're within the 7-day window
        $now = now();
        
        DB::table('api_error_logs')->insert([
            [
                'error_type' => 'morning_error',
                'message' => 'Morning error',
                'user_id' => $user->id,
                'company_id' => $company->id,
                'context' => json_encode([]),
                'created_at' => $now->copy()->subHours(2), // 2 hours ago
                'updated_at' => $now->copy()->subHours(2),
            ],
            [
                'error_type' => 'afternoon_error',
                'message' => 'Afternoon error',
                'user_id' => $user->id,
                'company_id' => $company->id,
                'context' => json_encode([]),
                'created_at' => $now->copy()->subHours(1), // 1 hour ago
                'updated_at' => $now->copy()->subHours(1),
            ],
            [
                'error_type' => 'afternoon_error2',
                'message' => 'Another afternoon error',
                'user_id' => $user->id,
                'company_id' => $company->id,
                'context' => json_encode([]),
                'created_at' => $now, // now
                'updated_at' => $now,
            ],
        ]);

        $patterns = $this->errorMonitoring->getErrorPatterns($company, 7);

        $this->assertArrayHasKey('hourly_distribution', $patterns);
        $this->assertArrayHasKey('daily_distribution', $patterns);
        $this->assertArrayHasKey('anomalies', $patterns);
        
        // Check that we have some hourly data
        $this->assertGreaterThan(0, count($patterns['hourly_distribution']));
        
        // Check that total errors match what we inserted
        $totalHourlyErrors = array_sum($patterns['hourly_distribution']);
        $this->assertEquals(3, $totalHourlyErrors);
    }

    public function test_gets_system_error_stats()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $user = User::factory()->create();
        
        // Create errors for different companies
        DB::table('api_error_logs')->insert([
            [
                'error_type' => 'system_error',
                'message' => 'System error 1',
                'user_id' => $user->id,
                'company_id' => $company1->id,
                'context' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'error_type' => 'system_error',
                'message' => 'System error 2',
                'user_id' => $user->id,
                'company_id' => $company2->id,
                'context' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $stats = $this->errorMonitoring->getSystemErrorStats(
            now()->subHour(),
            now()
        );

        $this->assertEquals(2, $stats['total_errors']);
        $this->assertEquals(2, $stats['affected_companies']);
        $this->assertGreaterThan(0, $stats['error_rate_per_hour']);
    }
}