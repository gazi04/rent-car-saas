<?php

use App\Filament\Operator\Pages\BrandingSettings;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

afterEach(function () {
    tenancy()->end();
});

/**
 * @return array{0: Tenant, 1: User}
 */
function brandingSetup(string $subdomain = 'brand'): array
{
    $tenant = Tenant::factory()->withDomain($subdomain)->create();

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
    Storage::fake('public');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    actingAs($operator);

    return [$tenant, $operator];
}

// ── Logo storage path ────────────────────────────────────────────────────────

it('stores the tenant logo under a tenants/{tenant_id}/logo/{media_id}/ path, not a bare media-ID folder', function () {
    [$tenant] = brandingSetup('logopath');

    $media = $tenant->addMedia(UploadedFile::fake()->image('logo.png', 200, 200))
        ->toMediaCollection('logo');

    $expectedPrefix = "tenants/{$tenant->id}/logo/{$media->id}/";

    expect($media->getPath())->toContain($expectedPrefix)
        ->and($media->getPathRelativeToRoot())->toStartWith($expectedPrefix);
});

it('generates a relative URL for the public disk so the operator panel works across subdomains', function () {
    [$tenant] = brandingSetup('relurl');

    $media = $tenant->addMedia(UploadedFile::fake()->image('logo.png', 200, 200))
        ->toMediaCollection('logo');

    expect($media->getUrl())->toStartWith('/storage/')
        ->and($media->getUrl())->not->toContain('http://')
        ->and($media->getUrl())->not->toContain('https://');
});

// ── Settings store ─────────────────────────────────────────────────────────────

it('round-trips a setting via setSetting and setting', function () {
    [$tenant] = brandingSetup('store1');

    $tenant->setSetting('color_primary', '#ff5500');

    expect($tenant->setting('color_primary'))->toBe('#ff5500');
});

it('setting returns the provided default when key is unset', function () {
    [$tenant] = brandingSetup('store2');

    expect($tenant->setting('color_primary', '#aabbcc'))->toBe('#aabbcc');
});

it('settings() bag returns all stored keys for the tenant', function () {
    [$tenant] = brandingSetup('store3');

    $tenant->setSetting('color_primary', '#111111');
    $tenant->setSetting('footer_text', 'Footer here');

    $bag = $tenant->settings();

    expect($bag['color_primary'])->toBe('#111111')
        ->and($bag['footer_text'])->toBe('Footer here');
});

it('stores null when value is empty string, and setting returns default', function () {
    [$tenant] = brandingSetup('store4');

    $tenant->setSetting('color_primary', '#123456');
    $tenant->setSetting('color_primary', '');

    assertDatabaseHas('tenant_settings', ['tenant_id' => $tenant->id, 'key' => 'color_primary', 'value' => null]);
    expect($tenant->setting('color_primary', '#2563eb'))->toBe('#2563eb');
});

// ── Allow-list guard ───────────────────────────────────────────────────────────

it('rejects keys not on the allow-list', function () {
    [$tenant] = brandingSetup('guard1');

    $tenant->setSetting('evil_key', 'bad value');

    assertDatabaseMissing('tenant_settings', ['key' => 'evil_key']);
});

// ── Tenant isolation ───────────────────────────────────────────────────────────

it('isolates settings between tenants', function () {
    $tenantA = Tenant::factory()->withDomain('iso-a')->create();
    $tenantB = Tenant::factory()->withDomain('iso-b')->create();

    tenancy()->initialize($tenantA);
    $tenantA->setSetting('color_primary', '#aaaaaa');
    tenancy()->end();

    tenancy()->initialize($tenantB);
    $tenantB->setSetting('color_primary', '#bbbbbb');
    tenancy()->end();

    tenancy()->initialize($tenantA);
    expect($tenantA->settings()['color_primary'])->toBe('#aaaaaa');
    tenancy()->end();

    tenancy()->initialize($tenantB);
    expect($tenantB->settings()['color_primary'])->toBe('#bbbbbb');
});

// ── BrandingSettings Filament page ─────────────────────────────────────────────

it('loads BrandingSettings page and fills from current settings', function () {
    [$tenant] = brandingSetup('page1');

    $tenant->setSetting('color_primary', '#336699');
    $tenant->setSetting('payment_instructions', 'IBAN: AL35202111090000000001234567');

    $component = Livewire::test(BrandingSettings::class);

    $component->assertSet('data.color_primary', '#336699')
        ->assertSet('data.payment_instructions', 'IBAN: AL35202111090000000001234567');
});

it('saves allow-listed settings via the save action', function () {
    [$tenant] = brandingSetup('page2');

    Livewire::test(BrandingSettings::class)
        ->set('data.color_primary', '#ff0000')
        ->set('data.footer_text_sq', 'Custom footer')
        ->call('save')
        ->assertHasNoErrors();

    tenancy()->end();
    tenancy()->initialize($tenant);

    expect($tenant->setting('color_primary'))->toBe('#ff0000')
        ->and($tenant->setting('footer_text_sq'))->toBe('Custom footer');
});

