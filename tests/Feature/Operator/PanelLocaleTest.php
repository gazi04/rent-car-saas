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
        ->get('http://loc-default.localhost/dashboard/bookings')
        ->assertOk()
        ->assertSee('Rezervimet');
});

it('renders the panel in English for an operator with locale=en', function () {
    [, $operator] = localeOperatorFor('loc-en', 'en');

    actingAs($operator)
        ->get('http://loc-en.localhost/dashboard/bookings')
        ->assertOk()
        ->assertSee('Bookings')
        ->assertDontSee('Rezervimet');
});

it('persists the language choice via the toggle route and applies it next request', function () {
    [, $operator] = localeOperatorFor('loc-toggle');

    actingAs($operator)
        ->from('http://loc-toggle.localhost/dashboard')
        ->get('http://loc-toggle.localhost/panel-language/en')
        ->assertRedirect();

    expect($operator->refresh()->locale)->toBe('en');

    actingAs($operator)
        ->get('http://loc-toggle.localhost/dashboard/bookings')
        ->assertSee('Bookings')
        ->assertDontSee('Rezervimet');
});

it('ignores an unsupported locale in the toggle route', function () {
    [, $operator] = localeOperatorFor('loc-bad');

    actingAs($operator)
        ->get('http://loc-bad.localhost/panel-language/de')
        ->assertRedirect();

    expect($operator->refresh()->locale)->toBeNull();
});

it('requires authentication to change the panel language', function () {
    localeOperatorFor('loc-guest');

    $this->get('http://loc-guest.localhost/panel-language/en')
        ->assertRedirect();

    expect(User::query()->whereNotNull('locale')->count())->toBe(0);
});
