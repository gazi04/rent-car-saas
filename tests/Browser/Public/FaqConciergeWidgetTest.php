<?php

use App\Ai\Agents\FaqConciergeAgent;
use App\Enums\PlanFeature;
use App\Models\Plan;
use App\Models\Tenant;

afterEach(function (): void {
    tenantHostReset();
    tenancy()->end();
});

/**
 * The storefront concierge widget (resources/views/livewire/faq-concierge.blade.php)
 * is Alpine-driven (x-data open/close, x-ref, optimistic pending bubble) with zero
 * prior browser coverage — tests/Feature/Operator/AiConciergeTest.php exercises the
 * component exclusively via Livewire::test(...), which never opens the panel,
 * never runs the Alpine x-on:click handlers, and never proves the widget is
 * actually reachable/usable by a real visitor.
 */
it('opens the concierge widget and completes a real question/answer round trip', function () {
    Plan::factory()->create([
        'slug' => 'browserconciergeplan',
        'features' => [PlanFeature::AiConcierge->value => true],
    ]);
    $tenant = Tenant::factory()->withDomain('browserconcierge')->create(['plan' => 'browserconciergeplan']);
    tenancy()->initialize($tenant);
    $tenant->setSetting('faq_content_en', 'Deposit is 200 EUR, refunded on return.');

    FaqConciergeAgent::fake([['answer' => 'The deposit is 200 EUR.', 'confident' => true]]);

    $page = visitAsTenant('browserconcierge');

    $page->assertNoJavaScriptErrors()
        ->click('[aria-label="'.__('booking.concierge_launcher').'"]')
        ->assertSee(__('booking.concierge_title'))
        ->fill('[x-ref="question"]', 'What is the deposit?')
        ->click('[aria-label="'.__('booking.concierge_send').'"]')
        ->assertSee('The deposit is 200 EUR.')
        ->assertNoJavaScriptErrors();

    FaqConciergeAgent::assertPrompted(fn ($prompt): bool => $prompt->prompt === 'What is the deposit?');
});
