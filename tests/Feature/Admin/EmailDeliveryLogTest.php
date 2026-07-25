<?php

use App\Enums\EmailStatus;
use App\Filament\Resources\EmailLogs\Pages\ListEmailLogs;
use App\Filament\Widgets\EmailDeliveryStats;
use App\Models\EmailLog;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

afterEach(function () {
    tenancy()->end();
});

/**
 * Minimal real Mailable so a genuine MessageSent event fires (Mail::fake() would
 * swap the mailer and suppress it). The suite runs on MAIL_MAILER=array.
 */
class DeliveryLogTestMail extends Mailable
{
    public function __construct(private readonly string $line = 'Test subject') {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->line);
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>hello</p>');
    }
}

/**
 * Build a valid Svix signature header for a raw JSON body, matching the scheme
 * ResendWebhookController verifies.
 */
function svixHeaders(string $body, string $secret = 'whsec_'): array
{
    $secret = $secret === 'whsec_' ? 'whsec_'.base64_encode('super-secret-key') : $secret;
    config(['services.resend.webhook_secret' => $secret]);

    $id = 'msg_test';
    $timestamp = (string) time();
    $key = base64_decode(substr($secret, 6));
    $signature = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", $key, true));

    return [
        'svix-id' => $id,
        'svix-timestamp' => $timestamp,
        'svix-signature' => "v1,{$signature}",
    ];
}

it('logs a sent email as a central row', function () {
    Mail::to('rider@example.com')->send(new DeliveryLogTestMail('Booking confirmed'));

    $row = EmailLog::query()->sole();

    expect($row->to_email)->toBe('rider@example.com')
        ->and($row->subject)->toBe('Booking confirmed')
        ->and($row->mailable)->toBe(DeliveryLogTestMail::class)
        ->and($row->status)->toBe(EmailStatus::Sent)
        ->and($row->tenant_id)->toBeNull();
});

it('attributes a sent email to the active tenant', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);

    Mail::to('rider@example.com')->send(new DeliveryLogTestMail);

    expect(EmailLog::query()->sole()->tenant_id)->toBe($tenant->id);
});

it('advances a row to delivered from a signed webhook', function () {
    $log = EmailLog::factory()->create(['message_id' => 'resend-123', 'status' => EmailStatus::Sent]);

    $body = (string) json_encode(['type' => 'email.delivered', 'data' => ['email_id' => 'resend-123']]);

    postJson('/webhooks/resend', json_decode($body, true), svixHeaders($body))
        ->assertOk();

    expect($log->refresh()->status)->toBe(EmailStatus::Delivered);
});

it('records a bounce reason from a signed webhook', function () {
    $log = EmailLog::factory()->create(['message_id' => 'resend-456', 'status' => EmailStatus::Sent]);

    $body = (string) json_encode([
        'type' => 'email.bounced',
        'data' => ['email_id' => 'resend-456', 'reason' => 'Mailbox does not exist'],
    ]);

    postJson('/webhooks/resend', json_decode($body, true), svixHeaders($body))
        ->assertOk();

    $log->refresh();

    expect($log->status)->toBe(EmailStatus::Bounced)
        ->and($log->error)->toBe('Mailbox does not exist');
});

it('rejects a webhook with an invalid signature and changes nothing', function () {
    $log = EmailLog::factory()->create(['message_id' => 'resend-789', 'status' => EmailStatus::Sent]);

    config(['services.resend.webhook_secret' => 'whsec_'.base64_encode('super-secret-key')]);

    $body = (string) json_encode(['type' => 'email.bounced', 'data' => ['email_id' => 'resend-789']]);

    postJson('/webhooks/resend', json_decode($body, true), [
        'svix-id' => 'msg_test',
        'svix-timestamp' => (string) time(),
        'svix-signature' => 'v1,not-a-real-signature',
    ])->assertStatus(400);

    expect($log->refresh()->status)->toBe(EmailStatus::Sent);
});

it('acknowledges a signed webhook for an unknown email id', function () {
    $body = (string) json_encode(['type' => 'email.delivered', 'data' => ['email_id' => 'never-seen']]);

    postJson('/webhooks/resend', json_decode($body, true), svixHeaders($body))
        ->assertOk();
});

it('renders the read-only email log resource for a super admin', function () {
    $rows = EmailLog::factory()->count(3)->create();

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    actingAs(User::factory()->admin()->create());

    Livewire::test(ListEmailLogs::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($rows);
});

it('shows delivery health on the admin stats widget', function () {
    EmailLog::factory()->count(4)->create(['created_at' => now()]);
    EmailLog::factory()->bounced()->create(['created_at' => now()]);
    // Last month — excluded from the monthly stats.
    EmailLog::factory()->create(['created_at' => now()->subMonthNoOverflow()->startOfMonth()]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    actingAs(User::factory()->admin()->create());

    Livewire::test(EmailDeliveryStats::class)
        ->assertSee('Emails this month')
        ->assertSee('Bounced')
        ->assertSee('5'); // 4 sent + 1 bounced this month
});
