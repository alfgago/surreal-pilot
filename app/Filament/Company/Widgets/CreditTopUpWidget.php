<?php

namespace App\Filament\Company\Widgets;

use App\Models\SubscriptionPlan;
use App\Services\CreditManager;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Maartenpaauw\Filament\Cashier\Billing\Providers\Contracts\BillingProvider;

class CreditTopUpWidget extends Widget implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    protected string $view = 'filament.widgets.credit-top-up';
    protected static ?int $sort = 3;

    public function getViewData(): array
    {
        $company = Filament::getTenant();
        
        if (!$company) {
            return [
                'credits' => 0,
                'plan' => 'none',
                'isLowCredits' => false,
            ];
        }

        $creditManager = app(CreditManager::class);
        $balanceSummary = $creditManager->getBalanceSummary($company);

        return [
            'credits' => $balanceSummary['current_credits'],
            'plan' => $balanceSummary['plan'],
            'isLowCredits' => $balanceSummary['current_credits'] < 100,
            'isApproachingLimit' => $balanceSummary['is_approaching_limit'],
            'hasActiveSubscription' => $company->subscribed(),
        ];
    }

    public function purchaseCreditsAction(): Action
    {
        return Action::make('purchaseCredits')
            ->label('Purchase Credits')
            ->icon('heroicon-o-plus-circle')
            ->color('success')
            ->form([
                Select::make('credit_package')
                    ->label('Credit Package')
                    ->options([
                        '1000' => '1,000 Credits - $10',
                        '5000' => '5,000 Credits - $45 (10% bonus)',
                        '10000' => '10,000 Credits - $80 (20% bonus)',
                        '25000' => '25,000 Credits - $175 (30% bonus)',
                    ])
                    ->required()
                    ->native(false),
            ])
            ->action(function (array $data): void {
                $company = Filament::getTenant();
                $credits = (int) $data['credit_package'];
                
                // Calculate price based on package
                $priceMap = [
                    '1000' => 1000,   // $10.00
                    '5000' => 4500,   // $45.00
                    '10000' => 8000,  // $80.00
                    '25000' => 17500, // $175.00
                ];
                
                $priceInCents = $priceMap[$data['credit_package']];
                
                try {
                    // Create Stripe checkout session for credit purchase
                    $checkout = $company->checkout([
                        'price_data' => [
                            'currency' => 'usd',
                            'product_data' => [
                                'name' => number_format($credits) . ' AI Credits',
                                'description' => 'AI Credits for SurrealPilot',
                            ],
                            'unit_amount' => $priceInCents,
                        ],
                        'quantity' => 1,
                    ], [
                        'success_url' => route('filament.company.pages.dashboard', ['tenant' => $company]) . '?checkout=success',
                        'cancel_url' => route('filament.company.pages.dashboard', ['tenant' => $company]) . '?checkout=cancelled',
                        'metadata' => [
                            'type' => 'credit_purchase',
                            'credits' => $credits,
                            'company_id' => $company->id,
                        ],
                    ]);

                    // Redirect to Stripe checkout
                    redirect($checkout->url);
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Payment Error')
                        ->body('Unable to process payment. Please try again.')
                        ->danger()
                        ->send();
                }
            });
    }

    public function manageSubscriptionAction(): Action
    {
        return Action::make('manageSubscription')
            ->label('Manage Subscription')
            ->icon('heroicon-o-cog-6-tooth')
            ->color('primary')
            ->url(function () {
                $company = Filament::getTenant();
                
                if ($company->subscribed()) {
                    // Return to billing portal for existing customers
                    return $company->billingPortalUrl(
                        route('filament.company.pages.dashboard', ['tenant' => $company])
                    );
                }
                
                return null;
            })
            ->openUrlInNewTab()
            ->visible(fn () => Filament::getTenant()?->subscribed());
    }

    public function upgradeSubscriptionAction(): Action
    {
        return Action::make('upgradeSubscription')
            ->label('Upgrade Plan')
            ->icon('heroicon-o-arrow-up-circle')
            ->color('primary')
            ->form([
                Select::make('plan')
                    ->label('Subscription Plan')
                    ->options(function () {
                        return SubscriptionPlan::all()
                            ->pluck('name', 'stripe_price_id')
                            ->map(function ($name, $priceId) {
                                $plan = SubscriptionPlan::where('stripe_price_id', $priceId)->first();
                                return "{$name} - {$plan->monthly_credits} credits/month - $" . ($plan->price_cents / 100);
                            });
                    })
                    ->required()
                    ->native(false),
            ])
            ->action(function (array $data): void {
                $company = Filament::getTenant();
                $stripePriceId = $data['plan'];
                
                try {
                    if ($company->subscribed()) {
                        // Update existing subscription
                        $company->subscription()->swap($stripePriceId);
                        
                        Notification::make()
                            ->title('Plan Updated Successfully')
                            ->body('Your subscription has been updated.')
                            ->success()
                            ->send();
                    } else {
                        // Create new subscription checkout
                        $checkout = $company->newSubscription('default', $stripePriceId)
                            ->checkout([
                                'success_url' => route('filament.company.pages.dashboard', ['tenant' => $company]) . '?subscription=success',
                                'cancel_url' => route('filament.company.pages.dashboard', ['tenant' => $company]) . '?subscription=cancelled',
                            ]);

                        redirect($checkout->url);
                    }
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Subscription Error')
                        ->body('Unable to process subscription. Please try again.')
                        ->danger()
                        ->send();
                }
            })
            ->visible(fn () => !Filament::getTenant()?->subscribed());
    }
}