<x-mail::message>
# {{ $booking->vehicle->tenant->name ?? config('app.name') }}

{{ __('emails.booking_cancelled.greeting', ['name' => $booking->customer_name]) }}

{{ __('emails.booking_cancelled.intro') }}

| | |
|---|---|
| **{{ __('emails.booking_cancelled.reference_label') }}** | {{ $booking->reference }} |
| **{{ __('emails.booking_cancelled.vehicle_label') }}** | {{ $booking->vehicle->name }} |
| **{{ __('emails.booking_cancelled.dates_label') }}** | {{ $booking->start_date->format('d M Y') }} – {{ $booking->end_date->format('d M Y') }} |

{{ __('emails.booking_cancelled.outro', ['operator' => $booking->vehicle->tenant->name ?? config('app.name')]) }}
</x-mail::message>
