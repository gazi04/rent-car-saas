<?php

use App\Filament\Operator\Pages\BrandingSettings;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

afterEach(fn () => tenancy()->end());

function brandingHelpOperator(string $domain, string $locale = 'en'): User
{
    $tenant = Tenant::factory()->withDomain($domain)->create(['plan' => 'ghost-plan']);

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

it('shows the branding help modal', function () {
    brandingHelpOperator('brandhelpen');

    Livewire::test(BrandingSettings::class)
        ->mountAction('help')
        ->assertMountedActionModalSee(__('help.branding.title'))
        ->assertMountedActionModalSee(__('help.branding.body')[0]);
});

it('renders the branding help modal in the operator locale', function () {
    brandingHelpOperator('brandhelpsq', 'sq');

    Livewire::test(BrandingSettings::class)
        ->mountAction('help')
        ->assertMountedActionModalSee(__('help.branding.title'));
});
