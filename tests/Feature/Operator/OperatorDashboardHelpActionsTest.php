<?php

use App\Enums\PlanFeature;
use App\Filament\Operator\Pages\Reports;
use App\Filament\Operator\Resources\Bookings\Pages\ListBookings;
use App\Filament\Operator\Resources\Customers\Pages\ListCustomers;
use App\Filament\Operator\Resources\PromoCodes\Pages\ListPromoCodes;
use App\Filament\Operator\Resources\Reviews\Pages\ListReviews;
use App\Filament\Operator\Resources\ServiceRecords\Pages\ListServiceRecords;
use App\Filament\Operator\Resources\Staff\Pages\ListStaff;
use App\Filament\Operator\Resources\Vehicles\Pages\ListVehicles;
use App\Filament\Operator\Widgets\AvailabilityCalendar;
use App\Filament\Operator\Widgets\BusinessSummaryWidget;
use App\Filament\Operator\Widgets\NeedsAttentionWidget;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

afterEach(fn () => tenancy()->end());

function dashboardHelpOperator(string $domain, string $locale = 'en', ?string $planSlug = null): User
{
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
    app()->setLocale($locale);
    actingAs($owner);

    return $owner;
}

it('shows the reports help modal', function () {
    dashboardHelpOperator('reportshelpen');

    Livewire::test(Reports::class)
        ->mountAction('help')
        ->assertMountedActionModalSee(__('help.reports.title'))
        ->assertMountedActionModalSee(__('help.reports.body')[0]);
});

it('shows the promo codes help modal', function () {
    dashboardHelpOperator('promohelpen');

    Livewire::test(ListPromoCodes::class)
        ->mountAction('help')
        ->assertMountedActionModalSee(__('help.promo_codes.title'))
        ->assertMountedActionModalSee(__('help.promo_codes.body')[0]);
});

it('shows the service records help modal', function () {
    dashboardHelpOperator('servicehelpen');

    Livewire::test(ListServiceRecords::class)
        ->mountAction('help')
        ->assertMountedActionModalSee(__('help.service_records.title'))
        ->assertMountedActionModalSee(__('help.service_records.body')[0]);
});

it('shows the vehicles help modal', function () {
    dashboardHelpOperator('vehicleshelpen');

    Livewire::test(ListVehicles::class)
        ->mountAction('help')
        ->assertMountedActionModalSee(__('help.vehicles.title'))
        ->assertMountedActionModalSee(__('help.vehicles.body')[0]);
});

it('shows the staff help modal', function () {
    dashboardHelpOperator('staffhelpen');

    Livewire::test(ListStaff::class)
        ->mountAction('help')
        ->assertMountedActionModalSee(__('help.staff.title'))
        ->assertMountedActionModalSee(__('help.staff.body')[0]);
});

it('shows the customers help modal', function () {
    dashboardHelpOperator('customershelpen');

    Livewire::test(ListCustomers::class)
        ->mountAction('help')
        ->assertMountedActionModalSee(__('help.customers.title'))
        ->assertMountedActionModalSee(__('help.customers.body')[0]);
});

it('shows the reviews help modal', function () {
    dashboardHelpOperator('reviewshelpen');

    Livewire::test(ListReviews::class)
        ->mountAction('help')
        ->assertMountedActionModalSee(__('help.reviews.title'))
        ->assertMountedActionModalSee(__('help.reviews.body')[0]);
});

it('shows the bookings help modal', function () {
    dashboardHelpOperator('bookingshelpen');

    Livewire::test(ListBookings::class)
        ->mountAction('help')
        ->assertMountedActionModalSee(__('help.bookings.title'))
        ->assertMountedActionModalSee(__('help.bookings.body')[0]);
});

it('renders the bookings help modal in the operator locale', function () {
    dashboardHelpOperator('bookingshelpsq', 'sq');

    Livewire::test(ListBookings::class)
        ->mountAction('help')
        ->assertMountedActionModalSee(__('help.bookings.title'));
});

it('shows the availability calendar help modal', function () {
    dashboardHelpOperator('calendarhelpen');

    Livewire::test(AvailabilityCalendar::class)
        ->mountAction('help')
        ->assertMountedActionModalSee(__('help.availability_calendar.title'))
        ->assertMountedActionModalSee(__('help.availability_calendar.body')[0]);
});

it('shows the needs attention help modal', function () {
    dashboardHelpOperator('attentionhelpen');

    Livewire::test(NeedsAttentionWidget::class)
        ->mountAction(TestAction::make('help')->table())
        ->assertMountedActionModalSee(__('help.needs_attention.title'))
        ->assertMountedActionModalSee(__('help.needs_attention.body')[0]);
});

it('shows the business summary help modal', function () {
    Plan::factory()->create([
        'slug' => 'aisummaryplan',
        'features' => [PlanFeature::AiBusinessSummary->value => true],
    ]);
    dashboardHelpOperator('summaryhelpen', 'en', 'aisummaryplan');

    Livewire::test(BusinessSummaryWidget::class)
        ->mountAction('help')
        ->assertMountedActionModalSee(__('help.business_summary.title'))
        ->assertMountedActionModalSee(__('help.business_summary.body')[0]);
});
