<?php

use App\Ai\Agents\FaqConciergeAgent;
use App\Enums\PlanFeature;
use App\Models\AiUsageLog;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

afterEach(fn () => tenancy()->end());

/**
 * A tenant with a concierge plan feature set, tenancy initialized, an owner
 * acting. Declared in-file: cross-file helpers resolve by load order and break
 * under --filter. The 'ghost-plan' slug (no plans row) exercises the
 * default-OFF path when $planSlug is null.
 *
 * @param  array<string, mixed>  $planFeatures
 */
function conciergeTenant(string $domain, array $planFeatures = [], ?string $planSlug = null, ?string $faqEn = 'Deposit is €200, refunded on return.'): Tenant
{
    if ($planSlug !== null) {
        Plan::factory()->create(['slug' => $planSlug, 'features' => $planFeatures]);
    }

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
    actingAs($owner);

    if ($faqEn !== null) {
        $tenant->setSetting('faq_content_en', $faqEn);
    }

    return $tenant;
}

/**
 * A faked structured response carrying a populated Usage — the default array
 * fake is enough for answer content, but the usage row needs real token counts.
 *
 * @param  array{answer: string, confident: bool}  $structured
 */
function fakeConciergeResponse(array $structured, ?Usage $usage = null): StructuredTextResponse
{
    return new StructuredTextResponse(
        $structured,
        (string) json_encode($structured),
        $usage ?? new Usage(120, 40, 0, 0, 0),
        new Meta('openai', 'gpt-4.1'),
    );
}

// ── Gating (the load-bearing tests bypass the UI and call over the wire) ─────────

it('refuses a direct ask when the plan disables the concierge', function () {
    conciergeTenant('congateoff', [PlanFeature::AiConcierge->value => false], 'congateoffplan');
    FaqConciergeAgent::fake([['answer' => 'nope', 'confident' => true]]);

    Livewire::test('faq-concierge')
        ->set('question', 'What is the deposit?')
        ->call('ask')
        ->assertNotFound();

    FaqConciergeAgent::assertNeverPrompted();
    expect(AiUsageLog::query()->count())->toBe(0);
});

it('refuses a direct ask when the operator wrote no FAQ content', function () {
    // Plan on, but nothing to ground on — no widget, no spend.
    conciergeTenant('connofaq', [PlanFeature::AiConcierge->value => true], 'connofaqplan', faqEn: null);
    FaqConciergeAgent::fake([['answer' => 'nope', 'confident' => true]]);

    Livewire::test('faq-concierge')
        ->set('question', 'What is the deposit?')
        ->call('ask')
        ->assertNotFound();

    FaqConciergeAgent::assertNeverPrompted();
});

it('shows the widget when the plan enables it and FAQ content exists', function () {
    conciergeTenant('conshow', [PlanFeature::AiConcierge->value => true], 'conshowplan');

    Livewire::test('faq-concierge')
        ->assertOk()
        ->assertSee(__('booking.concierge_launcher'));
});

it('hides the widget when the plan disables it', function () {
    conciergeTenant('conhide', [PlanFeature::AiConcierge->value => false], 'conhideplan');

    Livewire::test('faq-concierge')
        ->assertOk()
        ->assertDontSee(__('booking.concierge_launcher'));
});

it('hides the widget when the plan is on but no FAQ content exists', function () {
    conciergeTenant('conhidenofaq', [PlanFeature::AiConcierge->value => true], 'conhidenofaqplan', faqEn: null);

    Livewire::test('faq-concierge')
        ->assertOk()
        ->assertDontSee(__('booking.concierge_launcher'));
});

// ── Answering ────────────────────────────────────────────────────────────────────

it('answers a question from the FAQ and threads it into the conversation', function () {
    conciergeTenant('conanswer', [PlanFeature::AiConcierge->value => true], 'conanswerplan');
    FaqConciergeAgent::fake([['answer' => 'The deposit is €200.', 'confident' => true]]);

    Livewire::test('faq-concierge')
        ->set('question', 'What is the deposit?')
        ->call('ask')
        ->assertSet('question', '')
        ->assertSee('The deposit is €200.');
});

it('carries prior turns into the next prompt (multi-turn)', function () {
    conciergeTenant('conmulti', [PlanFeature::AiConcierge->value => true], 'conmultiplan');
    FaqConciergeAgent::fake([
        ['answer' => 'The deposit is €200.', 'confident' => true],
        ['answer' => 'It is refunded on return.', 'confident' => true],
    ]);

    Livewire::test('faq-concierge')
        ->set('question', 'What is the deposit?')
        ->call('ask')
        ->set('question', 'And when do I get it back?')
        ->call('ask')
        ->assertSee('It is refunded on return.');

    // The second prompt must carry the first exchange as history.
    FaqConciergeAgent::assertPrompted(function ($prompt): bool {
        if ($prompt->prompt !== 'And when do I get it back?') {
            return false;
        }

        $history = collect(iterator_to_array($prompt->agent->messages()));

        return $history->contains(fn ($m): bool => str_contains($m->content, 'What is the deposit?'))
            && $history->contains(fn ($m): bool => str_contains($m->content, 'The deposit is €200.'));
    });
});

// ── Fallback (structural, not model prose) ──────────────────────────────────────

