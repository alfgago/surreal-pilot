<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CreditTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CreditTransaction>
 */
class CreditTransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CreditTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'amount' => $this->faker->numberBetween(1, 1000),
            'type' => $this->faker->randomElement(['credit', 'debit']),
            'description' => $this->faker->sentence(),
            'metadata' => [],
        ];
    }

    /**
     * Indicate that the transaction is a debit.
     */
    public function debit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'debit',
        ]);
    }

    /**
     * Indicate that the transaction is a credit.
     */
    public function credit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'credit',
        ]);
    }

    /**
     * Indicate that the transaction has MCP metadata.
     */
    public function withMcpSurcharge(string $engineType = 'playcanvas', float $surcharge = 0.1): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => [
                'engine_type' => $engineType,
                'mcp_surcharge' => $surcharge,
                'base_tokens' => $attributes['amount'] - $surcharge,
                'total_cost' => $attributes['amount'],
                'has_mcp_surcharge' => true,
            ],
        ]);
    }

    /**
     * Indicate that the transaction is for AI API usage.
     */
    public function aiUsage(string $engineType = 'playcanvas'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'debit',
            'description' => 'AI API usage - ' . $engineType,
            'metadata' => [
                'engine_type' => $engineType,
                'usage_type' => 'ai_api',
            ],
        ]);
    }

    /**
     * Indicate that the transaction is for a subscription payment.
     */
    public function subscriptionPayment(string $planName = 'Pro'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'credit',
            'description' => 'Monthly subscription credits - ' . $planName,
            'metadata' => [
                'subscription_type' => 'monthly',
                'plan' => strtolower($planName),
            ],
        ]);
    }

    /**
     * Indicate that the transaction is for a credit purchase.
     */
    public function creditPurchase(int $credits = 10000): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'credit',
            'amount' => $credits,
            'description' => 'Credit purchase - ' . number_format($credits) . ' credits',
            'metadata' => [
                'purchase_type' => 'credit_topup',
                'package_size' => $credits,
            ],
        ]);
    }
}