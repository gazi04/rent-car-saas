<?php

use App\Events\BookingCancelled;
use App\Events\BookingConfirmed;
use App\Events\BookingCreated;
use App\Events\BookingRejected;
use App\Listeners\SendBookingCancelledNotifications;
use App\Listeners\SendBookingConfirmedEmail;
use App\Listeners\SendBookingReceivedNotifications;
use App\Listeners\SendBookingRejectedEmail;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingConfirmedMail;
use App\Mail\BookingReceivedMail;
use App\Mail\BookingRejectedMail;
use App\Mail\NewBookingAlertMail;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\BookingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    tenancy()->initialize($this->tenant);
    $this->operator = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'operator']);
    $this->vehicle = Vehicle::factory()->create(['daily_rate' => 50]);
    $this->service = app(BookingService::class);
});

afterEach(fn () => tenancy()->end());

/** @return array<string, mixed> */
function notifBookingData(Vehicle $vehicle, array $overrides = []): array
{
    return array_merge([
        'vehicle_id' => $vehicle->id,
        'customer_name' => 'Ana Kelmendi',
        'customer_phone' => '+38344000001',
        'customer_email' => 'ana@example.com',
        'start_date' => '2031-07-01',
        'end_date' => '2031-07-04',
    ], $overrides);
}

// ── Public booking (BookingCreated) ─────────────────────────────────────────

test('public booking queues customer received mail and operator alert mail', function () {
    Mail::fake();

    $booking = $this->service->create(notifBookingData($this->vehicle));

    $listener = new SendBookingReceivedNotifications;
    $listener->handle(new BookingCreated($booking));

    Mail::assertQueued(BookingReceivedMail::class, fn ($m) => $m->hasTo($booking->customer_email));
    Mail::assertQueued(NewBookingAlertMail::class, fn ($m) => $m->hasTo($this->operator->email));
});

test('public booking listener sends operator dashboard bell notification', function () {
    Mail::fake();

    $booking = $this->service->create(notifBookingData($this->vehicle));

    $listener = new SendBookingReceivedNotifications;
    $listener->handle(new BookingCreated($booking));

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $this->operator->id,
        'notifiable_type' => User::class,
    ]);
});

test('manual booking does not queue customer received mail', function () {
    Mail::fake();

    $booking = $this->service->createManual(notifBookingData($this->vehicle));

    Mail::assertNothingQueued();
    expect($booking->status->value)->toBe('confirmed');
});

test('no customer mail queued when customer_email is null', function () {
    Mail::fake();

    $booking = $this->service->create(notifBookingData($this->vehicle, ['customer_email' => null]));

    $listener = new SendBookingReceivedNotifications;
    $listener->handle(new BookingCreated($booking));

    Mail::assertNotQueued(BookingReceivedMail::class);
    Mail::assertQueued(NewBookingAlertMail::class);
});

// ── Confirm (BookingConfirmed) ───────────────────────────────────────────────

test('confirm queues booking confirmed mail to customer', function () {
    Mail::fake();

    $booking = Booking::factory()->create([
        'vehicle_id' => $this->vehicle->id,
        'customer_email' => 'ana@example.com',
        'locale' => 'en',
    ]);

    $listener = new SendBookingConfirmedEmail;
    $listener->handle(new BookingConfirmed($booking));

    Mail::assertQueued(BookingConfirmedMail::class, fn ($m) => $m->hasTo($booking->customer_email)
        && $m->envelope()->subject === "Booking Confirmed — {$booking->reference}"
    );
});

test('confirm still queues mail and generates the agreement when the vehicle was soft-deleted', function () {
    Storage::fake();
    Mail::fake();

    $booking = Booking::factory()->create([
        'vehicle_id' => $this->vehicle->id,
        'customer_email' => 'ana@example.com',
        'locale' => 'en',
    ]);
    $this->vehicle->delete();

    $listener = new SendBookingConfirmedEmail;
    $listener->handle(new BookingConfirmed($booking->fresh()));

    Mail::assertQueued(BookingConfirmedMail::class, fn ($m) => $m->hasTo($booking->customer_email));
});

test('confirm dispatches BookingConfirmed event via service', function () {
    $booking = Booking::factory()->create(['vehicle_id' => $this->vehicle->id]);

    Event::fake([BookingConfirmed::class]);

    $this->service->confirm($booking);

    Event::assertDispatched(BookingConfirmed::class, fn ($e) => $e->booking->is($booking));
});

// ── Reject (BookingRejected) ─────────────────────────────────────────────────

test('reject queues booking rejected mail to customer', function () {
    Mail::fake();

    $booking = Booking::factory()->create([
        'vehicle_id' => $this->vehicle->id,
        'customer_email' => 'ana@example.com',
        'locale' => 'sq',
    ]);

    $listener = new SendBookingRejectedEmail;
    $listener->handle(new BookingRejected($booking));

    Mail::assertQueued(BookingRejectedMail::class, fn ($m) => $m->hasTo($booking->customer_email));
});

test('reject dispatches BookingRejected event via service', function () {
    $booking = Booking::factory()->create(['vehicle_id' => $this->vehicle->id]);

    Event::fake([BookingRejected::class]);

    $this->service->reject($booking);

    Event::assertDispatched(BookingRejected::class);
});

// ── Cancel by operator ───────────────────────────────────────────────────────

