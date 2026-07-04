<?php

use App\Enums\PlanFeature;
use App\Filament\Resources\Tenants\Pages\ListTenants;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->admin = User::factory()->admin()->create();
    actingAs($this->admin);
});

it('extends the paid period by N days, stacking onto a future paid_until', function () {
    $tenant = Tenant::factory()->create(['status' => 'active', 'paid_until' => now()->addDays(10)]);
    $expected = $tenant->paid_until->copy()->addDays(5)->endOfDay();

    Livewire::test(ListTenants::class)
        ->callTableAction('extend_period', $tenant, data: ['mode' => 'add_days', 'days' => 5, 'note' => 'goodwill credit'])
        ->assertHasNoTableActionErrors();

    expect($tenant->refresh()->paid_until->toDateString())->toBe($expected->toDateString());
});

it('extends the paid period to an explicit date via set_date', function () {
    $tenant = Tenant::factory()->create(['status' => 'active']);

    Livewire::test(ListTenants::class)
        ->callTableAction('extend_period', $tenant, data: ['mode' => 'set_date', 'paid_until' => '2030-06-01'])
        ->assertHasNoTableActionErrors();

    expect($tenant->refresh()->paid_until->toDateString())->toBe('2030-06-01');
});

it('reactivates a suspended tenant when its period is extended', function () {
    $tenant = Tenant::factory()->suspended()->create(['paid_until' => now()->subDays(5)]);

    Livewire::test(ListTenants::class)
        ->callTableAction('extend_period', $tenant, data: ['mode' => 'add_days', 'days' => 30])
        ->assertHasNoTableActionErrors();

    expect($tenant->refresh()->status)->toBe('active');
});

it('records an extended_period audit entry with no payment row', function () {
    $tenant = Tenant::factory()->create(['status' => 'active']);

    Livewire::test(ListTenants::class)
        ->callTableAction('extend_period', $tenant, data: ['mode' => 'add_days', 'days' => 7, 'note' => 'comp']);

    assertDatabaseHas('activity_log', [
        'description' => 'extended_period',
        'causer_id' => $this->admin->id,
        'subject_id' => $tenant->id,
    ]);
    assertDatabaseCount('tenant_payments', 0);
});

it('changes the tenant plan and immediately reflects the new plan features', function () {
    Plan::factory()->create(['slug' => 'basic', 'features' => [PlanFeature::PromoCodes->value => false]]);
    Plan::factory()->create(['slug' => 'pro', 'features' => [PlanFeature::PromoCodes->value => true]]);
    $tenant = Tenant::factory()->create(['status' => 'active', 'plan' => 'basic']);

    Livewire::test(ListTenants::class)
        ->callTableAction('change_plan', $tenant, data: ['plan' => 'pro', 'note' => 'upgraded manually'])
        ->assertHasNoTableActionErrors();

    $tenant->refresh();

    expect($tenant->plan)->toBe('pro')
        ->and($tenant->allowsFeature(PlanFeature::PromoCodes))->toBeTrue();
});

it('records a changed_plan audit entry with from/to and no payment row', function () {
    Plan::factory()->create(['slug' => 'basic']);
    Plan::factory()->create(['slug' => 'pro']);
    $tenant = Tenant::factory()->create(['status' => 'active', 'plan' => 'basic']);

    Livewire::test(ListTenants::class)
        ->callTableAction('change_plan', $tenant, data: ['plan' => 'pro']);

    assertDatabaseHas('activity_log', [
        'description' => 'changed_plan',
        'causer_id' => $this->admin->id,
        'subject_id' => $tenant->id,
    ]);
    assertDatabaseCount('tenant_payments', 0);
});

it('extends a trial by N days, bumping both trial_ends_at and paid_until', function () {
    $tenant = Tenant::factory()->create([
        'status' => 'active',
        'plan' => 'trial',
        'trial_ends_at' => now()->addDays(10),
        'paid_until' => now()->addDays(10),
    ]);
    $expectedTrial = $tenant->trial_ends_at->copy()->addDays(5)->toDateString();
    $expectedPaid = $tenant->paid_until->copy()->addDays(5)->endOfDay()->toDateString();

    Livewire::test(ListTenants::class)
        ->callTableAction('extend_trial', $tenant, data: ['days' => 5, 'note' => 'still deciding'])
        ->assertHasNoTableActionErrors();

    $tenant->refresh();

    expect($tenant->trial_ends_at->toDateString())->toBe($expectedTrial)
        ->and($tenant->paid_until->toDateString())->toBe($expectedPaid);
});

it('hides the extend-trial action for non-trial tenants', function () {
    $tenant = Tenant::factory()->create(['status' => 'active', 'plan' => 'basic']);

    Livewire::test(ListTenants::class)
        ->assertTableActionHidden('extend_trial', $tenant);
});

it('records an extended_trial audit entry with no payment row', function () {
    $tenant = Tenant::factory()->create(['status' => 'active', 'plan' => 'trial']);

    Livewire::test(ListTenants::class)
        ->callTableAction('extend_trial', $tenant, data: ['days' => 14]);

    assertDatabaseHas('activity_log', [
        'description' => 'extended_trial',
        'causer_id' => $this->admin->id,
        'subject_id' => $tenant->id,
    ]);
    assertDatabaseCount('tenant_payments', 0);
});

it('hides extend_period and change_plan for pending and cancelled tenants', function () {
    $pending = Tenant::factory()->pending()->create();

    Livewire::test(ListTenants::class)
        ->assertTableActionHidden('extend_period', $pending)
        ->assertTableActionHidden('change_plan', $pending);
});
