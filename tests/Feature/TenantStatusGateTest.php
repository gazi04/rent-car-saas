<?php

use App\Models\Tenant;

afterEach(fn () => tenancy()->end());

it('blocks the public home page for a pending tenant', function () {
    $tenant = Tenant::factory()->pending()->withDomain('ardi')->create();

    $this->get(tenant_url('ardi', '/'))
        ->assertForbidden()
        ->assertSee('not live yet')
        ->assertDontSee('Powered by RentACar SaaS');
});

it('blocks the public home page for a suspended tenant', function () {
    $tenant = Tenant::factory()->suspended()->withDomain('ardi')->create();

    $this->get(tenant_url('ardi', '/'))
        ->assertForbidden()
        ->assertSee('temporarily unavailable')
        ->assertDontSee('Powered by RentACar SaaS');
});

it('blocks the public home page for a cancelled tenant', function () {
    $tenant = Tenant::factory()->withDomain('ardi')->create(['status' => 'cancelled']);

    $this->get(tenant_url('ardi', '/'))
        ->assertForbidden()
        ->assertSee('no longer available')
        ->assertDontSee('Powered by RentACar SaaS');
});

it('allows the public home page for an active tenant', function () {
    $tenant = Tenant::factory()->withDomain('ardi')->create(); // active by default

    $this->get(tenant_url('ardi', '/'))
        ->assertOk()
        ->assertSee('Powered by RentACar SaaS');
});
