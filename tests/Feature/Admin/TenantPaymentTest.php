<?php

use App\Filament\Resources\Tenants\Pages\ListTenants;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->admin = User::factory()->admin()->create();
    actingAs($this->admin);
});

/** @return array<string, mixed> */
function paymentData(array $overrides = []): array
{
    return array_merge([
        'plan' => 'basic',
        'method' => 'bank_transfer',
        'amount' => 15,
        'period_start' => '2030-01-01',
        'period_end' => '2030-02-01',
        'note' => 'transfer ref #123',
    ], $overrides);
}

it('records a payment, advances paid_until, and stores the audit row', function () {
    $tenant = Tenant::factory()->create();

    Livewire::test(ListTenants::class)
        ->callTableAction('record_payment', $tenant, data: paymentData())
        ->assertHasNoTableActionErrors();

    $tenant->refresh();
    $payment = TenantPayment::sole();

    expect($tenant->paid_until->toDateString())->toBe('2030-02-01')
        ->and($tenant->plan)->toBe('basic')
        ->and($payment->tenant_id)->toBe($tenant->id)
        ->and($payment->recorded_by)->toBe($this->admin->id)
        ->and($payment->period_start->toDateString())->toBe('2030-01-01')
        ->and((float) $payment->amount)->toBe(15.0);
});

it('defaults the next period to stack onto a future paid_until', function () {
    $tenant = Tenant::factory()->create(['paid_until' => now()->addDays(10)]);

    Livewire::test(ListTenants::class)
        ->mountTableAction('record_payment', $tenant)
        ->assertTableActionDataSet([
            'period_start' => $tenant->paid_until->toDateString(),
        ]);
});

it('defaults the next period to start from today when the previous period lapsed', function () {
    $tenant = Tenant::factory()->create(['paid_until' => now()->subDays(3)]);

    Livewire::test(ListTenants::class)
        ->mountTableAction('record_payment', $tenant)
        ->assertTableActionDataSet([
            'period_start' => now()->toDateString(),
        ]);
});

it('reactivates a suspended tenant when a payment is recorded', function () {
    $tenant = Tenant::factory()->suspended()->create(['paid_until' => now()->subDays(20)]);

    Livewire::test(ListTenants::class)
        ->callTableAction('record_payment', $tenant, data: paymentData())
        ->assertHasNoTableActionErrors();

    expect($tenant->refresh()->status->value)->toBe('active');
});

it('updates the tenant plan when a different plan is paid for', function () {
    $tenant = Tenant::factory()->create(['plan' => 'basic']);

    Livewire::test(ListTenants::class)
        ->callTableAction('record_payment', $tenant, data: paymentData(['plan' => 'pro', 'amount' => 49]))
        ->assertHasNoTableActionErrors();

    expect($tenant->refresh()->plan)->toBe('pro');
});

it('rejects a period end before the period start', function () {
    $tenant = Tenant::factory()->create();

    Livewire::test(ListTenants::class)
        ->callTableAction('record_payment', $tenant, data: paymentData([
            'period_start' => '2030-02-01',
            'period_end' => '2030-01-01',
        ]))
        ->assertHasTableActionErrors(['period_end']);

    expect(TenantPayment::count())->toBe(0);
});

it('hides the record-payment action for pending and cancelled tenants', function () {
    $pending = Tenant::factory()->pending()->create();

    Livewire::test(ListTenants::class)
        ->assertTableActionHidden('record_payment', $pending);
});
