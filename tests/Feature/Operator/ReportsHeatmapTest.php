<?php

use App\Enums\BookingStatus;
use App\Enums\PlanFeature;
use App\Filament\Operator\Pages\Reports;
use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

// reportsOperatorFor() and reportRange() are global helpers in tests/Pest.php.

afterEach(function () {
    tenancy()->end();
});

/** The heatmap payload for the standard June-2030 range. */
function heatmapFor(array $range = []): array
{
    return Livewire::test(Reports::class)
        ->fillForm($range === [] ? reportRange() : $range)
        ->instance()
        ->heatmap();
}

/** The cell for one vehicle row on one Y-m-d, or null if that day isn't a column. */
function heatmapCell(array $heatmap, string $vehicleName, string $date): ?array
{
    $column = array_search($date, array_column($heatmap['days'], 'date'), true);
    $row = collect($heatmap['rows'])->firstWhere('vehicle', $vehicleName);

    return $column === false || $row === null ? null : $row['cells'][$column];
}

it('counts calendar days touched, diverging from the utilisation table on purpose', function () {
    [, , $vehicle] = reportsOperatorFor('heatmap-days');

    // Out 09:00 Jun 4 -> 09:00 Jun 10. That is 144h duration (= 6 days by
    // ceil(hours/24)) but it touches 7 calendar days: Jun 4,5,6,7,8,9,10.
    Booking::factory()->forVehicle($vehicle)->confirmed()->create([
        'start_date' => '2030-06-04 09:00', 'end_date' => '2030-06-10 09:00',
    ]);

    $page = Livewire::test(Reports::class)->fillForm(reportRange())->instance();

    $heatmapRow = collect($page->heatmap()['rows'])->firstWhere('vehicle', $vehicle->name);
    $tableRow = $page->utilisation()->firstWhere('vehicle', $vehicle->name);

    // Both numbers are correct for their own question. This test pins the
    // disagreement so it can't be silently "fixed" into a single measure.
    expect($heatmapRow['occupied_days'])->toBe(7)
        ->and($tableRow['booked_days'])->toBe(6);
});

it('marks a day the vehicle is returned at midnight, matching the public availability endpoint', function () {
    [, , $vehicle] = reportsOperatorFor('heatmap-midnight');

    Booking::factory()->forVehicle($vehicle)->confirmed()->create([
        'start_date' => '2030-06-08 09:00', 'end_date' => '2030-06-10 00:00',
    ]);

    $heatmap = heatmapFor();

    // Deliberate one-day overcount: the customer-facing calendar blocks Jun 10
    // too, and agreeing with it beats theoretical precision.
    expect(heatmapCell($heatmap, $vehicle->name, '2030-06-10')['kind'])->toBe('booking');
});

it('clamps a booking that starts before the range to the range', function () {
    [, , $vehicle] = reportsOperatorFor('heatmap-clamp');

    Booking::factory()->forVehicle($vehicle)->confirmed()->create([
        'start_date' => '2030-05-28', 'end_date' => '2030-06-03',
    ]);

    $heatmap = heatmapFor();

    expect($heatmap['days'])->toHaveCount(30)
        ->and(heatmapCell($heatmap, $vehicle->name, '2030-06-01')['kind'])->toBe('booking')
        ->and(heatmapCell($heatmap, $vehicle->name, '2030-06-03')['kind'])->toBe('booking')
        ->and(heatmapCell($heatmap, $vehicle->name, '2030-06-04')['kind'])->toBe('free');
});

it('renders a blocked date distinctly from a booking', function () {
    [, , $vehicle] = reportsOperatorFor('heatmap-block');

    BlockedDate::factory()->create([
        'vehicle_id' => $vehicle->id,
        'start_date' => '2030-06-15',
        'end_date' => '2030-06-16',
        'reason' => 'maintenance',
    ]);

    $cell = heatmapCell(heatmapFor(), $vehicle->name, '2030-06-15');

    expect($cell['kind'])->toBe('block')
        ->and($cell['color'])->toBe('#9ca3af');
});

