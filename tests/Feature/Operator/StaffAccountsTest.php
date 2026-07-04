<?php

use App\Enums\PlanFeature;
use App\Filament\Operator\Pages\BrandingSettings;
use App\Filament\Operator\Pages\Reports;
use App\Filament\Operator\Resources\Staff\Pages\CreateStaff;
use App\Filament\Operator\Resources\Staff\Pages\ListStaff;
use App\Filament\Operator\Resources\Staff\StaffResource;
use App\Filament\Operator\Resources\Vehicles\VehicleResource;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

afterEach(fn () => tenancy()->end());

/**
 * Tenant + owner, optionally on a plan with the given features. Initializes
 * tenancy + the operator panel and logs the owner in.
 *
 * @param  array<string, mixed>  $planFeatures
 * @return array{0: Tenant, 1: User}
 */
function staffTenant(string $domain, array $planFeatures = [], ?string $planSlug = null): array
{
    if ($planSlug !== null) {
        Plan::factory()->create(['slug' => $planSlug, 'features' => $planFeatures]);
    }

    $tenant = Tenant::factory()->withDomain($domain)->create(['plan' => $planSlug ?? 'trial']);

    $owner = new User;
    $owner->forceFill([
        'tenant_id' => $tenant->id,
        'role' => 'operator',
        'name' => 'Owner',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ])->save();

    tenancy()->initialize($tenant);
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    actingAs($owner);

    return [$tenant, $owner];
}

function makeStaff(Tenant $tenant): User
{
    return User::factory()->staff()->create([
        'tenant_id' => $tenant->id,
        'email' => fake()->unique()->safeEmail(),
    ]);
}

it('lets the owner create a staff account', function () {
    [$tenant] = staffTenant('staffcreate');

    Livewire::test(CreateStaff::class)
        ->fillForm(['name' => 'Front Desk', 'email' => 'desk@example.com', 'password' => 'secret123'])
        ->call('create')
        ->assertHasNoFormErrors();

    $staff = User::query()->where('email', 'desk@example.com')->first();

    expect($staff)->not->toBeNull()
        ->and($staff->role)->toBe('staff')
        ->and($staff->tenant_id)->toBe($tenant->id)
        ->and($staff->email_verified_at)->not->toBeNull()
        ->and(Hash::check('secret123', $staff->password))->toBeTrue();
});

it('grants a staff account panel access for its own tenant only', function () {
    [$tenant] = staffTenant('staffaccess');
    $staff = makeStaff($tenant);
    $panel = Filament::getPanel('operator');

    expect($staff->canAccessPanel($panel))->toBeTrue();

    // Switch to a different tenant context — the staff user must be locked out.
    tenancy()->end();
    $other = Tenant::factory()->withDomain('otheraccess')->create();
    tenancy()->initialize($other);

    expect($staff->canAccessPanel($panel))->toBeFalse();
});

it('blocks creating staff beyond the plan seat cap', function () {
    [$tenant] = staffTenant('staffcap', [PlanFeature::StaffSeatLimit->value => 2], 'capped');
    makeStaff($tenant);
    makeStaff($tenant);

    Livewire::test(CreateStaff::class)
        ->fillForm(['name' => 'Third', 'email' => 'third@example.com', 'password' => 'secret123'])
        ->call('create');

    expect(User::query()->where('tenant_id', $tenant->id)->where('role', 'staff')->count())->toBe(2);
});

it('allows unlimited staff when the plan sets no cap', function () {
    [$tenant] = staffTenant('staffunlimited');
    makeStaff($tenant);
    makeStaff($tenant);

    Livewire::test(CreateStaff::class)
        ->fillForm(['name' => 'Third', 'email' => 'third2@example.com', 'password' => 'secret123'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('tenant_id', $tenant->id)->where('role', 'staff')->count())->toBe(3);
});

it('scopes the staff list to the tenant and hides the owner', function () {
    [$tenant, $owner] = staffTenant('staffscopeone');
    $mine = makeStaff($tenant);
    $mine->update(['name' => 'My Staff']);
    tenancy()->end();

    [$other] = staffTenant('staffscopetwo');
    $theirs = makeStaff($other);
    $theirs->update(['name' => 'Their Staff']);

    Livewire::test(ListStaff::class)
        ->assertSee('Their Staff')
        ->assertDontSee('My Staff')
        ->assertDontSee($owner->name);
});

it('hides owner-only resources and pages from staff accounts', function () {
    [$tenant] = staffTenant('staffgate');
    $staff = makeStaff($tenant);
    actingAs($staff);

    expect(VehicleResource::canAccess())->toBeFalse()
        ->and(StaffResource::canAccess())->toBeFalse()
        ->and(Reports::canAccess())->toBeFalse()
        ->and(BrandingSettings::canAccess())->toBeFalse();
});

it('keeps owner-only resources visible to the owner', function () {
    staffTenant('staffownergate');

    expect(VehicleResource::canAccess())->toBeTrue()
        ->and(StaffResource::canAccess())->toBeTrue()
        ->and(Reports::canAccess())->toBeTrue()
        ->and(BrandingSettings::canAccess())->toBeTrue();
});

it('404s an owner-only route for a staff account', function () {
    [$tenant] = staffTenant('staffroute');
    $staff = makeStaff($tenant);

    actingAs($staff)
        ->get(tenant_url('staffroute', '/dashboard/vehicles'))
        ->assertNotFound();
});
