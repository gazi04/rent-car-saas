<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantPayment>
 */
class TenantPaymentFactory extends Factory
{
    protected $model = TenantPayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $periodStart = fake()->dateTimeBetween('-2 months', 'now');

        return [
            'tenant_id' => Tenant::factory(),
            'plan' => fake()->randomElement(['basic', 'standard', 'pro']),
            'method' => fake()->randomElement(PaymentMethod::cases()),
            'amount' => fake()->randomElement([15, 29, 49]),
            'period_start' => $periodStart,
            'period_end' => (clone $periodStart)->modify('+1 month'),
            'note' => null,
            'recorded_by' => User::factory(),
        ];
    }
}