it('lets a booking win over a block on the same day', function () {
    [, , $vehicle] = reportsOperatorFor('heatmap-precedence');

    BlockedDate::factory()->create([
        'vehicle_id' => $vehicle->id,
        'start_date' => '2030-06-12',
        'end_date' => '2030-06-14',
    ]);
    Booking::factory()->forVehicle($vehicle)->completed()->create([
        'start_date' => '2030-06-13 09:00', 'end_date' => '2030-06-13 18:00',
    ]);

    $heatmap = heatmapFor();

    expect(heatmapCell($heatmap, $vehicle->name, '2030-06-13')['kind'])->toBe('booking')
        ->and(heatmapCell($heatmap, $vehicle->name, '2030-06-12')['kind'])->toBe('block');
});

it('gives a shared day to the most committed booking', function () {
    [, , $vehicle] = reportsOperatorFor('heatmap-turnover');

    // Same-day turnover: one car handed back and re-let the same day.
    Booking::factory()->forVehicle($vehicle)->create([
        'status' => 'pending', 'start_date' => '2030-06-20 14:00', 'end_date' => '2030-06-22 10:00',
    ]);
    Booking::factory()->forVehicle($vehicle)->create([
        'status' => 'active', 'start_date' => '2030-06-18 09:00', 'end_date' => '2030-06-20 12:00',
    ]);

    $cell = heatmapCell(heatmapFor(), $vehicle->name, '2030-06-20');

    expect($cell['color'])->toBe(BookingStatus::Active->calendarColor());
});

it('includes completed bookings so past ranges are not blank', function () {
    [, , $vehicle] = reportsOperatorFor('heatmap-completed');

    Booking::factory()->forVehicle($vehicle)->completed()->create([
        'start_date' => '2030-06-05', 'end_date' => '2030-06-07',
    ]);

    $heatmap = heatmapFor();

    expect(heatmapCell($heatmap, $vehicle->name, '2030-06-06')['kind'])->toBe('booking')
        ->and(collect($heatmap['rows'])->firstWhere('vehicle', $vehicle->name)['occupied_days'])->toBeGreaterThan(0);
});

it('excludes cancelled bookings', function () {
    [, , $vehicle] = reportsOperatorFor('heatmap-cancelled');

    Booking::factory()->forVehicle($vehicle)->create([
        'status' => 'cancelled', 'start_date' => '2030-06-05', 'end_date' => '2030-06-10',
    ]);

    $row = collect(heatmapFor()['rows'])->firstWhere('vehicle', $vehicle->name);

    expect($row['occupied_days'])->toBe(0)
        ->and(collect($row['cells'])->every(fn ($cell) => $cell['kind'] === 'free'))->toBeTrue();
});

it('aggregates fleet demand per day', function () {
    [, , $first] = reportsOperatorFor('heatmap-demand');
    $second = Vehicle::factory()->create(['daily_rate' => 50]);
    Vehicle::factory()->create(['daily_rate' => 50]);

    Booking::factory()->forVehicle($first)->confirmed()->create([
        'start_date' => '2030-06-05 09:00', 'end_date' => '2030-06-05 18:00',
    ]);
    Booking::factory()->forVehicle($second)->confirmed()->create([
        'start_date' => '2030-06-05 09:00', 'end_date' => '2030-06-05 18:00',
    ]);

    $heatmap = heatmapFor();
    $day = collect($heatmap['demand'])->firstWhere('date', '2030-06-05');

    // 2 of 3 vehicles out.
    expect($heatmap['fleet_size'])->toBe(3)
        ->and($day['occupied'])->toBe(2)
        ->and($day['percent'])->toBe(67);
});

