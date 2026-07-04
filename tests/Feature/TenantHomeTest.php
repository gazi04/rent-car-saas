<?php

use App\Enums\VehicleStatus;
use App\Models\Tenant;
use App\Models\Vehicle;

afterEach(fn () => tenancy()->end());

// ── Test helpers ────────────────────────────────────────────────────────────

function homeTenant(string $subdomain): Tenant
{
    return Tenant::factory()->withDomain($subdomain)->create();
}

function homeVehicle(array $attrs = []): Vehicle
{
    return Vehicle::factory()->create(array_merge([
        'is_public' => true,
        'status' => VehicleStatus::Available,
        'daily_rate' => 50,
    ], $attrs));
}

// ── Default content ──────────────────────────────────────────────────────────

it('renders the home page with default content when nothing is configured', function () {
    homeTenant('homedef');

    $this->get(tenant_url('homedef', '/'))
        ->assertOk()
        ->assertSee(__('booking.home_hero_heading'))
        ->assertSee(__('booking.home_about_title'))
        ->assertSee(__('booking.home_service_1_title'));
});

// ── Custom content ────────────────────────────────────────────────────────────

it('renders operator-customized hero, services and about content', function () {
    $tenant = homeTenant('homecustom');

    tenancy()->initialize($tenant);
    $tenant->setSetting('home_hero_heading', 'Drive Prishtina in style');
    $tenant->setSetting('home_hero_subheading', 'Custom subheading here');
    $tenant->setSetting('home_about_title', 'Our family business');
    $tenant->setSetting('home_about_text', 'Since 1999 we rent cars.');
    $tenant->setSetting('home_service_1_title', 'Airport pickup');
    tenancy()->end();

    $this->get(tenant_url('homecustom', '/'))
        ->assertOk()
        ->assertSee('Drive Prishtina in style')
        ->assertSee('Custom subheading here')
        ->assertSee('Our family business')
        ->assertSee('Since 1999 we rent cars.')
        ->assertSee('Airport pickup')
        ->assertDontSee(__('booking.home_hero_heading'));
});

// ── Bilingual content ─────────────────────────────────────────────────────────

it('shows the content for the visitor\'s chosen language', function () {
    $tenant = homeTenant('homelang');

    tenancy()->initialize($tenant);
    $tenant->setSetting('home_hero_heading_sq', 'Vozit me stil');
    $tenant->setSetting('home_hero_heading_en', 'Drive in style');
    tenancy()->end();

    // Session locale defaults to sq.
    $this->get(tenant_url('homelang', '/'))
        ->assertOk()
        ->assertSee('Vozit me stil')
        ->assertDontSee('Drive in style');

    $this->withSession(['locale' => 'en'])
        ->get(tenant_url('homelang', '/'))
        ->assertOk()
        ->assertSee('Drive in style')
        ->assertDontSee('Vozit me stil');
});

it('falls back to the other language when only one is filled', function () {
    $tenant = homeTenant('homelangfb');

    tenancy()->initialize($tenant);
    $tenant->setSetting('home_hero_heading_sq', 'Vetëm shqip');
    tenancy()->end();

    // English visitor still sees the Albanian value rather than nothing.
    $this->withSession(['locale' => 'en'])
        ->get(tenant_url('homelangfb', '/'))
        ->assertOk()
        ->assertSee('Vetëm shqip');
});

it('escapes HTML in operator-provided home content', function () {
    $tenant = homeTenant('homexss');

    tenancy()->initialize($tenant);
    $tenant->setSetting('home_hero_heading', '<script>alert("xss")</script>');
    tenancy()->end();

    $this->get(tenant_url('homexss', '/'))
        ->assertOk()
        ->assertDontSee('<script>alert("xss")</script>', false)
        ->assertSee('&lt;script&gt;', false);
});

// ── Featured vehicles ─────────────────────────────────────────────────────────

it('shows only public + available vehicles in the featured section', function () {
    $tenant = homeTenant('homefeat');

    tenancy()->initialize($tenant);
    homeVehicle(['name' => 'Featured Corolla']);
    Vehicle::factory()->private()->create(['name' => 'Hidden Golf']);
    Vehicle::factory()->underMaintenance()->create(['name' => 'Broken Passat']);
    tenancy()->end();

    $this->get(tenant_url('homefeat', '/'))
        ->assertOk()
        ->assertSee('Featured Corolla')
        ->assertDontSee('Hidden Golf')
        ->assertDontSee('Broken Passat');
});

it('does not leak another tenant vehicles into the featured section', function () {
    $tenantA = homeTenant('homea');
    $tenantB = homeTenant('homeb');

    tenancy()->initialize($tenantA);
    homeVehicle(['name' => 'Alpha Featured']);
    tenancy()->end();

    tenancy()->initialize($tenantB);
    homeVehicle(['name' => 'Beta Featured']);
    tenancy()->end();

    $this->get(tenant_url('homea', '/'))
        ->assertOk()
        ->assertSee('Alpha Featured')
        ->assertDontSee('Beta Featured');
});

// ── Layout rendering ──────────────────────────────────────────────────────────

it('renders the home page with every curated layout', function (string $layout) {
    $tenant = homeTenant('homelay'.str_replace('-', '', $layout));

    tenancy()->initialize($tenant);
    $tenant->setSetting('layout_home', $layout);
    homeVehicle(['name' => 'Layout Car']);
    tenancy()->end();

    $this->get(tenant_url('homelay'.str_replace('-', '', $layout), '/'))
        ->assertOk()
        ->assertSee(__('booking.home_hero_heading'));
})->with(['two-column', 'split-screen', 'f-shape', 'z-shape', 'card-block', 'asymmetrical', 'full-screen']);

it('falls back to the default layout when the stored layout value is invalid', function () {
    $tenant = homeTenant('homebadlay');

    tenancy()->initialize($tenant);
    // Bypass the allow-list guard by writing directly to the settings table.
    $tenant->tenantSettings()->create(['key' => 'layout_home', 'value' => '../../etc/passwd']);
    tenancy()->end();

    $this->get(tenant_url('homebadlay', '/'))
        ->assertOk()
        ->assertSee(__('booking.home_hero_heading'));
});
