<?php

use App\Enums\PlanFeature;
use App\Exceptions\AiRequestFailedException;
use App\Filament\Operator\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\Operator\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\Operator\Widgets\BusinessSummaryWidget;
use App\Jobs\GenerateBusinessSummaryJob;
use App\Models\AiBusinessSummary;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Ai\AiChatService;
use App\Services\Ai\BusinessSummaryGenerator;
use Filament\Facades\Filament;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

use function Pest\Laravel\actingAs;

afterEach(function () {
    tenancy()->end();
});

/**
 * Active tenant (with a plan whose features are $planFeatures) + a logged-in
 * operator, inside tenant context and the operator panel.
 *
 * @param  array<string, mixed>  $planFeatures
 * @return array{0: Tenant, 1: User}
 */
function aiOperator(string $domain, array $planFeatures = [], string $role = 'operator'): array
{
    Plan::factory()->create(['slug' => 'ai-plan', 'features' => $planFeatures]);

    $tenant = Tenant::factory()->withDomain($domain)->create(['plan' => 'ai-plan']);

    $operator = new User;
    $operator->forceFill([
        'tenant_id' => $tenant->id,
        'role' => $role,
        'name' => 'Operator',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ])->save();

    tenancy()->initialize($tenant);
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    actingAs($operator);

    return [$tenant, $operator];
}

/** Fake a single chat completion returning $content. */
function fakeChat(string $content): void
{
    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => $content]],
            ],
        ]),
    ]);
}