it('truncates a range longer than the column cap', function () {
    reportsOperatorFor('heatmap-cap');

    $heatmap = heatmapFor(['start_date' => '2030-06-01', 'end_date' => '2030-07-10']);

    expect($heatmap['truncated'])->toBeTrue()
        ->and($heatmap['days'])->toBe([])
        ->and($heatmap['rows'])->toBe([])
        ->and($heatmap['demand'])->toBe([]);
});

it('renders a range exactly at the column cap', function () {
    reportsOperatorFor('heatmap-cap-edge');

    $heatmap = heatmapFor(['start_date' => '2030-07-01', 'end_date' => '2030-07-31']);

    expect($heatmap['truncated'])->toBeFalse()
        ->and($heatmap['days'])->toHaveCount(Reports::MAX_HEATMAP_DAYS);
});

it('never shows another tenant\'s bookings', function () {
    [, , $vehicle] = reportsOperatorFor('heatmap-mine');
    tenancy()->end();

    $other = Tenant::factory()->withDomain('heatmap-other')->create();
    tenancy()->initialize($other);
    Booking::factory()
        ->forVehicle(Vehicle::factory()->create(['name' => 'Other Tenant Car']))
        ->confirmed()
        ->create(['start_date' => '2030-06-05', 'end_date' => '2030-06-10']);
    tenancy()->end();

    $mine = Tenant::query()->whereRelation('domains', 'domain', tenant_domain('heatmap-mine'))->firstOrFail();
    tenancy()->initialize($mine);
    Filament::setCurrentPanel(Filament::getPanel('operator'));

    $heatmap = heatmapFor();

    expect($heatmap['fleet_size'])->toBe(1)
        ->and(collect($heatmap['rows'])->pluck('vehicle')->all())->toBe([$vehicle->name])
        ->and(collect($heatmap['demand'])->every(fn ($day) => $day['occupied'] === 0))->toBeTrue();
});

it('is hidden from staff and from plans without reports', function () {
    [$tenant] = reportsOperatorFor('heatmap-gate');

    // The heatmap has its own PlanFeature::FleetHeatmap toggle, but it lives on
    // the Reports page — so the page's gate still takes the whole thing away.
    $staff = new User;
    $staff->forceFill([
        'tenant_id' => $tenant->id,
        'role' => 'staff',
        'name' => 'Staff',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ])->save();
    actingAs($staff);

    expect(Reports::canAccess())->toBeFalse();

    $plan = Plan::factory()->create([
        'slug' => 'heatmap-basic',
        'features' => [PlanFeature::Reports->value => false],
    ]);
    $tenant->update(['plan' => $plan->slug]);

    $operator = User::query()->where('tenant_id', $tenant->id)->where('role', 'operator')->firstOrFail();
    actingAs($operator);

    expect(Reports::canAccess())->toBeFalse();
});

it('handles an empty fleet and a fleet with no bookings', function () {
    [, , $vehicle] = reportsOperatorFor('heatmap-empty');

    $heatmap = heatmapFor();

    expect($heatmap['rows'])->toHaveCount(1)
        ->and(collect($heatmap['rows'][0]['cells'])->every(fn ($cell) => $cell['kind'] === 'free'))->toBeTrue()
        ->and(collect($heatmap['demand'])->every(fn ($day) => $day['percent'] === 0))->toBeTrue();

    $vehicle->forceDelete();

    expect(heatmapFor()['rows'])->toBe([]);
});