test('cancel by operator emails customer but not operator bell', function () {
    Mail::fake();

    $booking = Booking::factory()->create([
        'vehicle_id' => $this->vehicle->id,
        'customer_email' => 'ana@example.com',
        'locale' => 'en',
    ]);

    $notificationsBefore = $this->operator->notifications()->count();

    $listener = new SendBookingCancelledNotifications;
    $listener->handle(new BookingCancelled($booking, 'operator'));

    Mail::assertQueued(BookingCancelledMail::class, fn ($m) => $m->hasTo($booking->customer_email));
    expect($this->operator->notifications()->count())->toBe($notificationsBefore);
});

test('cancel by customer emails customer and operator and sends bell', function () {
    Mail::fake();

    $booking = Booking::factory()->create([
        'vehicle_id' => $this->vehicle->id,
        'customer_email' => 'ana@example.com',
        'locale' => 'en',
    ]);

    $listener = new SendBookingCancelledNotifications;
    $listener->handle(new BookingCancelled($booking, 'customer'));

    Mail::assertQueued(BookingCancelledMail::class, fn ($m) => $m->hasTo($booking->customer_email));
    Mail::assertQueued(BookingCancelledMail::class, fn ($m) => $m->hasTo($this->operator->email));
    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $this->operator->id,
        'notifiable_type' => User::class,
    ]);
});

test('cancel dispatches BookingCancelled event with cancelledBy actor via service', function () {
    $booking = Booking::factory()->create(['vehicle_id' => $this->vehicle->id]);

    Event::fake([BookingCancelled::class]);

    $this->service->cancel($booking, cancelledBy: 'customer');

    Event::assertDispatched(BookingCancelled::class, fn ($e) => $e->cancelledBy === 'customer');
});

// ── Bilingual ────────────────────────────────────────────────────────────────

test('booking received mailable stores booking locale sq', function () {
    $booking = Booking::factory()->create([
        'vehicle_id' => $this->vehicle->id,
        'locale' => 'sq',
    ]);

    $mailable = new BookingReceivedMail($booking);

    expect($mailable->booking->locale)->toBe('sq');
});

test('booking confirmed mailable stores booking locale en', function () {
    $booking = Booking::factory()->create([
        'vehicle_id' => $this->vehicle->id,
        'locale' => 'en',
    ]);

    $mailable = new BookingConfirmedMail($booking);

    expect($mailable->booking->locale)->toBe('en');
});

test('booking confirmed email renders an absolute logo URL, not a relative one', function () {
    Storage::fake('public');

    $this->tenant->addMedia(UploadedFile::fake()->image('logo.png', 200, 200))
        ->toMediaCollection('logo');

    $booking = Booking::factory()->create(['vehicle_id' => $this->vehicle->id]);

    $rendered = (new BookingConfirmedMail($booking))->render();

    expect($rendered)->toContain('src="'.rtrim(config('app.url'), '/'))
        ->and($rendered)->not->toContain('src="/storage/');
});

// ── Queued, not sync ─────────────────────────────────────────────────────────

test('all mailables implement ShouldQueue', function () {
    expect(BookingReceivedMail::class)->toImplement(ShouldQueue::class);
    expect(NewBookingAlertMail::class)->toImplement(ShouldQueue::class);
    expect(BookingConfirmedMail::class)->toImplement(ShouldQueue::class);
    expect(BookingRejectedMail::class)->toImplement(ShouldQueue::class);
    expect(BookingCancelledMail::class)->toImplement(ShouldQueue::class);
});

test('all listeners implement ShouldQueue', function () {
    expect(SendBookingReceivedNotifications::class)->toImplement(ShouldQueue::class);
    expect(SendBookingConfirmedEmail::class)->toImplement(ShouldQueue::class);
    expect(SendBookingRejectedEmail::class)->toImplement(ShouldQueue::class);
    expect(SendBookingCancelledNotifications::class)->toImplement(ShouldQueue::class);
});

// ── Tenant isolation ─────────────────────────────────────────────────────────

test('operator alert goes only to this tenants users not another tenants users', function () {
    Mail::fake();

    $otherTenant = Tenant::factory()->create();
    $otherOperator = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => 'operator']);

    $booking = $this->service->create(notifBookingData($this->vehicle));

    $listener = new SendBookingReceivedNotifications;
    $listener->handle(new BookingCreated($booking));

    Mail::assertQueued(NewBookingAlertMail::class, fn ($m) => $m->hasTo($this->operator->email));
    Mail::assertNotQueued(NewBookingAlertMail::class, fn ($m) => $m->hasTo($otherOperator->email));
});

// ── Signed-URL root derivation (M9) ──────────────────────────────────────────

test('signed cancel link follows app.url scheme and port on the tenant domain', function () {
    tenancy()->end();

    config(['app.url' => 'https://platform.test:8443']);

    $tenant = Tenant::factory()->withDomain('m9tenant')->create();
    tenancy()->initialize($tenant);
    $vehicle = Vehicle::factory()->create(['daily_rate' => 50]);
    $booking = app(BookingService::class)->create(notifBookingData($vehicle));

    expect($tenant->publicRootUrl())->toBe('https://'.tenant_domain('m9tenant').':8443');

    $mail = BookingReceivedMail::forTenantDomain($booking);

    expect($mail->cancelUrl)->toStartWith('https://'.tenant_domain('m9tenant').':8443/')
        ->and($mail->cancelUrl)->toContain('signature=');
});
