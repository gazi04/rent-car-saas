<?php

use App\Filament\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Resources\Tenants\Pages\ListTenants;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

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

// ── Purging abandoned signups (frees the subdomain) ──────────────────────────

it('purges an abandoned signup and frees its subdomain', function () {
    actingAs(User::factory()->admin()->create());

    $tenant = Tenant::factory()->pending()->withDomain('ghostco')->create([
        'created_at' => now()->subDays(60),
    ]);
    User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'operator']);

    Livewire::test(ListTenants::class)->callTableAction('purge', $tenant);

    assertDatabaseMissing('tenants', ['id' => $tenant->id]);
    assertDatabaseMissing('domains', ['tenant_id' => $tenant->id]);

    // The whole point: the subdomain is re-registerable afterwards.
    expect(Tenant::factory()->withDomain('ghostco')->create())->toBeInstanceOf(Tenant::class);
});

it('records who purged which subdomain before the row disappears', function () {
    $admin = User::factory()->admin()->create();
    actingAs($admin);

    $tenant = Tenant::factory()->pending()->withDomain('audited')->create([
        'created_at' => now()->subDays(60),
    ]);

    Livewire::test(ListTenants::class)->callTableAction('purge', $tenant);

    $activity = Activity::query()->where('description', 'purged')->sole();

    expect($activity->causer_id)->toBe($admin->id)
        ->and($activity->properties->get('domains'))->toContain(tenant_domain('audited'));
});

it('hides the purge action for tenants that are active, recent, or have bookings', function () {
    actingAs(User::factory()->admin()->create());

    $active = Tenant::factory()->create([
        'status' => 'active',
        'created_at' => now()->subDays(60),
    ]);

    $recent = Tenant::factory()->pending()->create(['created_at' => now()->subDays(2)]);

    $withBookings = Tenant::factory()->pending()->create(['created_at' => now()->subDays(60)]);
    tenancy()->initialize($withBookings);
    Booking::factory()->create(['vehicle_id' => Vehicle::factory()->create()->id]);
    tenancy()->end();

    Livewire::test(ListTenants::class)
        ->assertTableActionHidden('purge', $active)
        ->assertTableActionHidden('purge', $recent)
        ->assertTableActionHidden('purge', $withBookings);
});
