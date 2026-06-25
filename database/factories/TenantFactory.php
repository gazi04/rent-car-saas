<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Rent A Car',
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'status' => 'active',
            'plan' => 'trial',
            'trial_ends_at' => now()->addDays(30),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'pending']);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'suspended']);
    }

    /**
     * Attach a resolvable domain to the tenant after creation.
     */
    public function withDomain(string $subdomain): static
    {
        return $this->afterCreating(function (Tenant $tenant) use ($subdomain): void {
            $tenant->domains()->create([
                'domain' => $subdomain.'.'.config('tenancy.tenant_base_domain', 'localhost'),
            ]);
        });
    }
}
