<?php

use App\Filament\Resources\Tenants\Pages\ListTenants;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    // Livewire tests hit components directly, so pin the admin panel (Filament
    // otherwise resolves it from the request host).
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('lets an admin impersonate a tenant operator and redirects to their dashboard', function () {
    // The action is gated on a shared-parent session cookie; the test env has none.
    config(['session.domain' => '.test']);

    actingAs(User::factory()->admin()->create());

    $tenant = Tenant::factory()->withDomain('ardi')->create();
    $operator = User::factory()->create(['role' => 'operator', 'tenant_id' => $tenant->id]);

    Livewire::test(ListTenants::class)
        ->callTableAction('impersonate', $tenant)
        ->assertRedirect($tenant->publicRootUrl().'/dashboard');

    expect(session()->has('impersonated_by'))->toBeTrue()
        ->and(auth()->id())->toBe($operator->id);
});

it('hides the impersonate action for a tenant with no operator', function () {
    config(['session.domain' => '.test']);

    actingAs(User::factory()->admin()->create());

    $tenant = Tenant::factory()->withDomain('noop')->create();

    Livewire::test(ListTenants::class)
        ->assertTableActionHidden('impersonate', $tenant);
});

it('hides the impersonate action when no shared-parent session domain is set', function () {
    // Host-only cookie (local .localhost dev): impersonation cannot cross
    // subdomains, so the action must not appear even with an operator present.
    config(['session.domain' => null]);

    actingAs(User::factory()->admin()->create());

    $tenant = Tenant::factory()->withDomain('ardi')->create();
    User::factory()->create(['role' => 'operator', 'tenant_id' => $tenant->id]);

    Livewire::test(ListTenants::class)
        ->assertTableActionHidden('impersonate', $tenant);
});

it('only allows admins to impersonate', function () {
    expect(User::factory()->admin()->create()->canImpersonate())->toBeTrue()
        ->and(User::factory()->create(['role' => 'operator'])->canImpersonate())->toBeFalse()
        ->and(User::factory()->staff()->create()->canImpersonate())->toBeFalse();
});
