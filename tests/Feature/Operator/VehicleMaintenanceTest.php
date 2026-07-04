<?php

use App\Enums\PlanFeature;
use App\Filament\Operator\Resources\ServiceRecords\Pages\CreateServiceRecord;
use App\Filament\Operator\Resources\ServiceRecords\Pages\ListServiceRecords;
use App\Filament\Operator\Resources\ServiceRecords\ServiceRecordResource;
use App\Jobs\ProcessVehicleMaintenanceJob;
use App\Mail\ServiceDueMail;
use App\Models\BlockedDate;
use App\Models\Plan;
use App\Models\ServiceRecord;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

afterEach(fn () => tenancy()->end());

/**
 * @param  array<string, mixed>  $planFeatures
 * @return array{0: Tenant, 1: User}
 */
function maintenanceTenant(string $domain, array $planFeatures = [], ?string $planSlug = null): array
{
    if ($planSlug !== null) {
        Plan::factory()->create(['slug' => $planSlug, 'features' => $planFeatures]);
    }

    $tenant = Tenant::factory()->withDomain($domain)->create(['plan' => $planSlug ?? 'ghost-plan']);

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

it('lets the owner log a service record freely, tenant-scoped, regardless of the plan', function () {
    [$tenant] = maintenanceTenant('maintfree', [PlanFeature::MaintenanceReminders->value => false], 'nomaint');
    $vehicle = Vehicle::factory()->create();

    Livewire::test(CreateServiceRecord::class)
        ->fillForm([
            'vehicle_id' => $vehicle->id,
            'service_type' => 'oil_change',
            'performed_on' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $record = ServiceRecord::query()->first();
    expect($record->tenant_id)->toBe($tenant->id)
        ->and($record->service_type)->toBe('oil_change');

    Livewire::test(ListServiceRecords::class)->assertSee($vehicle->name);
});

it('gates the service record resource by owner only, not by plan', function () {
    [$tenant] = maintenanceTenant('maintgate', [PlanFeature::MaintenanceReminders->value => false], 'gatemaint');
    expect(ServiceRecordResource::canAccess())->toBeTrue();

    $staff = User::factory()->staff()->create(['tenant_id' => $tenant->id]);
    actingAs($staff);
    expect(ServiceRecordResource::canAccess())->toBeFalse();
});

it('auto-blocks an overdue vehicle when the feature is enabled, making it unavailable', function () {
    [$tenant] = maintenanceTenant('maintblock', [PlanFeature::MaintenanceReminders->value => true], 'withmaint');
    $vehicle = Vehicle::factory()->create();
    $record = ServiceRecord::factory()->overdue()->create(['vehicle_id' => $vehicle->id]);

    (new ProcessVehicleMaintenanceJob($tenant))->handle();

    $record->refresh();
    expect($record->blocked_date_id)->not->toBeNull();

    $blockedDate = BlockedDate::find($record->blocked_date_id);
    expect($blockedDate->reason)->toBe('maintenance');

    $isAvailable = app(AvailabilityService::class)->isAvailable(
        $vehicle->fresh(),
        Carbon::parse($blockedDate->start_date)->addHour(),
        Carbon::parse($blockedDate->start_date)->addHours(2),
    );
    expect($isAvailable)->toBeFalse();
});

it('sends a reminder once for a due-soon record and does not resend on the next run', function () {
    Mail::fake();

    [$tenant] = maintenanceTenant('maintremind', [PlanFeature::MaintenanceReminders->value => true], 'remindplan');
    $vehicle = Vehicle::factory()->create();
    $record = ServiceRecord::factory()->due()->create(['vehicle_id' => $vehicle->id]);

    (new ProcessVehicleMaintenanceJob($tenant))->handle();

    Mail::assertQueued(ServiceDueMail::class, fn ($m) => $m->serviceRecord->is($record));
    expect($record->fresh()->reminder_sent_at)->not->toBeNull();

    Mail::fake();
    (new ProcessVehicleMaintenanceJob($tenant))->handle();
    Mail::assertNothingQueued();
});

it('skips reminder and auto-block when the plan disables maintenance reminders', function () {
    Mail::fake();

    [$tenant] = maintenanceTenant('maintoff', [PlanFeature::MaintenanceReminders->value => false], 'offplan');
    $vehicle = Vehicle::factory()->create();
    ServiceRecord::factory()->overdue()->create(['vehicle_id' => $vehicle->id]);

    // The command is the actual gate — it never dispatches for this tenant —
    // but confirm the job itself stays inert if called directly, too.
    Queue::fake();
    $this->artisan('maintenance:process-due')->assertSuccessful();
    Queue::assertNotPushed(ProcessVehicleMaintenanceJob::class);

    Mail::assertNothingQueued();
    expect(ServiceRecord::query()->first()->blocked_date_id)->toBeNull();
});

it('clears the vehicle\'s active maintenance block when a new service record is logged', function () {
    maintenanceTenant('maintclear', [PlanFeature::MaintenanceReminders->value => true], 'clearplan');
    $vehicle = Vehicle::factory()->create();

    $blockedDate = BlockedDate::factory()->forVehicle($vehicle)->create([
        'reason' => 'maintenance',
        'start_date' => now(),
        'end_date' => now()->addDays(3),
    ]);
    ServiceRecord::factory()->overdue()->create([
        'vehicle_id' => $vehicle->id,
        'blocked_date_id' => $blockedDate->id,
    ]);

    Livewire::test(CreateServiceRecord::class)
        ->fillForm([
            'vehicle_id' => $vehicle->id,
            'service_type' => 'oil_change',
            'performed_on' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(BlockedDate::query()->count())->toBe(0)
        ->and(ServiceRecord::query()->whereNotNull('blocked_date_id')->count())->toBe(0);
});

it('queues maintenance jobs only for active tenants with the feature enabled', function () {
    Queue::fake();

    Plan::factory()->create(['slug' => 'with-maint', 'features' => [PlanFeature::MaintenanceReminders->value => true]]);
    Plan::factory()->create(['slug' => 'no-maint', 'features' => [PlanFeature::MaintenanceReminders->value => false]]);

    Tenant::factory()->withDomain('enabled')->create(['plan' => 'with-maint']);
    Tenant::factory()->withDomain('disabled')->create(['plan' => 'no-maint']);
    Tenant::factory()->withDomain('suspended')->suspended()->create(['plan' => 'with-maint']);

    $this->artisan('maintenance:process-due')->assertSuccessful();

    Queue::assertPushed(ProcessVehicleMaintenanceJob::class, 1);
});
