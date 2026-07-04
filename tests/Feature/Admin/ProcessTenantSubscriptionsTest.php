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

it('keeps a tenant active inside the 7-day grace period', function () {
    $tenant = Tenant::factory()->create(['paid_until' => now()->subDays(3)]);

    artisan('tenants:process-subscriptions')->assertSuccessful();

    expect($tenant->refresh()->status)->toBe('active');
    Notification::assertNothingSent();
});

it('suspends a tenant past the grace period and notifies the admins', function () {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create(['paid_until' => now()->subDays(8)]);

    artisan('tenants:process-subscriptions')->assertSuccessful();

    expect($tenant->refresh()->status)->toBe('suspended');

    Notification::assertSentTo($admin, TenantSubscriptionSuspended::class);
    // Filament's bell entry also goes through the notification system, so the
    // fake intercepts it here instead of writing a notifications-table row.
    Notification::assertSentTo($admin, DatabaseNotification::class);
});

it('skips tenants that are not enrolled (paid_until null)', function () {
    $tenant = Tenant::factory()->create(['paid_until' => null]);

    artisan('tenants:process-subscriptions')->assertSuccessful();

    expect($tenant->refresh()->status)->toBe('active');
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
