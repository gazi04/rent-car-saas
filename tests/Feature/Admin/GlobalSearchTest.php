<?php

use App\Filament\Resources\Plans\PlanResource;
use App\Filament\Resources\Tenants\TenantResource;
use App\Models\Plan;
use App\Models\Tenant;

it('finds a tenant by name, email, and subdomain', function (string $query) {
    $tenant = Tenant::factory()
        ->withDomain('acme')
        ->create(['name' => 'Acme Rentals', 'email' => 'owner@acme-cars.test']);

    $titles = TenantResource::getGlobalSearchResults($query)->pluck('title');

    expect($titles)->toContain($tenant->name);
})->with([
    'by name' => 'Acme',
    'by email' => 'acme-cars',
    'by subdomain' => 'acme.',
]);

it('shows status, plan and subdomain in the tenant result details', function () {
    Tenant::factory()->withDomain('acme')->create(['name' => 'Acme Rentals']);

    $result = TenantResource::getGlobalSearchResults('Acme')->firstOrFail();

    expect($result->details)->toHaveKeys(['Status', 'Plan', 'Subdomain'])
        ->and($result->details['Subdomain'])->toContain('acme.');
});

it('returns no tenant for a non-matching query', function () {
    Tenant::factory()->create(['name' => 'Acme Rentals']);

    expect(TenantResource::getGlobalSearchResults('ZZZ-nothing')->pluck('title'))
        ->not->toContain('Acme Rentals');
});

it('finds a plan by name', function () {
    $plan = Plan::factory()->create(['name' => 'Gold Tier']);

    $titles = PlanResource::getGlobalSearchResults('Gold')->pluck('title');

    expect($titles)->toContain($plan->name);
});
