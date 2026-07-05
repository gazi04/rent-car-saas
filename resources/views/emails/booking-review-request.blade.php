<x-mail::message>
# {{ $operator }}

{{ __('emails.review_request.greeting', ['name' => $booking->customer_name]) }}

{{ __('emails.review_request.intro', ['vehicle' => $booking->vehicle->name]) }}

@if(!empty($reviewUrl))
<x-mail::button :url="$reviewUrl">
{{ __('emails.review_request.button') }}
</x-mail::button>
@endif

{{ __('emails.review_request.outro', ['operator' => $operator]) }}
</x-mail::message>