it('only ever emits bare Y-m-d dates to the view', function () {
    [, , $vehicle] = reportsOperatorFor('heatmap-dates');

    Booking::factory()->forVehicle($vehicle)->confirmed()->create([
        'start_date' => '2030-06-05 09:00', 'end_date' => '2030-06-07 09:00',
    ]);

    $heatmap = heatmapFor();

    // Regression guard for the b22a4c9 class of bug: an ISO-8601 "...Z" datetime
    // gets re-anchored to the viewer's local day and shifts the grid a column
    // west of UTC. Day bucketing must stay server-side, in bare Y-m-d.
    foreach ($heatmap['days'] as $day) {
        expect($day['date'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    }

    foreach ($heatmap['demand'] as $day) {
        expect($day['date'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    }
});

it('drops only the heatmap when the plan disables it, keeping the rest of Reports', function () {
    [$tenant, , $vehicle] = reportsOperatorFor('heatmap-plan-off');

    Booking::factory()->forVehicle($vehicle)->completed()->create([
        'start_date' => '2030-06-05', 'end_date' => '2030-06-07', 'total' => 250,
    ]);

    $plan = Plan::factory()->create([
        'slug' => 'heatmap-off-plan',
        'features' => [
            PlanFeature::Reports->value => true,
            PlanFeature::FleetHeatmap->value => false,
        ],
    ]);
    $tenant->update(['plan' => $plan->slug]);

    expect(Reports::canAccess())->toBeTrue()
        ->and(Livewire::test(Reports::class)->instance()->showsHeatmap())->toBeFalse();

    // The regression that matters: the page still works, minus the heatmap.
    Livewire::test(Reports::class)
        ->fillForm(reportRange())
        ->assertOk()
        ->assertSee(__('reports.utilisation'))
        ->assertSee($vehicle->name)
        ->assertDontSee(__('reports.heatmap'))
        ->assertDontSee(__('reports.heatmap_demand'));
});

it('shows the heatmap when the plan enables it', function () {
    [$tenant, , $vehicle] = reportsOperatorFor('heatmap-plan-on');

    $plan = Plan::factory()->create([
        'slug' => 'heatmap-on-plan',
        'features' => [
            PlanFeature::Reports->value => true,
            PlanFeature::FleetHeatmap->value => true,
        ],
    ]);
    $tenant->update(['plan' => $plan->slug]);

    Livewire::test(Reports::class)
        ->fillForm(reportRange())
        ->assertOk()
        ->assertSee(__('reports.heatmap'))
        ->assertSee($vehicle->name);
});

it('keeps the heatmap for a tenant whose plan slug has no plan row', function () {
    reportsOperatorFor('heatmap-no-plan');

    // Permissive default — an unseeded/legacy plan slug must not silently strip
    // a feature the tenant already had.
    expect(Livewire::test(Reports::class)->instance()->showsHeatmap())->toBeTrue();
});

it('renders the grid, the legend and the demand row', function () {
    [, , $vehicle] = reportsOperatorFor('heatmap-render');

    Booking::factory()->forVehicle($vehicle)->confirmed()->create([
        'start_date' => '2030-06-05 09:00', 'end_date' => '2030-06-07 09:00',
    ]);
    BlockedDate::factory()->create([
        'vehicle_id' => $vehicle->id,
        'start_date' => '2030-06-15',
        'end_date' => '2030-06-16',
    ]);

    // The other cases assert heatmap() in isolation; this one exercises the
    // blade, so a broken template can't pass unnoticed.
    Livewire::test(Reports::class)
        ->fillForm(reportRange())
        ->assertOk()
        ->assertSee(__('reports.heatmap'))
        ->assertSee(__('reports.heatmap_demand'))
        ->assertSee(__('panel.legend_blocked'))
        ->assertSee(__('reports.legend_completed'))
        ->assertSee($vehicle->name)
        ->assertSee('heatmap-cell-blocked', escape: false)
        ->assertSee(BookingStatus::Confirmed->calendarColor(), escape: false);
});

it('shows the too-long notice instead of the grid for a wide range', function () {
    reportsOperatorFor('heatmap-render-cap');

    Livewire::test(Reports::class)
        ->fillForm(['start_date' => '2030-06-01', 'end_date' => '2030-07-10'])
        ->assertOk()
        ->assertSee(__('reports.heatmap_range_too_long'))
        ->assertDontSee(__('reports.heatmap_demand'));
});

it('keeps the English and Albanian reports translations in sync', function () {
    $en = require lang_path('en/reports.php');
    $sq = require lang_path('sq/reports.php');

    expect(array_keys($sq))->toBe(array_keys($en));
});
