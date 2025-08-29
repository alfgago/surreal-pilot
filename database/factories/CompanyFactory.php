<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Company::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Test Company ' . rand(1000, 9999),
            'user_id' => User::factory(),
            'personal_company' => true,
            'credits' => rand(0, 5000),
            'plan' => 'starter',
            'monthly_credit_limit' => rand(1000, 10000),
        ];
    }
}
