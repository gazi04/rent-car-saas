<?php

use App\Filament\Resources\Plans\Pages\EditPlan;
use App\Models\Plan;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    actingAs(User::factory()->admin()->create());
});

it('falls back to the TRIAL_SLUG constant when no plan is flagged is_trial', function () {
    Plan::factory()->create(['slug' => 'basic', 'is_trial' => false]);

    expect(Plan::trialSlug())->toBe(Plan::TRIAL_SLUG);
});

it('returns the flagged plan slug once one is marked is_trial', function () {
    Plan::factory()->create(['slug' => 'basic', 'is_trial' => true]);

    expect(Plan::trialSlug())->toBe('basic');
});

it('unflags the previous trial plan when a different plan is flagged', function () {
    $original = Plan::factory()->create(['slug' => 'trial', 'is_trial' => true]);
    $replacement = Plan::factory()->create(['slug' => 'starter', 'is_trial' => false]);

    $replacement->update(['is_trial' => true]);

    expect($original->refresh()->is_trial)->toBeFalse()
        ->and($replacement->refresh()->is_trial)->toBeTrue()
        ->and(Plan::trialSlug())->toBe('starter');
});

it('lets an admin flip the trial flag from the plan edit form, unflagging the prior one', function () {
    $original = Plan::factory()->create(['slug' => 'trial', 'is_trial' => true]);
    $replacement = Plan::factory()->create(['slug' => 'starter', 'is_trial' => false]);

    Livewire::test(EditPlan::class, ['record' => $replacement->getKey()])
        ->fillForm(['is_trial' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($original->refresh()->is_trial)->toBeFalse()
        ->and($replacement->refresh()->is_trial)->toBeTrue();
});
