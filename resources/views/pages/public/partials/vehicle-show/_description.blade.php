{{-- Vehicle description in the visitor's language; escaped with newlines preserved. --}}
@php($description = $vehicle->descriptionFor())
@if ($description !== '')
    <div>
        <h2 class="text-lg font-semibold text-gray-900 mb-3">{{ __('booking.description_heading') }}</h2>
        <p class="text-gray-600 text-sm leading-relaxed">{!! nl2br(e($description)) !!}</p>
    </div>
@endif
