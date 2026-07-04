<?php

use App\Models\Tenant;
use App\Models\User;

use function Pest\Laravel\actingAs;

afterEach(function () {
    tenancy()->end();
});

/**
 * @return array{0: Tenant, 1: User}
 */
function localeOperatorFor(string $domain, ?string $locale = null): array
{
    $tenant = Tenant::factory()->withDomain($domain)->create();

    $operator = new User;
    $operator->forceFill([
        'tenant_id' => $tenant->id,
        'role' => 'operator',
        'locale' => $locale,
        'name' => 'Operator',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ])->save();

    return [$tenant, $operator];
}

it('renders the panel in Albanian by default (no saved locale)', function () {
    [, $operator] = localeOperatorFor('loc-default');

    actingAs($operator)
        ->get(tenant_url('loc-default', '/dashboard/bookings'))
        ->assertOk()
        ->assertSee('Rezervimet');
});

it('renders the panel in English for an operator with locale=en', function () {
    [, $operator] = localeOperatorFor('loc-en', 'en');

    actingAs($operator)
        ->get(tenant_url('loc-en', '/dashboard/bookings'))
        ->assertOk()
        ->assertSee('Bookings')
        ->assertDontSee('Rezervimet');
});

it('persists the language choice via the toggle route and applies it next request', function () {
    [, $operator] = localeOperatorFor('loc-toggle');

    actingAs($operator)
        ->from(tenant_url('loc-toggle', '/dashboard'))
        ->get(tenant_url('loc-toggle', '/panel-language/en'))
        ->assertRedirect();

    expect($operator->refresh()->locale)->toBe('en');

    actingAs($operator)
        ->get(tenant_url('loc-toggle', '/dashboard/bookings'))
        ->assertSee('Bookings')
        ->assertDontSee('Rezervimet');
});

it('ignores an unsupported locale in the toggle route', function () {
    [, $operator] = localeOperatorFor('loc-bad');

    actingAs($operator)
        ->get(tenant_url('loc-bad', '/panel-language/de'))
        ->assertRedirect();

    expect($operator->refresh()->locale)->toBeNull();
});

it('requires authentication to change the panel language', function () {
    localeOperatorFor('loc-guest');

    $this->get(tenant_url('loc-guest', '/panel-language/en'))
        ->assertRedirect();

    expect(User::query()->whereNotNull('locale')->count())->toBe(0);
});
