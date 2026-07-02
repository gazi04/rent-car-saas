{{-- Vehicle title bar: name, year, category badge, quick specs. --}}
<div>
    <a href="{{ route('public.vehicles') }}" class="text-sm text-primary hover:underline">← {{ __('booking.back_to_fleet') }}</a>

    <div class="mt-2 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $vehicle->name }}</h1>
        <span class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-secondary">
            {{ $vehicle->category->getLabel() }}
        </span>
    </div>

    <div class="mt-2 flex items-center gap-4 text-sm text-gray-500">
        <span>{{ $vehicle->year }}</span>
        <span>{{ __('booking.seats', ['count' => $vehicle->seats]) }}</span>
        <span>{{ $vehicle->fuel_type->getLabel() }}</span>
        <span>{{ $vehicle->transmission->getLabel() }}</span>
    </div>
</div>
