<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'monthly_credits' => 10000, // 10k tokens
                'price_cents' => 500, // $5.00
                'stripe_price_id' => 'price_starter_5',
                'allow_byo_keys' => false,
                'addon_price_cents' => 500, // $5 add-on
                'addon_credits_per_unit' => 10000, // 10k credits per add-on
                'features' => ['Chat', 'Web & Mobile games', 'Email support'],
                'allow_unreal' => false, // restrict to JS games only
                'allow_multiplayer' => false,
                'allow_advanced_publish' => false,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'monthly_credits' => 10000, // 10k credits expected by tests
                'price_cents' => 2500, // $25.00
                'stripe_price_id' => 'price_pro_25',
                'allow_byo_keys' => false,
                'addon_price_cents' => 500,
                'addon_credits_per_unit' => 10000,
                'features' => ['Chat', 'Engine actions', 'Multiplayer helper', 'Priority support'],
                'allow_unreal' => true,
                'allow_multiplayer' => true,
                'allow_advanced_publish' => false,
            ],
            [
                'name' => 'Studio',
                'slug' => 'studio',
                'monthly_credits' => 120000, // 120k tokens
                'price_cents' => 5000, // $50.00
                'stripe_price_id' => 'price_studio_50',
                'allow_byo_keys' => true, // BYO keys allowed on this plan
                'addon_price_cents' => 500,
                'addon_credits_per_unit' => 10000,
                'features' => ['Chat', 'Engine actions', 'Priority support', 'Bring Your Own API Keys', 'Steam/iOS/Android publish helper'],
                'allow_unreal' => true,
                'allow_multiplayer' => true,
                'allow_advanced_publish' => true,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'monthly_credits' => 100000, // 100k credits expected by tests
                'price_cents' => 15000, // $150.00
                'stripe_price_id' => 'price_enterprise_150',
                'allow_byo_keys' => true,
                'addon_price_cents' => 500,
                'addon_credits_per_unit' => 10000,
                'features' => ['All features', 'Priority support', 'SLA'],
                'allow_unreal' => true,
                'allow_multiplayer' => true,
                'allow_advanced_publish' => true,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
