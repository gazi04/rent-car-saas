<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Settings are normally written through Tenant::setSetting(), which enforces the
 * key allow-list and per-key value format. This factory bypasses both on purpose,
 * so tests can stage rows that setSetting() would reject — legacy keys, values
 * saved before a format guard existed — and assert the read-side guards hold.
 *
 * @extends Factory<TenantSetting>
 */
class TenantSettingFactory extends Factory
{
    protected $model = TenantSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'key' => fake()->unique()->slug(2),
            'value' => fake()->word(),
        ];
    }

    /**
     * A specific key/value pair, the common case in a test.
     */
    public function pair(string $key, ?string $value): static
    {
        return $this->state(fn (array $attributes): array => [
            'key' => $key,
            'value' => $value,
        ]);
    }
}
