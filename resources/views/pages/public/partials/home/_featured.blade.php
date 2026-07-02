@php
    /** Shared featured-vehicles strip. $columns controls the grid density. */
    $columns = $columns ?? 3;
    $limit = $limit ?? null;
    $shown = $limit ? $vehicles->take($limit) : $vehicles;
@endphp

@if ($shown->isNotEmpty())
    <div>
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ __('booking.featured_vehicles') }}</h2>
            <a href="{{ route('public.vehicles') }}" class="text-sm font-medium text-primary hover:underline">
                {{ __('booking.view_all_vehicles') }} →
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 {{ $columns === 3 ? 'lg:grid-cols-3' : '' }} gap-6">
            @foreach ($shown as $vehicle)
                <div wire:key="featured-{{ $vehicle->id }}">
                    @include('pages.public.partials.vehicles._card', ['vehicle' => $vehicle])
                </div>
            @endforeach
        </div>
    </div>
@endif
