<?php

declare(strict_types=1);

use App\Models\Plan;

/*
 * The public SaaS landing page (routes/web.php, central domain only).
 *
 * The pricing table used to hardcode €15/€29/€49 in the Blade markup while the
 * real prices live in the `plans` table and are editable from the admin panel.
 * These tests pin the fix: the page reads live plan data.
 */

function marketingUrl(string $path = '/'): string
{
    return 'http://'.config('tenancy.central_domain').$path;
}

it('renders the marketing page on the central domain', function () {
    $this->get(marketingUrl())
        ->assertOk()
        ->assertSee(__('marketing.hero_heading'))
        ->assertSee(__('marketing.pricing_heading'));
});

it('prices the plan cards from the database, not from the markup', function () {
    Plan::query()->delete();

    Plan::factory()->create([
        'name' => 'Basic', 'slug' => 'basic', 'price' => 15,
        'is_active' => true, 'is_trial' => false, 'sort_order' => 2,
    ]);

    // An admin editing this price must move the public page with it.
    Plan::factory()->create([
        'name' => 'Standard', 'slug' => 'standard', 'price' => 33,
        'is_active' => true, 'is_trial' => false, 'sort_order' => 3,
    ]);

    $this->get(marketingUrl())
        ->assertOk()
        ->assertSee('€33')
        ->assertDontSee('€29');
});

it('omits archived plans from the pricing table', function () {
    Plan::query()->delete();

    Plan::factory()->create([
        'name' => 'Basic', 'slug' => 'basic', 'price' => 15,
        'is_active' => true, 'is_trial' => false, 'sort_order' => 2,
    ]);
    Plan::factory()->create([
        'name' => 'Retired', 'slug' => 'retired', 'price' => 99,
        'is_active' => false, 'is_trial' => false, 'sort_order' => 9,
    ]);

    $this->get(marketingUrl())
        ->assertOk()
        ->assertSee('€15')
        ->assertDontSee('€99');
});

it('shows the free label rather than a zero price for the trial tier', function () {
    Plan::query()->delete();

    Plan::factory()->create([
        'name' => 'Trial', 'slug' => 'trial', 'price' => 0,
        'is_active' => true, 'is_trial' => true, 'sort_order' => 1,
    ]);

    $this->get(marketingUrl())
        ->assertOk()
        ->assertSee(__('marketing.plan_trial_price'))
        ->assertDontSee('€0');
});

it('exposes the nav sections to phones through a no-javascript disclosure', function () {
    // The links were `hidden sm:flex` with no replacement, so Features / How it
    // works / Pricing were unreachable from the header on a phone.
    $this->get(marketingUrl())
        ->assertOk()
        ->assertSee('<details', escape: false)
        ->assertSee('#features', escape: false)
        ->assertSee('#pricing', escape: false);
});

it('loads no third-party assets', function () {
    $html = $this->get(marketingUrl())->assertOk()->getContent();

    expect($html)
        ->not->toContain('fonts.googleapis.com')
        ->not->toContain('fonts.gstatic.com')
        ->not->toContain('cdn.jsdelivr.net');
});
