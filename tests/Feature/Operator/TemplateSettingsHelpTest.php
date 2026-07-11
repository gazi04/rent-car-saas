<?php

use App\Filament\Operator\Pages\TemplateSettings;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

afterEach(fn () => tenancy()->end());

function templateHelpOperator(string $domain, string $locale = 'en'): User
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

it('shows the templates help modal with the placeholder list', function () {
    templateHelpOperator('tmplhelpen');

    Livewire::test(TemplateSettings::class)
        ->mountAction('help')
        ->assertMountedActionModalSee(__('help.templates.title'))
        ->assertMountedActionModalSee(__('help.templates.body')[0])
        ->assertMountedActionModalSee('{customer_name}')
        ->assertMountedActionModalSee('{pickup_location}');
});

it('renders the help modal in the operator locale', function () {
    templateHelpOperator('tmplhelpsq', 'sq');

    Livewire::test(TemplateSettings::class)
        ->mountAction('help')
        ->assertMountedActionModalSee(__('help.templates.title'));
});