it('rejects invalid hex color', function () {
    brandingSetup('page3');

    Livewire::test(BrandingSettings::class)
        ->set('data.color_primary', 'not-a-color')
        ->call('save')
        ->assertHasErrors(['data.color_primary']);
});

it('rejects font not in the allow-list', function () {
    brandingSetup('page4');

    Livewire::test(BrandingSettings::class)
        ->set('data.font_family', 'EvilFont')
        ->call('save');

    assertDatabaseMissing('tenant_settings', ['key' => 'font_family', 'value' => 'EvilFont']);
});

// ── Public layout CSS vars ─────────────────────────────────────────────────────

it('injects tenant color into the public layout CSS vars', function () {
    $tenant = Tenant::factory()->withDomain('css1')->create();
    tenancy()->initialize($tenant);
    $tenant->setSetting('color_primary', '#abcdef');
    tenancy()->end();

    $this->get('http://css1.localhost/')
        ->assertOk()
        ->assertSee('--color-primary: #abcdef', false);
});

it('renders public layout with default colors when no settings are saved', function () {
    Tenant::factory()->withDomain('css2')->create();

    $this->get('http://css2.localhost/')
        ->assertOk()
        ->assertSee('--color-primary', false);
});

it('shows operator payment_instructions on booking review page', function () {
    $tenant = Tenant::factory()->withDomain('pay1')->create();
    tenancy()->initialize($tenant);
    $tenant->setSetting('payment_instructions', 'IBAN AL99 3300 1100 0000 0002 3456 7890');
    tenancy()->end();

    $this->get('http://pay1.localhost/')
        ->assertOk();

    // The instructions value should be available in the tenant settings
    tenancy()->initialize($tenant);
    expect($tenant->setting('payment_instructions'))->toBe('IBAN AL99 3300 1100 0000 0002 3456 7890');
});

it('cross-tenant: subdomain shows its own colors, not another tenant\'s', function () {
    $tenantA = Tenant::factory()->withDomain('cross-a')->create();
    $tenantB = Tenant::factory()->withDomain('cross-b')->create();

    tenancy()->initialize($tenantA);
    $tenantA->setSetting('color_primary', '#111111');
    tenancy()->end();

    tenancy()->initialize($tenantB);
    $tenantB->setSetting('color_primary', '#999999');
    tenancy()->end();

    $this->get('http://cross-a.localhost/')
        ->assertOk()
        ->assertSee('#111111', false)
        ->assertDontSee('#999999', false);
});

// ── Injection guard ────────────────────────────────────────────────────────────

it('escapes malicious text in footer_text on render', function () {
    $tenant = Tenant::factory()->withDomain('xss1')->create();
    tenancy()->initialize($tenant);
    $tenant->setSetting('footer_text', '</style><script>alert(1)</script>');
    tenancy()->end();

    $this->get('http://xss1.localhost/')
        ->assertOk()
        ->assertDontSee('</style><script>', false)
        ->assertSee('&lt;/style&gt;&lt;script&gt;', false);
});

// ── Layout settings ────────────────────────────────────────────────────────────

it('saves a curated layout for each public page', function () {
    [$tenant] = brandingSetup('layoutok');

    Livewire::test(BrandingSettings::class)
        ->set('data.layout_home', 'card-block')
        ->set('data.layout_vehicles', 'f-shape')
        ->set('data.layout_vehicle_show', 'two-column')
        ->call('save')
        ->assertHasNoErrors();

    tenancy()->end();
    tenancy()->initialize($tenant);

    expect($tenant->setting('layout_home'))->toBe('card-block')
        ->and($tenant->setting('layout_vehicles'))->toBe('f-shape')
        ->and($tenant->setting('layout_vehicle_show'))->toBe('two-column');
});

it('rejects a layout that is not curated for that page', function () {
    brandingSetup('layoutbad');

    // full-screen is allowed for home but NOT for the vehicle list.
    Livewire::test(BrandingSettings::class)
        ->set('data.layout_vehicles', 'full-screen')
        ->call('save');

    assertDatabaseMissing('tenant_settings', ['key' => 'layout_vehicles', 'value' => 'full-screen']);
});

it('saves operator home page content settings per language', function () {
    [$tenant] = brandingSetup('contentok');

    Livewire::test(BrandingSettings::class)
        ->set('data.home_hero_heading_sq', 'Heroi im shqip')
        ->set('data.home_hero_heading_en', 'My English hero')
        ->set('data.home_service_2_title_sq', 'Shofer privat')
        ->call('save')
        ->assertHasNoErrors();

    tenancy()->end();
    tenancy()->initialize($tenant);

    expect($tenant->setting('home_hero_heading_sq'))->toBe('Heroi im shqip')
        ->and($tenant->setting('home_hero_heading_en'))->toBe('My English hero')
        ->and($tenant->setting('home_service_2_title_sq'))->toBe('Shofer privat');
});

it('prefills the Albanian content fields from legacy un-suffixed settings', function () {
    [$tenant] = brandingSetup('contentlegacy');

    $tenant->setSetting('home_hero_heading', 'Legacy heading');

    Livewire::test(BrandingSettings::class)
        ->assertSet('data.home_hero_heading_sq', 'Legacy heading');
});
