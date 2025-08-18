<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\CreditTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditTransactionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->company = Company::factory()->create();
    }

    public function test_credit_transaction_belongs_to_company()
    {
        $transaction = CreditTransaction::create([
            'company_id' => $this->company->id,
            'amount' => 100,
            'type' => 'debit',
            'description' => 'Test transaction',
        ]);

        $this->assertInstanceOf(Company::class, $transaction->company);
        $this->assertEquals($this->company->id, $transaction->company->id);
    }

    public function test_credit_transaction_casts_attributes_correctly()
    {
        $metadata = ['provider' => 'openai', 'model' => 'gpt-4'];
        
        $transaction = CreditTransaction::create([
            'company_id' => $this->company->id,
            'amount' => '100', // String should be cast to integer
            'type' => 'debit',
            'description' => 'Test transaction',
            'metadata' => $metadata,
        ]);

        $this->assertIsInt($transaction->amount);
        $this->assertEquals(100, $transaction->amount);
        $this->assertIsArray($transaction->metadata);
        $this->assertEquals($metadata, $transaction->metadata);
    }

    public function test_debits_scope_filters_correctly()
    {
        // Create debit transaction
        CreditTransaction::create([
            'company_id' => $this->company->id,
            'amount' => 100,
            'type' => 'debit',
            'description' => 'Debit transaction',
        ]);

        // Create credit transaction
        CreditTransaction::create([
            'company_id' => $this->company->id,
            'amount' => 200,
            'type' => 'credit',
            'description' => 'Credit transaction',
        ]);

        $debits = CreditTransaction::debits()->get();
        
        $this->assertEquals(1, $debits->count());
        $this->assertEquals('debit', $debits->first()->type);
        $this->assertEquals(100, $debits->first()->amount);
    }

    public function test_credits_scope_filters_correctly()
    {
        // Create debit transaction
        CreditTransaction::create([
            'company_id' => $this->company->id,
            'amount' => 100,
            'type' => 'debit',
            'description' => 'Debit transaction',
        ]);

        // Create credit transaction
        CreditTransaction::create([
            'company_id' => $this->company->id,
            'amount' => 200,
            'type' => 'credit',
            'description' => 'Credit transaction',
        ]);

        $credits = CreditTransaction::credits()->get();
        
        $this->assertEquals(1, $credits->count());
        $this->assertEquals('credit', $credits->first()->type);
        $this->assertEquals(200, $credits->first()->amount);
    }

    public function test_for_month_scope_filters_correctly()
    {
        // Create transaction for current month
        CreditTransaction::create([
            'company_id' => $this->company->id,
            'amount' => 100,
            'type' => 'debit',
            'description' => 'Current month',
            'created_at' => now(),
        ]);

        // Create transaction for previous month
        $previousTransaction = new CreditTransaction([
            'company_id' => $this->company->id,
            'amount' => 200,
            'type' => 'debit',
            'description' => 'Previous month',
        ]);
        $previousTransaction->created_at = now()->subMonths(2)->startOfMonth();
        $previousTransaction->updated_at = now()->subMonths(2)->startOfMonth();
        $previousTransaction->save();

        $currentMonthTransactions = CreditTransaction::forMonth(now()->month, now()->year)->get();
        
        $this->assertEquals(1, $currentMonthTransactions->count());
        $this->assertEquals('Current month', $currentMonthTransactions->first()->description);
    }

    public function test_can_combine_scopes()
    {
        // Create various transactions
        CreditTransaction::create([
            'company_id' => $this->company->id,
            'amount' => 100,
            'type' => 'debit',
            'description' => 'Current month debit',
            'created_at' => now(),
        ]);

        CreditTransaction::create([
            'company_id' => $this->company->id,
            'amount' => 200,
            'type' => 'credit',
            'description' => 'Current month credit',
            'created_at' => now(),
        ]);

        $previousMonthTransaction = new CreditTransaction([
            'company_id' => $this->company->id,
            'amount' => 150,
            'type' => 'debit',
            'description' => 'Previous month debit',
        ]);
        $previousMonthTransaction->created_at = now()->subMonths(2)->startOfMonth();
        $previousMonthTransaction->updated_at = now()->subMonths(2)->startOfMonth();
        $previousMonthTransaction->save();

        // Get current month debits only
        $currentMonthDebits = CreditTransaction::debits()
            ->forMonth(now()->month, now()->year)
            ->get();
        
        $this->assertEquals(1, $currentMonthDebits->count());
        $this->assertEquals('Current month debit', $currentMonthDebits->first()->description);
        $this->assertEquals('debit', $currentMonthDebits->first()->type);
    }

    public function test_fillable_attributes_are_mass_assignable()
    {
        $attributes = [
            'company_id' => $this->company->id,
            'amount' => 100,
            'type' => 'debit',
            'description' => 'Test transaction',
            'metadata' => ['test' => 'data'],
        ];

        $transaction = CreditTransaction::create($attributes);

        $this->assertEquals($attributes['company_id'], $transaction->company_id);
        $this->assertEquals($attributes['amount'], $transaction->amount);
        $this->assertEquals($attributes['type'], $transaction->type);
        $this->assertEquals($attributes['description'], $transaction->description);
        $this->assertEquals($attributes['metadata'], $transaction->metadata);
    }
}