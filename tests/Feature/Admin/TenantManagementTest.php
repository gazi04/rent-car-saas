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
    $adminUrl = 'http://'.config('tenancy.admin_domain').'/';

    actingAs($admin)->get($adminUrl)->assertOk();
});

it('hides the admin panel from a non-admin operator behind a 404', function () {
    $operator = User::factory()->create(['role' => 'operator']);
    $adminUrl = 'http://'.config('tenancy.admin_domain').'/';

    actingAs($operator)->get($adminUrl)->assertNotFound();
});

it('redirects guests to the admin login', function () {
    $adminUrl = 'http://'.config('tenancy.admin_domain').'/';

    $this->get($adminUrl)->assertRedirect($adminUrl.'login');
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
    assertDatabaseHas('domains', ['domain' => 'ardi.'.config('tenancy.tenant_base_domain')]);
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

it('approves a pending tenant and starts its 30-day trial period', function () {
    actingAs(User::factory()->admin()->create());
    $tenant = Tenant::factory()->pending()->create(['paid_until' => null]);

    Livewire::test(ListTenants::class)
        ->callTableAction('approve', $tenant);

    $tenant->refresh();

    expect($tenant->status->value)->toBe('active')
        ->and($tenant->paid_until->toDateString())
        ->toBe(now()->addDays(config('billing.trial_days'))->toDateString());
});

it('does not reset an existing paid period when re-approving', function () {
    actingAs(User::factory()->admin()->create());
    $paidUntil = now()->addDays(90)->startOfSecond();
    $tenant = Tenant::factory()->pending()->create(['paid_until' => $paidUntil]);

    Livewire::test(ListTenants::class)
        ->callTableAction('approve', $tenant);

    expect($tenant->refresh()->paid_until->timestamp)->toBe($paidUntil->timestamp);
});

it('suspends and reactivates a tenant', function () {
    actingAs(User::factory()->admin()->create());
    $tenant = Tenant::factory()->create(['status' => 'active']);

    Livewire::test(ListTenants::class)->callTableAction('suspend', $tenant);
    expect($tenant->refresh()->status->value)->toBe('suspended');

    Livewire::test(ListTenants::class)->callTableAction('reactivate', $tenant);
    expect($tenant->refresh()->status->value)->toBe('active');
});

it('rejects a tenant', function () {
    actingAs(User::factory()->admin()->create());
    $tenant = Tenant::factory()->pending()->create();

    Livewire::test(ListTenants::class)->callTableAction('reject', $tenant);

    expect($tenant->refresh()->status->value)->toBe('cancelled');
});
