<x-mail::message>
# {{ $booking->vehicle->tenant->name ?? config('app.name') }}

{{ __('emails.new_booking_alert.greeting') }}

{{ __('emails.new_booking_alert.intro') }}

| | |
|---|---|
| **{{ __('emails.new_booking_alert.customer_label') }}** | {{ $booking->customer_name }} ({{ $booking->customer_phone }}) |
| **{{ __('emails.new_booking_alert.vehicle_label') }}** | {{ $booking->vehicle->name }} |
| **{{ __('emails.new_booking_alert.dates_label') }}** | {{ $booking->start_date->format('d M Y') }} – {{ $booking->end_date->format('d M Y') }} |
| **{{ __('emails.new_booking_alert.total_label') }}** | €{{ number_format($booking->total, 2) }} |

{{ __('emails.new_booking_alert.outro') }}
</x-mail::message>
