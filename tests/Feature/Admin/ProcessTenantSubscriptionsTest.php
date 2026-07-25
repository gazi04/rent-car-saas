<?php

use App\Mail\SubscriptionRenewalReminderMail;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantSubscriptionSuspended;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\artisan;

beforeEach(function () {
    Mail::fake();
    Notification::fake();
});

it('queues a reminder 7 days before paid_until', function () {
    $tenant = Tenant::factory()->create(['paid_until' => now()->addDays(7)]);

    artisan('tenants:process-subscriptions')->assertSuccessful();

    Mail::assertQueued(SubscriptionRenewalReminderMail::class, fn ($m) => $m->hasTo($tenant->email) && $m->daysLeft === 7);
});

it('queues a reminder 1 day before paid_until', function () {
    $tenant = Tenant::factory()->create(['paid_until' => now()->addDay()]);

    artisan('tenants:process-subscriptions')->assertSuccessful();

    Mail::assertQueued(SubscriptionRenewalReminderMail::class, fn ($m) => $m->hasTo($tenant->email) && $m->daysLeft === 1);
});

it('uses trial wording for tenants still on the trial plan', function () {
    $tenant = Tenant::factory()->create(['plan' => 'trial', 'paid_until' => now()->addDays(7)]);

    artisan('tenants:process-subscriptions')->assertSuccessful();

    Mail::assertQueued(SubscriptionRenewalReminderMail::class, fn ($m) => $m->hasTo($tenant->email)
        && $m->langKey() === 'trial_expiring_reminder'
    );
});

it('uses renewal wording for tenants on a paid plan', function () {
    $tenant = Tenant::factory()->create(['plan' => 'basic', 'paid_until' => now()->addDays(7)]);

    artisan('tenants:process-subscriptions')->assertSuccessful();

    Mail::assertQueued(SubscriptionRenewalReminderMail::class, fn ($m) => $m->hasTo($tenant->email)
        && $m->langKey() === 'subscription_renewal_reminder'
    );
});

it('sends the reminder in the operator user\'s saved locale', function () {
    $tenant = Tenant::factory()->create(['paid_until' => now()->addDays(7)]);
    User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'operator'])
        ->forceFill(['locale' => 'en'])->save();

    artisan('tenants:process-subscriptions')->assertSuccessful();

    Mail::assertQueued(SubscriptionRenewalReminderMail::class, fn ($m) => $m->locale === 'en');
});

it('defaults the reminder locale to sq when the operator has none saved', function () {
    Tenant::factory()->create(['paid_until' => now()->addDays(7)]);

    artisan('tenants:process-subscriptions')->assertSuccessful();

    Mail::assertQueued(SubscriptionRenewalReminderMail::class, fn ($m) => $m->locale === 'sq');
});

it('sends no reminder on other days', function () {
    Tenant::factory()->create(['paid_until' => now()->addDays(4)]);

    artisan('tenants:process-subscriptions')->assertSuccessful();

    Mail::assertNothingQueued();
});

// ── Post-lapse grace reminder (reminder_days includes -1) ────────────────────

it('queues a grace reminder the day after the period lapsed', function () {
    $tenant = Tenant::factory()->create(['paid_until' => now()->subDay()]);

    artisan('tenants:process-subscriptions')->assertSuccessful();

    Mail::assertQueued(SubscriptionRenewalReminderMail::class, fn ($m) => $m->hasTo($tenant->email) && $m->isGrace());
});

it('uses trial grace wording for tenants still on the trial plan', function () {
    Tenant::factory()->create(['plan' => 'trial', 'paid_until' => now()->subDay()]);

    artisan('tenants:process-subscriptions')->assertSuccessful();

    Mail::assertQueued(SubscriptionRenewalReminderMail::class, fn ($m) => $m->langKey() === 'trial_grace_reminder');
});

it('uses subscription grace wording for tenants on a paid plan', function () {
    // The lapse lifecycle is plan-agnostic — only the copy differs by tier.
    Tenant::factory()->create(['plan' => 'basic', 'paid_until' => now()->subDay()]);

    artisan('tenants:process-subscriptions')->assertSuccessful();

    Mail::assertQueued(SubscriptionRenewalReminderMail::class, fn ($m) => $m->langKey() === 'subscription_grace_reminder');
});

it('reports grace days remaining and the suspension date, never a negative count', function () {
    $tenant = Tenant::factory()->create(['paid_until' => now()->subDay()]);
    $graceDays = (int) config('billing.grace_days');

    artisan('tenants:process-subscriptions')->assertSuccessful();

    Mail::assertQueued(SubscriptionRenewalReminderMail::class, fn ($m) => $m->displayDays() === $graceDays
        && $m->suspendsOn()->toDateString() === $tenant->paid_until->addDays($graceDays)->toDateString()
    );
});

it('renders the grace days in the body, not the raw negative threshold', function () {
    // Regression: Mailable::buildViewData() merges public properties after the
    // with() array, so a view variable named `daysLeft` was silently overwritten
    // by the raw -1 threshold — the subject read "7 day(s)" while the body read
    // "-1 day(s)". Asserting the accessor alone did not catch it; render instead.
    $tenant = Tenant::factory()->create(['plan' => 'trial', 'paid_until' => now()->subDay()]);

    $body = (new SubscriptionRenewalReminderMail($tenant, -1))->locale('en')->render();

    expect($body)->toContain('You have 7 day(s) left')
        ->and($body)->not->toContain('-1 day(s)');
});

it('sends no grace reminder once past the grace window, it suspends instead', function () {
    User::factory()->admin()->create();
    $tenant = Tenant::factory()->create(['paid_until' => now()->subDays(8)]);

    artisan('tenants:process-subscriptions')->assertSuccessful();

    expect($tenant->refresh()->status->value)->toBe('suspended');
    Mail::assertNothingQueued();
});

it('keeps a tenant active inside the 7-day grace period', function () {
    $tenant = Tenant::factory()->create(['paid_until' => now()->subDays(3)]);

    artisan('tenants:process-subscriptions')->assertSuccessful();

    expect($tenant->refresh()->status->value)->toBe('active');
    Notification::assertNothingSent();
});

it('suspends a tenant past the grace period and notifies the admins', function () {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create(['paid_until' => now()->subDays(8)]);

    artisan('tenants:process-subscriptions')->assertSuccessful();

    expect($tenant->refresh()->status->value)->toBe('suspended');

    Notification::assertSentTo($admin, TenantSubscriptionSuspended::class);
    // Filament's bell entry also goes through the notification system, so the
    // fake intercepts it here instead of writing a notifications-table row.
    Notification::assertSentTo($admin, DatabaseNotification::class);
});

it('skips tenants that are not enrolled (paid_until null)', function () {
    // Only a non-active tenant can be unenrolled now — Tenant::booted() gives
    // every Active tenant a paid_until, precisely so none can hide from this sweep.
    $tenant = Tenant::factory()->pending()->create(['paid_until' => null]);

    artisan('tenants:process-subscriptions')->assertSuccessful();

    expect($tenant->refresh()->status->value)->toBe('pending')
        ->and($tenant->paid_until)->toBeNull();
    Mail::assertNothingQueued();
    Notification::assertNothingSent();
});

it('skips tenants that are already suspended', function () {
    User::factory()->admin()->create();
    Tenant::factory()->suspended()->create(['paid_until' => now()->subDays(30)]);

    artisan('tenants:process-subscriptions')->assertSuccessful();

    Mail::assertNothingQueued();
    Notification::assertNothingSent();
});
