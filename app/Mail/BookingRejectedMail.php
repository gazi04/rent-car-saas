<?php

namespace App\Mail;

use App\Concerns\ThrottlesMailQueue;
use App\Models\Booking;
use App\Models\Tenant;
use App\Services\TemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;
    use ThrottlesMailQueue;

    public function __construct(public readonly Booking $booking) {}

    public function envelope(): Envelope
    {
        $tenant = Tenant::query()->findOrFail($this->booking->tenant_id);

        return new Envelope(
            from: $tenant->senderAddress(),
            replyTo: array_filter([$tenant->replyToAddress()]),
            subject: resolve(TemplateRenderer::class)->resolve(
                $this->booking,
                'tmpl_email_rejected_subject',
                'emails.booking_rejected.subject',
                ['reference' => $this->booking->reference],
            ),
        );
    }

    public function content(): Content
    {
        $renderer = resolve(TemplateRenderer::class);
        $operator = Tenant::query()->findOrFail($this->booking->tenant_id)->name;

        return new Content(
            markdown: 'emails.booking-rejected',
            with: [
                'booking' => $this->booking,
                'intro' => $renderer->resolve($this->booking, 'tmpl_email_rejected_intro', 'emails.booking_rejected.intro'),
                'outro' => $renderer->resolve($this->booking, 'tmpl_email_rejected_outro', 'emails.booking_rejected.outro', ['operator' => $operator]),
            ],
        );
    }
}
