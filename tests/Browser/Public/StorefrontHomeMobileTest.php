<?php

use App\Enums\VehicleStatus;
use App\Models\Tenant;
use App\Models\Vehicle;

afterEach(function (): void {
    tenantHostReset();
    tenancy()->end();
});

/*
 * The storefront homepage is the page most operator customers hit, and mostly on
 * a phone. An HTTP test asserts markup but not layout; this drives a real
 * Chromium at 320px and 375px and fails if the page can pan sideways — the
 * regression the responsiveness pass fixed (unclamped operator logo, oversized
 * hero headline, operator free-text with no break-words).
 */
it('storefront home has no horizontal overflow on a small phone', function () {
    $tenant = Tenant::factory()->withDomain('mobilehome')->create();
    tenancy()->initialize($tenant);

    Vehicle::factory()->create([
        'name' => 'Volkswagen Golf',
        'is_public' => true,
        'status' => VehicleStatus::Available,
        'daily_rate' => 40,
    ]);

    $page = visitAsTenant('mobilehome');

    foreach ([320, 375] as $width) {
        $page->resize($width, 720)->assertNoJavascriptErrors();

        $overflow = $page->script('document.documentElement.scrollWidth - document.documentElement.clientWidth');
        expect($overflow)->toBeLessThanOrEqual(1, "at {$width}px the storefront home overflows horizontally by {$overflow}px");
    }
});
