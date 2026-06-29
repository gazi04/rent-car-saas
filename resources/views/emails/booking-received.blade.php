<x-mail::message>
# {{ $booking->vehicle->tenant->name ?? config('app.name') }}

{{ __('emails.booking_received.greeting', ['name' => $booking->customer_name]) }}

{{ __('emails.booking_received.intro') }}

| | |
|---|---|
| **{{ __('emails.booking_received.reference_label') }}** | {{ $booking->reference }} |
| **{{ __('emails.booking_received.vehicle_label') }}** | {{ $booking->vehicle->name }} |
| **{{ __('emails.booking_received.dates_label') }}** | {{ $booking->start_date->format('d M Y') }} – {{ $booking->end_date->format('d M Y') }} |
| **{{ __('emails.booking_received.total_label') }}** | €{{ number_format($booking->total, 2) }} |

@if($booking->customer_email)
<x-mail::button :url="url()->temporarySignedRoute('public.booking.cancel', now()->addDay(), ['booking' => $booking->id])">
{{ __('emails.booking_received.cancel_action') }}
</x-mail::button>

{{ __('emails.booking_received.cancel_note') }}
@endif

{{ __('emails.booking_received.outro', ['operator' => $booking->vehicle->tenant->name ?? config('app.name')]) }}
</x-mail::message>
