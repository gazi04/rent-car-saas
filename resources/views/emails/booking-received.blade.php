<x-mail::message>
# {{ $booking->vehicle->tenant->name ?? config('app.name') }}

{{ __('emails.booking_received.greeting', ['name' => $booking->customer_name]) }}

{{ $intro }}

| | |
|---|---|
| **{{ __('emails.booking_received.reference_label') }}** | {{ $booking->reference }} |
| **{{ __('emails.booking_received.vehicle_label') }}** | {{ $booking->vehicle->name }} |
| **{{ __('emails.booking_received.dates_label') }}** | {{ $booking->start_date->format('d M Y') }} – {{ $booking->end_date->format('d M Y') }} |
| **{{ __('emails.booking_received.total_label') }}** | €{{ number_format($booking->total, 2) }} |

@if(!empty($cancelUrl))
<x-mail::button :url="$cancelUrl">
{{ __('emails.booking_received.cancel_action') }}
</x-mail::button>

{{ __('emails.booking_received.cancel_note') }}
@endif

{{ $outro }}
</x-mail::message>
