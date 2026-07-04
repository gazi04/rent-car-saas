<?php

use App\Filament\Widgets\AtRiskTenants;
use App\Filament\Widgets\TenantStats;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    actingAs(User::factory()->admin()->create());
});

// ── TenantStats: revenue this month ──────────────────────────────────────────

it('sums only the current month payments in the revenue stat', function () {
    $tenant = Tenant::factory()->create();

    TenantPayment::factory()->for($tenant)->create(['amount' => 150, 'created_at' => now()]);
    TenantPayment::factory()->for($tenant)->create(['amount' => 100, 'created_at' => now()->startOfMonth()->addDay()]);
    // Last month — excluded.
    TenantPayment::factory()->for($tenant)->create(['amount' => 999, 'created_at' => now()->subMonthNoOverflow()->startOfMonth()]);

    Livewire::test(TenantStats::class)
        ->assertSee('Revenue this month')
        ->assertSee('€250.00')
        ->assertDontSee('€999.00');
});

it('shows zero revenue when no payments exist this month', function () {
    Livewire::test(TenantStats::class)
        ->assertSee('€0.00');
});

// ── AtRiskTenants table ──────────────────────────────────────────────────────

it('lists active tenants due within the grace window', function () {
    $dueSoon = Tenant::factory()->create(['status' => 'active', 'paid_until' => now()->addDays(3)]);
    $inGrace = Tenant::factory()->create(['status' => 'active', 'paid_until' => now()->subDays(2)]);
    $safe = Tenant::factory()->create(['status' => 'active', 'paid_until' => now()->addDays(30)]);
    $suspended = Tenant::factory()->suspended()->create(['paid_until' => now()->subDays(2)]);
    $unenrolled = Tenant::factory()->create(['status' => 'active', 'paid_until' => null]);

    Livewire::test(AtRiskTenants::class)
        ->assertCanSeeTableRecords([$dueSoon, $inGrace])
        ->assertCanNotSeeTableRecords([$safe, $suspended, $unenrolled]);
});

it('labels lapsed tenants as Grace and upcoming ones as Due soon', function () {
    Tenant::factory()->create(['status' => 'active', 'paid_until' => now()->subDay(), 'name' => 'Lapsed Co']);
    Tenant::factory()->create(['status' => 'active', 'paid_until' => now()->addDays(2), 'name' => 'Upcoming Co']);

    Livewire::test(AtRiskTenants::class)
        ->assertSee('Grace')
        ->assertSee('Due soon');
});

it('shows the empty state when nothing is at risk', function () {
    Tenant::factory()->create(['status' => 'active', 'paid_until' => now()->addDays(30)]);

    Livewire::test(AtRiskTenants::class)
        ->assertSee('No subscriptions need attention');
});
