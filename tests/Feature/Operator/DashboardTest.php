<?php

use App\Enums\BookingStatus;
use App\Filament\Operator\Widgets\NeedsAttentionWidget;
use App\Filament\Operator\Widgets\OperatorStatsOverview;
use App\Filament\Operator\Widgets\TodaysMovementsWidget;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

afterEach(function () {
    tenancy()->end();
});

/**
 * Active tenant + logged-in operator, inside tenant context and the operator panel.
 *
 * @return array{0: Tenant, 1: User}
 */
function dashboardOperator(string $domain): array
{
    $tenant = Tenant::factory()->withDomain($domain)->create();

    $operator = new User;
    $operator->forceFill([
        'tenant_id' => $tenant->id,
        'role' => 'operator',
        'name' => 'Operator',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ])->save();

    tenancy()->initialize($tenant);
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    actingAs($operator);

    return [$tenant, $operator];
}

/**
 * Seed one of each dashboard-relevant booking for the current tenant.
 *
 * @return array{pickup: Booking, return: Booking, pending: Booking, overdue: Booking}
 */
function seedDashboardBookings(): array
{
    $vehicle = Vehicle::factory()->create();

    return [
        'pickup' => Booking::factory()->forVehicle($vehicle)->confirmed()->create([
            'start_date' => now()->startOfDay()->addHours(9),
            'end_date' => now()->addDays(2),
        ]),
        'return' => Booking::factory()->forVehicle($vehicle)->active()->create([
            'start_date' => now()->subDay(),
            'end_date' => now()->endOfDay(),
        ]),
        'pending' => Booking::factory()->forVehicle($vehicle)->create([
            'status' => BookingStatus::Pending,
            'start_date' => now()->addDays(3),
            'end_date' => now()->addDays(5),
        ]),
        'overdue' => Booking::factory()->forVehicle($vehicle)->active()->create([
            'start_date' => now()->subDays(3),
            'end_date' => now()->subDay(),
        ]),
    ];
}

it('computes the four dashboard stats', function () {
    dashboardOperator('statsop');
    seedDashboardBookings();

    $stats = (fn () => $this->getStats())->call(new OperatorStatsOverview);

    expect($stats[0]->getValue())->toBe(1)  // today's pickups
        ->and($stats[1]->getValue())->toBe(1)  // today's returns
        ->and($stats[2]->getValue())->toBe(1)  // awaiting confirmation
        ->and($stats[3]->getValue())->toBe(1); // overdue returns
});

it('lists today’s pickups and returns, excluding future bookings', function () {
    dashboardOperator('movementsop');
    $b = seedDashboardBookings();

    Livewire::test(TodaysMovementsWidget::class)
        ->assertCanSeeTableRecords([$b['pickup'], $b['return']])
        ->assertCanNotSeeTableRecords([$b['pending'], $b['overdue']]);
});

it('lists pending and overdue bookings that need attention', function () {
    dashboardOperator('attentionop');
    $b = seedDashboardBookings();

    Livewire::test(NeedsAttentionWidget::class)
        ->assertCanSeeTableRecords([$b['pending'], $b['overdue']])
        ->assertCanNotSeeTableRecords([$b['pickup'], $b['return']]);
});

it('never shows another tenant’s bookings', function () {
    // First tenant with an overdue booking.
    [$first] = dashboardOperator('tenantone');
    $ownVehicle = Vehicle::factory()->create();
    $ownOverdue = Booking::factory()->forVehicle($ownVehicle)->active()->create([
        'start_date' => now()->subDays(3),
        'end_date' => now()->subDay(),
    ]);
    tenancy()->end();

    // Second tenant with its own overdue booking.
    [$second] = dashboardOperator('tenanttwo');
    $otherVehicle = Vehicle::factory()->create();
    $otherOverdue = Booking::factory()->forVehicle($otherVehicle)->active()->create([
        'start_date' => now()->subDays(3),
        'end_date' => now()->subDay(),
    ]);

    Livewire::test(NeedsAttentionWidget::class)
        ->assertCanSeeTableRecords([$otherOverdue])
        ->assertCanNotSeeTableRecords([$ownOverdue]);
});

it('loads the operator dashboard with the widgets', function () {
    dashboardOperator('smokeop');

    actingAs(User::query()->where('tenant_id', tenant()->id)->first())
        ->get(tenant_url('smokeop', '/dashboard'))
        ->assertOk();
});
