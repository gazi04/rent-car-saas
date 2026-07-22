<?php

namespace App\Mail;

use App\Concerns\ThrottlesMailQueue;
use App\Models\Booking;
use App\Models\Tenant;
use App\Services\TemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingConfirmedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;
    use ThrottlesMailQueue;

    public function __construct(
        public readonly Booking $booking,
        public readonly ?string $agreementUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        $tenant = Tenant::query()->find($this->booking->tenant_id);

        return new Envelope(
            from: new Address($tenant->email, $tenant->name),
            subject: resolve(TemplateRenderer::class)->resolve(
                $this->booking,
                'tmpl_email_confirmed_subject',
                'emails.booking_confirmed.subject',
                ['reference' => $this->booking->reference],
            ),
        );
    }

    public function content(): Content
    {
        $renderer = resolve(TemplateRenderer::class);
        $operator = Tenant::query()->find($this->booking->tenant_id)->name;

        return new Content(
            markdown: 'emails.booking-confirmed',
            with: [
                'booking' => $this->booking,
                'agreementUrl' => $this->agreementUrl,
                'intro' => $renderer->resolve($this->booking, 'tmpl_email_confirmed_intro', 'emails.booking_confirmed.intro'),
                'outro' => $renderer->resolve($this->booking, 'tmpl_email_confirmed_outro', 'emails.booking_confirmed.outro', ['operator' => $operator]),
            ],
        );
    }
}
