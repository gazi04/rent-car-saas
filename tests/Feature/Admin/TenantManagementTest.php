<?php

use App\Filament\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Resources\Tenants\Pages\ListTenants;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    // Filament resolves the "current panel" from the request host. Tests call
    // Livewire components directly, so set it explicitly.
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('lets a Super Admin reach the admin panel', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin)->get('http://admin.localhost/')->assertOk();
});

it('forbids a non-admin operator from the admin panel', function () {
    $operator = User::factory()->create(['role' => 'operator']);

    actingAs($operator)->get('http://admin.localhost/')->assertForbidden();
});

it('redirects guests to the admin login', function () {
    $this->get('http://admin.localhost/')->assertRedirect('http://admin.localhost/login');
});

it('creates a tenant and its resolvable domain together', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(CreateTenant::class)
        ->fillForm([
            'name' => 'Ardi Rent A Car',
            'email' => 'ardi@example.com',
            'subdomain' => 'ardi',
            'status' => 'active',
            'plan' => 'trial',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas('tenants', ['name' => 'Ardi Rent A Car', 'status' => 'active']);
    assertDatabaseHas('domains', ['domain' => 'ardi.localhost']);
});

it('rejects a duplicate subdomain', function () {
    actingAs(User::factory()->admin()->create());
    Tenant::factory()->withDomain('ardi')->create();

    Livewire::test(CreateTenant::class)
        ->fillForm([
            'name' => 'Another Rental',
            'subdomain' => 'ardi',
            'status' => 'active',
        ])
        ->call('create')
        ->assertHasFormErrors(['subdomain']);
});

it('rejects an invalid subdomain format', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(CreateTenant::class)
        ->fillForm([
            'name' => 'Bad Rental',
            'subdomain' => 'Not Valid!',
            'status' => 'active',
        ])
        ->call('create')
        ->assertHasFormErrors(['subdomain']);
});

it('approves a pending tenant', function () {
    actingAs(User::factory()->admin()->create());
    $tenant = Tenant::factory()->pending()->create();

    Livewire::test(ListTenants::class)
        ->callTableAction('approve', $tenant);

    expect($tenant->refresh()->status)->toBe('active');
});

it('suspends and reactivates a tenant', function () {
    actingAs(User::factory()->admin()->create());
    $tenant = Tenant::factory()->create(['status' => 'active']);

    Livewire::test(ListTenants::class)->callTableAction('suspend', $tenant);
    expect($tenant->refresh()->status)->toBe('suspended');

    Livewire::test(ListTenants::class)->callTableAction('reactivate', $tenant);
    expect($tenant->refresh()->status)->toBe('active');
});

it('rejects a tenant', function () {
    actingAs(User::factory()->admin()->create());
    $tenant = Tenant::factory()->pending()->create();

    Livewire::test(ListTenants::class)->callTableAction('reject', $tenant);

    expect($tenant->refresh()->status)->toBe('cancelled');
});
