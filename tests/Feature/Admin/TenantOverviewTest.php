<?php

use App\Filament\Resources\Tenants\Pages\ViewTenant;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

/**
 * Pulls the rendered value of an infolist TextEntry by its label, anchored to
 * Filament's entry markup — avoids false matches against unrelated digits
 * elsewhere on the page (dates, ids) that a bare assertSee('3') would risk.
 */
function infolistEntryValue(string $html, string $label): string
{
    preg_match('/'.preg_quote($label, '/').'.*?fi-in-text">\s*(.*?)\s*</s', $html, $matches);

    return trim($matches[1] ?? '');
}

it('renders the tenant overview without initializing tenancy', function () {
    actingAs(User::factory()->admin()->create());

    $tenant = Tenant::factory()->withDomain('overviewco')->create();
    User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'operator']);
    $vehicles = Vehicle::factory()->count(3)->create(['tenant_id' => $tenant->id]);
    Booking::factory()->count(2)->create(['tenant_id' => $tenant->id, 'vehicle_id' => $vehicles->first()->id]);
    TenantPayment::factory()->for($tenant)->create();

    $html = Livewire::test(ViewTenant::class, ['record' => $tenant->getKey()])
        ->assertSee($tenant->name)
        ->html();

    expect(infolistEntryValue($html, 'Fleet size'))->toBe('3')
        ->and(infolistEntryValue($html, 'Bookings'))->toBe('2');
});

it('scopes the fleet and bookings counts to the viewed tenant only', function () {
    actingAs(User::factory()->admin()->create());

    $tenant = Tenant::factory()->withDomain('overviewa')->create();
    $other = Tenant::factory()->withDomain('overviewb')->create();

    $vehicle = Vehicle::factory()->create(['tenant_id' => $tenant->id]);
    $otherVehicles = Vehicle::factory()->count(5)->create(['tenant_id' => $other->id]);
    Booking::factory()->count(1)->create(['tenant_id' => $tenant->id, 'vehicle_id' => $vehicle->id]);
    Booking::factory()->count(4)->create(['tenant_id' => $other->id, 'vehicle_id' => $otherVehicles->first()->id]);

    $html = Livewire::test(ViewTenant::class, ['record' => $tenant->getKey()])->html();

    expect(infolistEntryValue($html, 'Fleet size'))->toBe('1')
        ->and(infolistEntryValue($html, 'Bookings'))->toBe('1');
});

it('renders cleanly with no operator and no payment history', function () {
    actingAs(User::factory()->admin()->create());

    $tenant = Tenant::factory()->withDomain('overviewbare')->create();

    Livewire::test(ViewTenant::class, ['record' => $tenant->getKey()])
        ->assertSee('No activity')
        ->assertSee('—');
});

it('blocks a non-admin from reaching the admin panel', function () {
    $operator = User::factory()->create(['role' => 'operator']);
    $adminUrl = 'http://'.config('tenancy.admin_domain').'/';

    actingAs($operator)->get($adminUrl)->assertNotFound();
});
