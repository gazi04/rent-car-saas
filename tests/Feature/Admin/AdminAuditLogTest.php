<?php

use App\Enums\PaymentMethod;
use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Resources\Tenants\Pages\ListTenants;
use App\Listeners\LogImpersonationStart;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use STS\FilamentImpersonate\Events\EnterImpersonation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('records an audit entry when an admin suspends a tenant', function () {
    $admin = User::factory()->admin()->create();
    actingAs($admin);
    $tenant = Tenant::factory()->create(['status' => 'active']);

    Livewire::test(ListTenants::class)->callTableAction('suspend', $tenant);

    assertDatabaseHas('activity_log', [
        'description' => 'suspended',
        'causer_type' => $admin->getMorphClass(),
        'causer_id' => $admin->id,
        'subject_type' => $tenant->getMorphClass(),
        'subject_id' => $tenant->id,
    ]);
});

it('records the matching audit description for each lifecycle action', function (string $action, string $status, string $description) {
    $admin = User::factory()->admin()->create();
    actingAs($admin);
    $tenant = Tenant::factory()->create(['status' => $status]);

    Livewire::test(ListTenants::class)->callTableAction($action, $tenant);

    assertDatabaseHas('activity_log', [
        'description' => $description,
        'causer_id' => $admin->id,
        'subject_id' => $tenant->id,
    ]);
})->with([
    'approve' => ['approve', 'pending', 'approved'],
    'reactivate' => ['reactivate', 'suspended', 'reactivated'],
    'reject' => ['reject', 'active', 'rejected'],
]);

it('records a recorded_payment entry carrying the amount and note', function () {
    $admin = User::factory()->admin()->create();
    actingAs($admin);
    $tenant = Tenant::factory()->create(['status' => 'active', 'plan' => 'basic']);

    Livewire::test(ListTenants::class)->callTableAction('record_payment', $tenant, data: [
        'plan' => 'basic',
        'method' => PaymentMethod::BankTransfer->value,
        'amount' => 29,
        'period_start' => now()->toDateString(),
        'period_end' => now()->addMonthNoOverflow()->toDateString(),
        'note' => 'bank ref #1234',
    ]);

    $activity = DB::table('activity_log')->where('description', 'recorded_payment')->first();

    expect($activity)->not->toBeNull();
    $properties = json_decode($activity->properties, true);
    expect($properties['amount'])->toEqual(29)
        ->and($properties['note'])->toBe('bank ref #1234')
        ->and($activity->causer_id)->toEqual($admin->id)
        ->and($activity->subject_id)->toBe($tenant->id);
});

it('records an audit entry when an admin starts impersonating an operator', function () {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create();
    $operator = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'operator']);

    (new LogImpersonationStart)->handle(new EnterImpersonation($admin, $operator));

    assertDatabaseHas('activity_log', [
        'description' => 'impersonated',
        'causer_id' => $admin->id,
        'subject_type' => $tenant->getMorphClass(),
        'subject_id' => $tenant->id,
    ]);
});

it('shows the audit log list to a Super Admin', function () {
    $admin = User::factory()->admin()->create();
    actingAs($admin);
    $tenant = Tenant::factory()->create(['status' => 'active']);
    Livewire::test(ListTenants::class)->callTableAction('suspend', $tenant);

    Livewire::test(ListActivities::class)
        ->assertOk()
        ->assertSee('suspended');
});
