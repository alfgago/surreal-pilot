<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditTransaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'amount',
        'type',
        'description',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Tests expect integer type for amount
            'amount' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the company that owns the credit transaction.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope to get debit transactions.
     */
    public function scopeDebits($query)
    {
        return $query->where('type', 'debit');
    }

    /**
     * Scope to get credit transactions.
     */
    public function scopeCredits($query)
    {
        return $query->where('type', 'credit');
    }

    /**
     * Scope to get transactions for a specific month.
     */
    public function scopeForMonth($query, int $month, int $year)
    {
        return $query->whereMonth('created_at', $month)
                    ->whereYear('created_at', $year);
    }
}
