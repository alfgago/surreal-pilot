<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingHistory extends Model
{
    protected $table = 'billing_history';

    protected $fillable = [
        'company_id',
        'type',
        'description',
        'amount_cents',
        'currency',
        'status',
        'stripe_payment_intent_id',
        'stripe_invoice_id',
        'stripe_subscription_id',
        'credits_added',
        'metadata',
        'processed_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'credits_added' => 'integer',
        'metadata' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the company that owns this billing record.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the formatted amount.
     */
    public function getFormattedAmountAttribute(): string
    {
        return '$' . number_format($this->amount_cents / 100, 2);
    }

    /**
     * Scope for successful payments.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'succeeded');
    }

    /**
     * Scope for credit purchases.
     */
    public function scopeCreditPurchases($query)
    {
        return $query->where('type', 'credit_purchase');
    }

    /**
     * Scope for subscription payments.
     */
    public function scopeSubscriptionPayments($query)
    {
        return $query->where('type', 'subscription_payment');
    }
}