it('hides the listing writer action without the plan feature', function () {
    aiOperator('nolisting');
    $vehicle = Vehicle::factory()->create();

    Livewire::test(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
        ->assertFormComponentActionDoesNotExist('description', 'generateDescription');
});

it('shows the listing writer action with the plan feature', function () {
    aiOperator('haslisting', [PlanFeature::AiListingWriter->value => true]);
    $vehicle = Vehicle::factory()->create();

    Livewire::test(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
        ->assertFormComponentActionVisible('description', 'generateDescription');
});

it('fills the description from the AI response', function () {
    aiOperator('fills', [PlanFeature::AiListingWriter->value => true]);
    $vehicle = Vehicle::factory()->create();

    fakeChat(json_encode(['description' => 'A crisp, reliable ride for city trips.']));

    Livewire::test(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
        ->callFormComponentAction('description', 'generateDescription')
        ->assertFormSet(['description' => 'A crisp, reliable ride for city trips.']);
});

it('applies the suggested daily rate on confirm and hides pricing on create', function () {
    aiOperator('pricing', [PlanFeature::AiPricingSuggestions->value => true]);
    $vehicle = Vehicle::factory()->create(['daily_rate' => 40]);

    fakeChat(json_encode(['suggested_daily_rate' => 57.5, 'reasoning' => 'Strong recent demand.']));

    Livewire::test(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
        ->callFormComponentAction('daily_rate', 'suggestPrice')
        ->assertFormSet(['daily_rate' => 57.5]);

    // The action is only offered once the vehicle exists — absent on create.
    Livewire::test(CreateVehicle::class)
        ->assertFormComponentActionDoesNotExist('daily_rate', 'suggestPrice');
});

it('hides the summary widget without the plan feature', function () {
    aiOperator('nowidget');

    expect(BusinessSummaryWidget::canView())->toBeFalse();
});

it('generates and renders a tenant-scoped summary from the widget', function () {
    [$tenant] = aiOperator('haswidget', [PlanFeature::AiBusinessSummary->value => true]);

    fakeChat('Bookings held steady this week; two returns are overdue.');

    Livewire::test(BusinessSummaryWidget::class)
        ->call('generate');

    $summary = AiBusinessSummary::query()->first();

    expect($summary)->not->toBeNull()
        ->and($summary->tenant_id)->toBe($tenant->id)
        ->and($summary->content)->toBe('Bookings held steady this week; two returns are overdue.');
});

it('hides the summary widget from staff even with the plan feature', function () {
    aiOperator('staffwidget', [PlanFeature::AiBusinessSummary->value => true], role: 'staff');

    expect(BusinessSummaryWidget::canView())->toBeFalse();
});

it('blocks staff from calling generate directly, bypassing canView', function () {
    aiOperator('staffgenerate', [PlanFeature::AiBusinessSummary->value => true], role: 'staff');

    fakeChat('Should never be reached.');

    Livewire::test(BusinessSummaryWidget::class)
        ->call('generate');

    expect(AiBusinessSummary::query()->count())->toBe(0);
});

it('wraps SDK and JSON failures in AiRequestFailedException', function () {
    // Empty/malformed content triggers the malformedResponse path.
    fakeChat('');

    expect(fn () => app(AiChatService::class)->chat([['role' => 'user', 'content' => 'hi']]))
        ->toThrow(AiRequestFailedException::class);
});

it('gives asymmetric plan defaults: toggles on, AI off', function () {
    $plan = Plan::factory()->create(['features' => []]);

    expect($plan->allows(PlanFeature::Reports))->toBeTrue()
        ->and($plan->allows(PlanFeature::Branding))->toBeTrue()
        ->and($plan->allows(PlanFeature::PromoCodes))->toBeTrue()
        ->and($plan->allows(PlanFeature::Templates))->toBeTrue()
        ->and($plan->allows(PlanFeature::Reviews))->toBeTrue()
        ->and($plan->allows(PlanFeature::AiListingWriter))->toBeFalse()
        ->and($plan->allows(PlanFeature::AiBusinessSummary))->toBeFalse()
        ->and($plan->allows(PlanFeature::AiPricingSuggestions))->toBeFalse();
});

it('queues summary jobs only for active tenants with the feature enabled', function () {
    Queue::fake();

    Plan::factory()->create(['slug' => 'with-ai', 'features' => [PlanFeature::AiBusinessSummary->value => true]]);
    Plan::factory()->create(['slug' => 'no-ai', 'features' => []]);

    Tenant::factory()->withDomain('enabled')->create(['plan' => 'with-ai']);
    Tenant::factory()->withDomain('disabled')->create(['plan' => 'no-ai']);
    Tenant::factory()->withDomain('suspended')->suspended()->create(['plan' => 'with-ai']);

    $this->artisan('ai:generate-business-summaries')->assertSuccessful();

    Queue::assertPushed(GenerateBusinessSummaryJob::class, 1);
});

it('creates a tenant-scoped summary when the job runs directly', function () {
    [$tenant] = aiOperator('jobruns', [PlanFeature::AiBusinessSummary->value => true]);

    fakeChat('Steady week overall.');

    (new GenerateBusinessSummaryJob($tenant))->handle(app(BusinessSummaryGenerator::class));

    $summary = AiBusinessSummary::query()->first();

    expect($summary)->not->toBeNull()
        ->and($summary->tenant_id)->toBe($tenant->id)
        ->and($summary->content)->toBe('Steady week overall.');
});

it('regenerating within the same period updates the existing row instead of duplicating it', function () {
    aiOperator('regenerate', [PlanFeature::AiBusinessSummary->value => true]);

    fakeChat('First pass.');
    Livewire::test(BusinessSummaryWidget::class)->call('generate');

    fakeChat('Second pass, same window.');
    Livewire::test(BusinessSummaryWidget::class)->call('generate');

    expect(AiBusinessSummary::query()->count())->toBe(1)
        ->and(AiBusinessSummary::query()->first()->content)->toBe('Second pass, same window.');
});

it('rejects a duplicate summary row for the same tenant and period at the database level', function () {
    aiOperator('dupewindow', [PlanFeature::AiBusinessSummary->value => true]);

    AiBusinessSummary::factory()->create();

    expect(fn () => AiBusinessSummary::factory()->create())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('configures retries and timeout for transient AI failures', function () {
    $job = new GenerateBusinessSummaryJob(Tenant::factory()->make());

    expect($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(60)
        ->and($job->backoff())->toBe([60, 300, 900]);
});

it('logs tenant context when the job fails permanently', function () {
    [$tenant] = aiOperator('jobfailslog', [PlanFeature::AiBusinessSummary->value => true]);

    Log::spy();

    (new GenerateBusinessSummaryJob($tenant))->failed(new AiRequestFailedException('boom'));

    Log::shouldHaveReceived('error')->once()->withArgs(
        fn (string $message, array $context) => $message === 'Business summary generation failed'
            && $context['tenant_id'] === $tenant->id
    );
});
