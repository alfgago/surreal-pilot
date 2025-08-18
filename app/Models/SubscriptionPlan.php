<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'monthly_credits',
        'price_cents',
        'stripe_price_id',
        'allow_byo_keys',
        'addon_price_cents',
        'addon_credits_per_unit',
        'features',
        'allow_unreal',
        'allow_multiplayer',
        'allow_advanced_publish',
    ];

    protected $casts = [
        'monthly_credits' => 'integer',
        'price_cents' => 'integer',
        'allow_byo_keys' => 'boolean',
        'addon_price_cents' => 'integer',
        'addon_credits_per_unit' => 'integer',
        'features' => 'array',
        'allow_unreal' => 'boolean',
        'allow_multiplayer' => 'boolean',
        'allow_advanced_publish' => 'boolean',
    ];

    /**
     * Get the companies that have this subscription plan.
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'plan', 'slug');
    }
}
