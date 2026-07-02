{{-- Two-Column Layout: horizontal cards, image column beside details column. --}}
<div>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ __('booking.browse_fleet') }}</h1>

    <div class="mb-8">
        @include('pages.public.partials.vehicles._filters')
    </div>

    @if ($this->vehicles->isEmpty())
        <p class="text-center text-gray-500 py-16">{{ __('booking.no_vehicles') }}</p>
    @else
        <div class="space-y-6 max-w-4xl">
            @foreach ($this->vehicles as $vehicle)
                <div wire:key="vehicle-{{ $vehicle->id }}">
                    @include('pages.public.partials.vehicles._card', ['horizontal' => true])
                </div>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $this->vehicles->links() }}
        </div>
    @endif
</div>
