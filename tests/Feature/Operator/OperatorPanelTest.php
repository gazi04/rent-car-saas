<?php

use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

afterEach(function () {
    // Tenancy may be initialized by the panel middleware during a request; reset it.
    tenancy()->end();
});

/**
 * Create an operator user belonging to the given tenant.
 */
function operatorFor(Tenant $tenant): User
{
    $user = new User;
    $user->forceFill([
        'tenant_id' => $tenant->id,
        'role' => 'operator',
        'name' => 'Operator',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ])->save();

    return $user;
}

it('registers an operator as a pending tenant with a domain and user', function () {
    Livewire::test('pages::auth.operator-register')
        ->set('name', 'Ardi Rent A Car')
        ->set('email', 'ardi@example.com')
        ->set('phone', '+38344123456')
        ->set('subdomain', 'ardi')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertHasNoErrors()
        ->assertSet('registered', true);

    assertDatabaseHas('tenants', ['name' => 'Ardi Rent A Car', 'status' => 'pending', 'plan' => 'trial']);
    assertDatabaseHas('domains', ['domain' => 'ardi.localhost']);
    assertDatabaseHas('users', ['email' => 'ardi@example.com', 'role' => 'operator']);
});

it('rejects a reserved subdomain on registration', function () {
    Livewire::test('pages::auth.operator-register')
        ->set('name', 'Bad Co')
        ->set('email', 'bad@example.com')
        ->set('subdomain', 'admin')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertHasErrors(['subdomain']);
});

it('rejects a duplicate subdomain on registration', function () {
    Tenant::factory()->withDomain('ardi')->create();

    Livewire::test('pages::auth.operator-register')
        ->set('name', 'Second Co')
        ->set('email', 'second@example.com')
        ->set('subdomain', 'ardi')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertHasErrors(['subdomain']);
});

it('lets an approved operator reach the panel on their subdomain', function () {
    $tenant = Tenant::factory()->withDomain('ardi')->create(); // active
    $operator = operatorFor($tenant);

    actingAs($operator)->get('http://ardi.localhost/dashboard')->assertOk();
});

it('gates a pending tenant with an under-review message', function () {
    $tenant = Tenant::factory()->pending()->withDomain('ardi')->create();
    $operator = operatorFor($tenant);

    actingAs($operator)->get('http://ardi.localhost/dashboard')
        ->assertOk()
        ->assertSee('under review');
});

it('gates a suspended tenant with a support message', function () {
    $tenant = Tenant::factory()->suspended()->withDomain('ardi')->create();
    $operator = operatorFor($tenant);

    actingAs($operator)->get('http://ardi.localhost/dashboard')
        ->assertOk()
        ->assertSee('suspended');
});

it('blocks an operator from another tenant\'s subdomain', function () {
    $tenantA = Tenant::factory()->withDomain('ardi')->create();
    $tenantB = Tenant::factory()->withDomain('bardh')->create();
    $operatorA = operatorFor($tenantA);

    actingAs($operatorA)->get('http://bardh.localhost/dashboard')->assertForbidden();
});

it('redirects an unauthenticated visitor to the operator login', function () {
    Tenant::factory()->withDomain('ardi')->create();

    $this->get('http://ardi.localhost/dashboard')
        ->assertRedirect('http://ardi.localhost/dashboard/login');
});

it('makes the shared Livewire update route tenancy-aware', function () {
    // The operator panel logs in via a Livewire AJAX call to the global update
    // endpoint. Without tenancy middleware on that route, tenancy is uninitialized
    // during the request and the operator panel access gate rejects the login.
    $route = app('router')->getRoutes()->getByName('livewire.update');

    expect($route)->not->toBeNull();

    $middleware = $route->gatherMiddleware();

    expect($middleware)->toContain('universal')
        ->and($middleware)->toContain(InitializeTenancyByDomain::class);
});
