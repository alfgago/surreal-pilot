<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;

class BillingController extends Controller
{
    public function createSubscriptionSession(Request $request)
    {
        $request->validate([
            'plan_slug' => 'required|string|exists:subscription_plans,slug',
        ]);

        $plan = SubscriptionPlan::where('slug', $request->plan_slug)->first();
        $user = Auth::user();
        $company = $user->currentCompany;

        // Create Stripe Checkout Session via Cashier
        return $user->checkout([$plan->stripe_price_id => 1], [
            'success_url' => route('billing.success'),
            'cancel_url' => route('billing.cancel'),
            'metadata' => [
                'company_id' => (string) $company->id,
                'type' => 'subscription',
                'plan_slug' => $plan->slug,
            ],
            'mode' => 'subscription',
        ]);
    }

    public function createTopUpSession(Request $request)
    {
        $request->validate([
            'credits' => 'required|integer|in:10000',
        ]);

        $user = Auth::user();
        $company = $user->currentCompany;

        // Assume top-up price id in env
        $priceId = config('services.stripe.topup_price_id', 'price_topup_10k');

        return $user->checkout([$priceId => 1], [
            'success_url' => route('billing.success'),
            'cancel_url' => route('billing.cancel'),
            'metadata' => [
                'company_id' => (string) $company->id,
                'type' => 'credit_purchase',
                'credits' => (string) $request->credits,
            ],
            'mode' => 'payment',
        ]);
    }
}