it('substitutes the contact line when the model is not confident', function () {
    $tenant = conciergeTenant('confallback', [PlanFeature::AiConcierge->value => true], 'confallbackplan');
    $tenant->setSetting('contact_phone', '049111222');

    // The model's own answer must be discarded, not shown.
    FaqConciergeAgent::fake([['answer' => 'I think maybe it is 500?', 'confident' => false]]);

    Livewire::test('faq-concierge')
        ->set('question', 'Do you allow pets?')
        ->call('ask')
        ->assertSee('049111222')
        ->assertDontSee('I think maybe it is 500?');
});

it('uses the no-contact fallback when the operator has no contact details', function () {
    conciergeTenant('confallbacknoc', [PlanFeature::AiConcierge->value => true], 'confallbacknocplan');
    FaqConciergeAgent::fake([['answer' => 'unsure', 'confident' => false]]);

    Livewire::test('faq-concierge')
        ->set('question', 'Do you allow pets?')
        ->call('ask')
        ->assertSee(__('booking.concierge_fallback'));
});

// ── Failure ─────────────────────────────────────────────────────────────────────

it('shows a friendly error and stays usable when the AI fails', function () {
    conciergeTenant('confail', [PlanFeature::AiConcierge->value => true], 'confailplan');
    FaqConciergeAgent::fake([fn () => throw new RuntimeException('AI down')]);

    Livewire::test('faq-concierge')
        ->set('question', 'What is the deposit?')
        ->call('ask')
        ->assertSee(__('booking.concierge_error'))
        // Widget still rendered (input still there) — an AI hiccup never removes it.
        ->assertSee(__('booking.concierge_send'));
});

// ── Guardrails ──────────────────────────────────────────────────────────────────

it('returns the fallback, not an error, when the per-IP limit is hit', function () {
    $tenant = conciergeTenant('conip', [PlanFeature::AiConcierge->value => true], 'conipplan');
    $key = 'concierge-ask:'.$tenant->id.':127.0.0.1';
    RateLimiter::clear($key);

    // Pre-fill the IP bucket to the cap so the next ask is over the line, without
    // burning ten real AI calls.
    foreach (range(1, 10) as $ignored) {
        RateLimiter::hit($key, 3600);
    }

    FaqConciergeAgent::fake([['answer' => 'should not run', 'confident' => true]]);

    Livewire::test('faq-concierge')
        ->set('question', 'What is the deposit?')
        ->call('ask')
        ->assertSee(__('booking.concierge_throttled'));

    FaqConciergeAgent::assertNeverPrompted();
});

it('returns the fallback when the per-tenant daily cap is hit', function () {
    config(['ai.concierge_daily_cap' => 3]);
    $tenant = conciergeTenant('contenant', [PlanFeature::AiConcierge->value => true], 'contenantplan');
    $key = 'concierge-tenant:'.$tenant->id;
    RateLimiter::clear($key);

    foreach (range(1, 3) as $ignored) {
        RateLimiter::hit($key, 86400);
    }

    FaqConciergeAgent::fake([['answer' => 'should not run', 'confident' => true]]);

    Livewire::test('faq-concierge')
        ->set('question', 'What is the deposit?')
        ->call('ask')
        ->assertSee(__('booking.concierge_throttled'));

    FaqConciergeAgent::assertNeverPrompted();
});

// ── Usage logging ───────────────────────────────────────────────────────────────

it('records exactly one concierge usage row per answered question', function () {
    conciergeTenant('conusage', [PlanFeature::AiConcierge->value => true], 'conusageplan');
    $tenant = tenant();
    RateLimiter::clear('concierge-ask:'.$tenant->id.':127.0.0.1');
    RateLimiter::clear('concierge-tenant:'.$tenant->id);

    FaqConciergeAgent::fake([fakeConciergeResponse(['answer' => 'The deposit is €200.', 'confident' => true])]);

    Livewire::test('faq-concierge')
        ->set('question', 'What is the deposit?')
        ->call('ask');

    $row = AiUsageLog::query()->sole();
    expect($row->feature)->toBe('concierge')
        ->and($row->tenant_id)->toBe($tenant->id);
});

it('logs no usage row for a gated call', function () {
    conciergeTenant('conusagegate', [PlanFeature::AiConcierge->value => false], 'conusagegateplan');
    FaqConciergeAgent::fake([fakeConciergeResponse(['answer' => 'x', 'confident' => true])]);

    Livewire::test('faq-concierge')
        ->set('question', 'What is the deposit?')
        ->call('ask')
        ->assertNotFound();

    expect(AiUsageLog::query()->count())->toBe(0);
});

// ── i18n ─────────────────────────────────────────────────────────────────────────

it('keeps the English and Albanian public + panel strings in sync', function () {
    foreach (['booking', 'panel', 'help'] as $file) {
        $en = require lang_path("en/{$file}.php");
        $sq = require lang_path("sq/{$file}.php");

        expect(array_keys($sq))->toBe(array_keys($en), "{$file}.php keys diverged");
    }

    $enHelp = require lang_path('en/help.php');
    $sqHelp = require lang_path('sq/help.php');
    expect(array_keys($sqHelp['concierge']))->toBe(array_keys($enHelp['concierge']));
});
