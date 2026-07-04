<?php

use App\Enums\PlanFeature;
use App\Filament\Operator\Pages\TemplateSettings;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TemplateRenderer;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;

afterEach(fn () => tenancy()->end());

/**
 * @param  array<string, mixed>  $planFeatures
 * @return array{0: Tenant, 1: User}
 */
function templateOperator(string $domain, array $planFeatures = [], ?string $planSlug = null): array
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
    app()->setLocale('sq');
    actingAs($owner);

    return [$tenant, $owner];
}

it('substitutes variables and leaves unknown tokens literal', function () {
    $out = app(TemplateRenderer::class)->render(
        'Hi {customer_name}, ref {reference}, {unknown}',
        ['customer_name' => 'Arben', 'reference' => 'BK-1'],
    );

    expect($out)->toBe('Hi Arben, ref BK-1, {unknown}');
});

it('resolves an override when set, else the default', function () {
    templateOperator('tmplresolve');
    $vehicle = Vehicle::factory()->create(['daily_rate' => 50]);
    $booking = Booking::factory()->forVehicle($vehicle)->create([
        'customer_name' => 'Arben Krasniqi',
        'locale' => 'sq',
    ]);

    $renderer = app(TemplateRenderer::class);

    // No override yet → the built-in default.
    expect($renderer->resolve($booking, 'tmpl_agreement_terms', 'contract.terms_body'))
        ->toBe((string) __('contract.terms_body'));

    tenant()->setSetting('tmpl_agreement_terms_sq', 'Terms for {customer_name} — ref {reference}.');

    expect($renderer->resolve($booking, 'tmpl_agreement_terms', 'contract.terms_body'))
        ->toBe("Terms for Arben Krasniqi — ref {$booking->reference}.");
});

it('applies custom subject and body to a booking email', function () {
    templateOperator('tmplemail');
    $vehicle = Vehicle::factory()->create(['daily_rate' => 50]);
    $booking = Booking::factory()->forVehicle($vehicle)->confirmed()->create([
        'customer_name' => 'Arben',
        'locale' => 'sq',
    ]);

    tenant()->setSetting('tmpl_email_confirmed_subject_sq', 'Rezervimi {reference} u konfirmua');
    tenant()->setSetting('tmpl_email_confirmed_intro_sq', 'Përshëndetje {customer_name}, makina {vehicle} është gati.');

    $mail = new BookingConfirmedMail($booking);

    $mail->assertHasSubject("Rezervimi {$booking->reference} u konfirmua");
    $mail->assertSeeInHtml("Përshëndetje Arben, makina {$vehicle->name} është gati.", false);
});

it('falls back to the default email copy without an override', function () {
    templateOperator('tmpldefault');
    $vehicle = Vehicle::factory()->create(['daily_rate' => 50]);
    $booking = Booking::factory()->forVehicle($vehicle)->confirmed()->create(['locale' => 'sq']);

    $mail = new BookingConfirmedMail($booking);

    $mail->assertHasSubject((string) __('emails.booking_confirmed.subject', ['reference' => $booking->reference]));
    $mail->assertSeeInHtml((string) __('emails.booking_confirmed.intro'), false);
});

it('accepts template keys through setSetting (allow-list extended)', function () {
    templateOperator('tmplallow');

    tenant()->setSetting('tmpl_agreement_terms_en', 'English terms');

    expect(tenant()->setting('tmpl_agreement_terms_en'))->toBe('English terms');
});

it('gates the template page to owners on an allowing plan', function () {
    templateOperator('tmplgateon', [PlanFeature::Templates->value => true], 'withtmpl');

    expect(TemplateSettings::canAccess())->toBeTrue();
});

it('hides the template page when the plan disables it', function () {
    templateOperator('tmplgateoff', [PlanFeature::Templates->value => false], 'notmpl');

    expect(TemplateSettings::canAccess())->toBeFalse();
});

it('hides the template page from staff accounts', function () {
    [$tenant] = templateOperator('tmplgatestaff', [PlanFeature::Templates->value => true], 'stafftmpl');

    $staff = User::factory()->staff()->create(['tenant_id' => $tenant->id]);
    actingAs($staff);

    expect(TemplateSettings::canAccess())->toBeFalse();
});
