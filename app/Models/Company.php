<?php

namespace App\Models;

use Filament\Models\Contracts\HasAvatar;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Cashier\Billable;

class Company extends Model implements HasAvatar
{
    use HasFactory, Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'user_id',
        'personal_company',
        'credits',
        'plan',
        'monthly_credit_limit',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
        'playcanvas_api_key',
        'playcanvas_project_id',
        'openai_api_key_enc',
        'anthropic_api_key_enc',
        'gemini_api_key_enc',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'personal_company' => 'boolean',
            'credits' => 'decimal:2',
            'monthly_credit_limit' => 'decimal:2',
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * Get the attributes that should be hidden for serialization.
     *
     * @return array<int, string>
     */
    protected $hidden = [
        'playcanvas_api_key',
        'openai_api_key_enc',
        'anthropic_api_key_enc',
        'gemini_api_key_enc',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user')->withTimestamps();
    }

    /**
     * Check if the company has PlayCanvas credentials configured.
     */
    public function hasPlayCanvasCredentials(): bool
    {
        return !empty($this->playcanvas_api_key) && !empty($this->playcanvas_project_id);
    }

    public function getFilamentAvatarUrl(): string
    {
        $initial = strtoupper(substr($this->name ?? 'C', 0, 1));
        return "https://ui-avatars.com/api/?name={$initial}&background=0ea5e9&color=fff";
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Check if the company can afford the specified cost (tokens + surcharges).
     */
    public function canAffordTokens(int|float $cost): bool
    {
        return $this->credits >= $cost;
    }

    /**
     * Get the monthly usage for the current month.
     */
    public function getMonthlyUsage(): float
    {
        return $this->creditTransactions()
            ->debits()
            ->forMonth(now()->month, now()->year)
            ->sum('amount');
    }

    /**
     * Get the credit transactions relationship.
     */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    /**
     * Get the subscription plan relationship.
     */
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan', 'slug');
    }

    /**
     * Get the billing history relationship.
     */
    public function billingHistory(): HasMany
    {
        return $this->hasMany(BillingHistory::class);
    }

    /**
     * Get the monthly credit limit from the subscription plan.
     */
    public function getMonthlyCreditLimitAttribute(): float
    {
        return $this->subscriptionPlan?->monthly_credits ?? $this->attributes['monthly_credit_limit'] ?? 1000;
    }

    /**
     * Get the workspaces relationship.
     */
    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }
}
