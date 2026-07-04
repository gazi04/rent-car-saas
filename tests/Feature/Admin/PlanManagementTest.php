<?php

use App\Enums\PlanFeature;
use App\Filament\Resources\Plans\Pages\CreatePlan;
use App\Filament\Resources\Plans\Pages\ListPlans;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    actingAs(User::factory()->admin()->create());
});

it('creates a plan with feature toggles and limits', function () {
    Livewire::test(CreatePlan::class)
        ->fillForm([
            'name' => 'Starter',
            'slug' => 'starter',
            'price' => 9,
            'description' => 'Small fleets',
            'is_active' => true,
            'features' => [
                PlanFeature::VehicleLimit->value => 3,
                PlanFeature::PhotosPerVehicle->value => 4,
                PlanFeature::Reports->value => false,
                PlanFeature::Branding->value => true,
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $plan = Plan::query()->where('slug', 'starter')->firstOrFail();

    expect($plan->limit(PlanFeature::VehicleLimit))->toBe(3)
        ->and($plan->limit(PlanFeature::PhotosPerVehicle))->toBe(4)
        ->and($plan->allows(PlanFeature::Reports))->toBeFalse()
        ->and($plan->allows(PlanFeature::Branding))->toBeTrue();
});

it('rejects a duplicate slug', function () {
    Plan::factory()->create(['slug' => 'taken']);

    Livewire::test(CreatePlan::class)
        ->fillForm(['name' => 'Taken Again', 'slug' => 'taken', 'price' => 5])
        ->call('create')
        ->assertHasFormErrors(['slug']);
});

it('treats missing feature keys as permissive defaults', function () {
    $plan = Plan::factory()->create(['features' => []]);

    expect($plan->limit(PlanFeature::VehicleLimit))->toBeNull()
        ->and($plan->allows(PlanFeature::Reports))->toBeTrue()
        ->and($plan->allows(PlanFeature::Branding))->toBeTrue();
});

it('excludes archived plans from pickers but keeps a tenant\'s current archived plan', function () {
    Plan::factory()->create(['slug' => 'live', 'name' => 'Live', 'is_active' => true]);
    Plan::factory()->archived()->create(['slug' => 'legacy', 'name' => 'Legacy']);

    expect(Plan::options())->toHaveKey('live')
        ->and(Plan::options())->not->toHaveKey('legacy')
        ->and(Plan::options('legacy'))->toHaveKey('legacy');
});

it('falls back to the historical tiers when no plans are seeded', function () {
    expect(Plan::options())->toBe([
        'trial' => 'Trial',
        'basic' => 'Basic',
        'standard' => 'Standard',
        'pro' => 'Pro',
    ]);
});

it('hides the delete action while tenants reference the plan', function () {
    $plan = Plan::factory()->create(['slug' => 'inuse']);
    Tenant::factory()->create(['plan' => 'inuse']);

    Livewire::test(ListPlans::class)
        ->assertTableActionHidden('delete', $plan);

    $free = Plan::factory()->create(['slug' => 'unused']);

    Livewire::test(ListPlans::class)
        ->callTableAction('delete', $free);

    expect(Plan::query()->where('slug', 'unused')->exists())->toBeFalse()
        ->and(Plan::query()->where('slug', 'inuse')->exists())->toBeTrue();
});
