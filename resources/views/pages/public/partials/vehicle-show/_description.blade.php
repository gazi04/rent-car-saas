{{-- Vehicle description in the visitor's language; escaped with newlines preserved. --}}
@php($description = $vehicle->descriptionFor())
@if ($description !== '')
    <div>
        <h2 class="mb-3 text-lg font-semibold text-ink">{{ __('booking.description_heading') }}</h2>
        <p class="text-sm leading-relaxed text-ink-muted">{!! nl2br(e($description)) !!}</p>
    </div>
@endif
