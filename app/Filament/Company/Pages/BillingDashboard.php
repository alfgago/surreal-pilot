<?php

namespace App\Filament\Company\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class BillingDashboard extends Page
{
    protected string $view = 'filament.company.pages.billing-dashboard';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Billing';

    public function getViewData(): array
    {
        $company = Auth::user()?->currentCompany;
        return [
            'company' => $company,
            'plan' => $company?->subscriptionPlan,
        ];
    }
}

