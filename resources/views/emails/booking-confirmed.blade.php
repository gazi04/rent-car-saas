<x-mail::message>
# {{ $booking->vehicle->tenant->name ?? config('app.name') }}

{{ __('emails.booking_confirmed.greeting', ['name' => $booking->customer_name]) }}

{{ __('emails.booking_confirmed.intro') }}

| | |
|---|---|
| **{{ __('emails.booking_confirmed.reference_label') }}** | {{ $booking->reference }} |
| **{{ __('emails.booking_confirmed.vehicle_label') }}** | {{ $booking->vehicle->name }} |
| **{{ __('emails.booking_confirmed.dates_label') }}** | {{ $booking->start_date->format('d M Y') }} – {{ $booking->end_date->format('d M Y') }} |
| **{{ __('emails.booking_confirmed.total_label') }}** | €{{ number_format($booking->total, 2) }} |

{{ __('emails.booking_confirmed.payment_note') }}

@if(!empty($agreementUrl))
<x-mail::button :url="$agreementUrl" color="primary">
{{ __('emails.booking_confirmed.agreement_button') }}
</x-mail::button>
@endif

{{ __('emails.booking_confirmed.outro', ['operator' => $booking->vehicle->tenant->name ?? config('app.name')]) }}
</x-mail::message>
