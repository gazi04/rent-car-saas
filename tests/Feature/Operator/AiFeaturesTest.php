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
use Filament\Facades\Filament;
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
function aiOperator(string $domain, array $planFeatures = []): array
{
    Plan::factory()->create(['slug' => 'ai-plan', 'features' => $planFeatures]);

    $tenant = Tenant::factory()->withDomain($domain)->create(['plan' => 'ai-plan']);

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
