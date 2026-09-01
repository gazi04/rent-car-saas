<?php

declare(strict_types=1);

use App\Models\Tenant;

afterEach(fn () => tenancy()->end());

/*
 * The white-label storefront homepage. Most of its visitors are on a phone, so
 * these pin the mobile-layout guards added in the responsiveness pass: the
 * horizontal-scroll clip on <body>, and the sticky-header scroll offset on the
 * #about / #contact anchor targets the storefront nav links point at.
 */

it('renders the storefront homepage for an active tenant with the mobile-layout guards', function () {
    Tenant::factory()->withDomain('ardi')->create();

    $this->get(tenant_url('ardi', '/'))
        ->assertOk()
        ->assertSee('overflow-x-clip', escape: false)
        // #about (partial) and #contact (footer) both clear the sticky h-16 header.
        ->assertSee('id="about" class="max-w-3xl scroll-mt-20"', escape: false)
        ->assertSee('id="contact" class="scroll-mt-20 border-t border-line"', escape: false);
});
