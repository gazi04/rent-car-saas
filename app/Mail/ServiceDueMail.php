<?php

namespace App\Mail;

use App\Models\ServiceRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ServiceDueMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ServiceRecord $serviceRecord) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.service_due.subject', ['vehicle' => $this->serviceRecord->vehicle->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.service-due',
            with: ['serviceRecord' => $this->serviceRecord],
        );
    }
}
