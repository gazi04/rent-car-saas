<x-mail::message>
# {{ config('app.name') }}

{{ __('emails.service_due.greeting') }}

{{ __('emails.service_due.intro', ['vehicle' => $serviceRecord->vehicle->name, 'date' => $serviceRecord->next_due_on->format('d M Y')]) }}

| | |
|---|---|
| **{{ __('emails.service_due.vehicle_label') }}** | {{ $serviceRecord->vehicle->name }} |
| **{{ __('emails.service_due.due_on_label') }}** | {{ $serviceRecord->next_due_on->format('d M Y') }} |
| **{{ __('emails.service_due.last_service_label') }}** | {{ __("panel.service_type_{$serviceRecord->service_type}") }} ({{ $serviceRecord->performed_on->format('d M Y') }}) |

{{ __('emails.service_due.outro') }}
</x-mail::message>
