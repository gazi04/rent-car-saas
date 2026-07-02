@php
    /** Shared services/features section. $stacked=true renders a vertical list instead of a grid. */
    $stacked = $stacked ?? false;
    $showHeading = $showHeading ?? true;
@endphp

<div>
    @if ($showHeading)
        <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-8">{{ __('booking.home_services_heading') }}</h2>
    @endif

    <div class="{{ $stacked ? 'space-y-6' : 'grid sm:grid-cols-3 gap-6' }}">
        @foreach ($content['services'] as $index => $service)
            <div class="bg-white rounded-lg border border-gray-200 p-6 {{ $stacked ? 'flex items-start gap-4' : '' }}" wire:key="service-{{ $index }}">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-secondary font-bold {{ $stacked ? '' : 'mb-4' }}">
                    {{ $index + 1 }}
                </span>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">{{ $service['title'] }}</h3>
                    <p class="text-sm text-gray-600">{{ $service['text'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
</div>
