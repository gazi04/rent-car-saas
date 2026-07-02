@php
    /** Shared vehicle card. $horizontal=true renders image beside content instead of above. */
    $horizontal = $horizontal ?? false;
@endphp

<div class="bg-white rounded-lg border border-gray-200 overflow-hidden hover:shadow-md transition-shadow {{ $horizontal ? 'sm:flex' : '' }}">
    {{-- Cover photo --}}
    <div class="{{ $horizontal ? 'sm:w-64 sm:shrink-0 aspect-video sm:aspect-auto' : 'aspect-video' }} bg-gray-100 overflow-hidden">
        @if ($vehicle->getFirstMedia('vehicle_photos'))
            <img src="{{ $vehicle->getFirstMediaUrl('vehicle_photos', 'web') }}"
                 alt="{{ $vehicle->name }}"
                 class="w-full h-full object-cover">
        @else
            <div class="w-full h-full flex items-center justify-center text-gray-400 {{ $horizontal ? 'min-h-40' : '' }}">
                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                </svg>
            </div>
        @endif
    </div>

    <div class="p-4 {{ $horizontal ? 'flex-1 flex flex-col justify-between' : '' }}">
        <div>
            <div class="flex items-start justify-between mb-2">
                <h2 class="font-semibold text-gray-900 text-sm leading-tight">{{ $vehicle->name }}</h2>
                <span class="ml-2 inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-secondary shrink-0">
                    {{ $vehicle->category->getLabel() }}
                </span>
            </div>

            <div class="flex items-center gap-3 text-xs text-gray-500 mb-3">
                <span>{{ __('booking.seats', ['count' => $vehicle->seats]) }}</span>
                <span>{{ $vehicle->fuel_type->getLabel() }}</span>
                <span>{{ $vehicle->transmission->getLabel() }}</span>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <div>
                <span class="text-lg font-bold text-gray-900">€{{ number_format((float) $vehicle->daily_rate, 2) }}</span>
                <span class="text-xs text-gray-500 ml-1">{{ __('booking.per_day') }}</span>
            </div>
            <a href="{{ route('public.vehicle', $vehicle) }}"
               class="inline-flex items-center rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-secondary transition-colors">
                {{ __('booking.book_now') }}
            </a>
        </div>
    </div>
</div>
