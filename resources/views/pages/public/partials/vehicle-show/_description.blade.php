{{-- Vehicle description; operator text escaped with newlines preserved. --}}
@if ($vehicle->description)
    <div>
        <h2 class="text-lg font-semibold text-gray-900 mb-3">{{ __('booking.description_heading') }}</h2>
        <p class="text-gray-600 text-sm leading-relaxed">{!! nl2br(e($vehicle->description)) !!}</p>
    </div>
@endif
