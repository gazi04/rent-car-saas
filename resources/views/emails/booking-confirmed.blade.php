<x-mail::message>
@php
    $tenant = $booking->vehicle->tenant ?? null;
    $logoUrl = $tenant?->logoUrl();
    if ($logoUrl) {
        $logoUrl = rtrim(config('app.url'), '/').$logoUrl;
    }
    $paymentInstructions = $tenant?->setting('payment_instructions');
@endphp

@if($logoUrl)
<div style="text-align:center;margin-bottom:16px;">
<img src="{{ $logoUrl }}" alt="{{ $tenant->name }}" style="max-height:60px;max-width:200px;">
</div>
@endif

# {{ $tenant->name ?? config('app.name') }}

{{ __('emails.booking_confirmed.greeting', ['name' => $booking->customer_name]) }}

{{ __('emails.booking_confirmed.intro') }}

| | |
|---|---|
| **{{ __('emails.booking_confirmed.reference_label') }}** | {{ $booking->reference }} |
| **{{ __('emails.booking_confirmed.vehicle_label') }}** | {{ $booking->vehicle->name }} |
| **{{ __('emails.booking_confirmed.dates_label') }}** | {{ $booking->start_date->format('d M Y') }} – {{ $booking->end_date->format('d M Y') }} |
| **{{ __('emails.booking_confirmed.total_label') }}** | €{{ number_format($booking->total, 2) }} |

{{ $paymentInstructions ?? __('emails.booking_confirmed.payment_note') }}

@if(!empty($agreementUrl))
<x-mail::button :url="$agreementUrl" color="primary">
{{ __('emails.booking_confirmed.agreement_button') }}
</x-mail::button>
@endif

{{ __('emails.booking_confirmed.outro', ['operator' => $tenant->name ?? config('app.name')]) }}
</x-mail::message>
