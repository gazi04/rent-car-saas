<x-mail::message>
# {{ config('app.name') }}

{{ __("emails.{$langKey}.greeting", ['name' => $tenant->name]) }}

{{ __("emails.{$langKey}.intro", ['days' => $displayDays, 'date' => $tenant->paid_until->format('d M Y'), 'suspends_on' => $suspendsOn]) }}

| | |
|---|---|
| **{{ __("emails.{$langKey}.plan_label") }}** | {{ ucfirst($tenant->plan) }} |
| **{{ __("emails.{$langKey}.paid_until_label") }}** | {{ $tenant->paid_until->format('d M Y') }} |

{{ __("emails.{$langKey}.payment_note") }}

{{ __("emails.{$langKey}.outro") }}
</x-mail::message>
