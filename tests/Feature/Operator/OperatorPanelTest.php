<?php

use App\Http\Middleware\ResolveFilamentPanelForSharedRoutes;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
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
function operatorFor(Tenant $tenant, bool $verified = true): User
{
    $user = new User;
    $user->forceFill([
        'tenant_id' => $tenant->id,
        'role' => 'operator',
        'name' => 'Operator',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
        'email_verified_at' => $verified ? now() : null,
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
    assertDatabaseHas('domains', ['domain' => tenant_domain('ardi')]);
    assertDatabaseHas('users', ['email' => 'ardi@example.com', 'role' => 'operator']);
});

it('sends a verification email on registration and leaves the account unverified', function () {
    Notification::fake();

    Livewire::test('pages::auth.operator-register')
        ->set('name', 'Ardi Rent A Car')
        ->set('email', 'ardi@example.com')
        ->set('subdomain', 'ardi')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertHasNoErrors();

    $operator = User::where('email', 'ardi@example.com')->firstOrFail();

    expect($operator->email_verified_at)->toBeNull();
    Notification::assertSentTo($operator, VerifyEmail::class);
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

it('throttles repeated registration attempts from the same visitor', function () {
    RateLimiter::clear('operator-register:127.0.0.1');

    foreach (range(0, 2) as $i) {
        Livewire::test('pages::auth.operator-register')
            ->set('name', "Business {$i}")
            ->set('email', "owner{$i}@example.com")
            ->set('subdomain', "biz{$i}")
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register')
            ->assertHasNoErrors();
    }

    Livewire::test('pages::auth.operator-register')
        ->set('name', 'One More Business')
        ->set('email', 'owner3@example.com')
        ->set('subdomain', 'biz3')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertHasErrors(['email']);

    expect(Tenant::count())->toBe(3);
});

it('lets an approved operator reach the panel on their subdomain', function () {
    $tenant = Tenant::factory()->withDomain('ardi')->create(); // active
    $operator = operatorFor($tenant);

    actingAs($operator)->get(tenant_url('ardi', '/dashboard'))->assertOk();
});

it('gates a pending tenant with an under-review message', function () {
    $tenant = Tenant::factory()->pending()->withDomain('ardi')->create();
    $operator = operatorFor($tenant);

    actingAs($operator)->get(tenant_url('ardi', '/dashboard'))
        ->assertForbidden()
        ->assertSee('under review');
});

it('gates a suspended tenant with a support message', function () {
    $tenant = Tenant::factory()->suspended()->withDomain('ardi')->create();
    $operator = operatorFor($tenant);

    actingAs($operator)->get(tenant_url('ardi', '/dashboard'))
        ->assertForbidden()
        ->assertSee('suspended');
});

it('blocks an operator with an unverified email even on an approved tenant', function () {
    $tenant = Tenant::factory()->withDomain('ardi')->create(); // active
    $operator = operatorFor($tenant, verified: false);

    actingAs($operator)->get(tenant_url('ardi', '/dashboard'))->assertNotFound();

    $operator->markEmailAsVerified();

    actingAs($operator)->get(tenant_url('ardi', '/dashboard'))->assertOk();
});

it('hides another tenant\'s dashboard behind a 404', function () {
    $tenantA = Tenant::factory()->withDomain('ardi')->create();
    $tenantB = Tenant::factory()->withDomain('bardh')->create();
    $operatorA = operatorFor($tenantA);

    actingAs($operatorA)->get(tenant_url('bardh', '/dashboard'))->assertNotFound();
});

it('redirects an unauthenticated visitor to the operator login', function () {
    Tenant::factory()->withDomain('ardi')->create();

    $this->get(tenant_url('ardi', '/dashboard'))
        ->assertRedirect(tenant_url('ardi', '/dashboard/login'));
});

it('makes the shared Livewire update route tenancy-aware', function () {
    // The operator panel logs in via a Livewire AJAX call to the global update
    // endpoint. Without tenancy middleware on that route, tenancy is uninitialized
    // during the request and the operator panel access gate rejects the login.
    $route = app('router')->getRoutes()->getByName('livewire.update');

    expect($route)->not->toBeNull();

    $middleware = $route->gatherMiddleware();

    expect($middleware)->toContain('universal')
        ->and($middleware)->toContain(InitializeTenancyByDomain::class)
        ->and($middleware)->toContain(ResolveFilamentPanelForSharedRoutes::class);
});

it('resolves the operator panel, not the default admin panel, for the shared Livewire update route on a tenant subdomain', function () {
    $tenant = Tenant::factory()->withDomain('ardi')->create();
    tenancy()->initialize($tenant);

    (new ResolveFilamentPanelForSharedRoutes)->handle(request(), fn () => new Response);

    expect(Filament::getCurrentPanel()->getId())->toBe('operator');
});

it('resolves the admin panel for the shared Livewire update route on the central domain', function () {
    (new ResolveFilamentPanelForSharedRoutes)->handle(request(), fn () => new Response);

    expect(Filament::getCurrentPanel()->getId())->toBe('admin');
});
