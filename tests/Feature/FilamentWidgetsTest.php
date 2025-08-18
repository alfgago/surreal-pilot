<?php

namespace Tests\Feature;

use App\Filament\Widgets\CreditBalanceWidget;
use App\Filament\Widgets\CreditTopUpWidget;
use App\Filament\Widgets\UsageAnalyticsWidget;
use App\Models\Company;
use App\Models\User;
use App\Services\CreditManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a user and company for testing
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create([
            'credits' => 500,
            'plan' => 'pro',
            'monthly_credit_limit' => 5000,
        ]);
        
        // Associate user with company
        $this->company->users()->attach($this->user, ['role' => 'admin']);
    }

    public function test_credit_balance_widget_displays_correct_data()
    {
        $this->actingAs($this->user);
        Filament::setTenant($this->company);

        $component = Livewire::test(CreditBalanceWidget::class);

        // Test that the widget renders without errors
        $component->assertSuccessful();
        
        // Test that the widget contains expected content
        $component->assertSee('500');
        $component->assertSee('Current Credits');
        $component->assertSee('Monthly Usage');
        $component->assertSee('Monthly Limit');
    }

    public function test_usage_analytics_widget_returns_chart_data()
    {
        $this->actingAs($this->user);
        Filament::setTenant($this->company);

        // Add some test transactions
        $creditManager = app(CreditManager::class);
        $creditManager->deductCredits($this->company, 100, 'Test usage');
        $creditManager->addCredits($this->company, 200, 'Test credit');

        $component = Livewire::test(UsageAnalyticsWidget::class);

        // Test that the widget renders without errors
        $component->assertSuccessful();
        
        // Test that the widget contains the chart heading
        $component->assertSee('Credit Usage Analytics');
    }

    public function test_credit_top_up_widget_displays_company_info()
    {
        $this->actingAs($this->user);
        Filament::setTenant($this->company);

        $component = Livewire::test(CreditTopUpWidget::class);
        $viewData = $component->instance()->getViewData();

        $this->assertEquals(500, $viewData['credits']);
        $this->assertEquals('pro', $viewData['plan']);
        $this->assertFalse($viewData['isLowCredits']);
    }

    public function test_credit_top_up_widget_shows_low_credits_warning()
    {
        $this->actingAs($this->user);
        Filament::setTenant($this->company);
        
        // Set low credits
        $this->company->update(['credits' => 50]);

        $component = Livewire::test(CreditTopUpWidget::class);
        $viewData = $component->instance()->getViewData();

        $this->assertTrue($viewData['isLowCredits']);
    }

    public function test_purchase_credits_action_works()
    {
        $this->actingAs($this->user);
        Filament::setTenant($this->company);

        $component = Livewire::test(CreditTopUpWidget::class);
        
        $component->callAction('purchaseCredits', [
            'credit_package' => '1000'
        ]);

        // Refresh the company model
        $this->company->refresh();
        
        // Should have original 500 + 1000 purchased
        $this->assertEquals(1500, $this->company->credits);
    }
}