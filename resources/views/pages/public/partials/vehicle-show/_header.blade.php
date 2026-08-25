{{-- Vehicle title bar: name, year, category badge, quick specs. --}}
<div>
    <a href="{{ route('public.vehicles') }}"
       class="inline-flex min-h-11 items-center gap-1 text-sm text-primary hover:underline">
        <flux:icon.arrow-left class="size-4" />{{ __('booking.back_to_fleet') }}
    </a>

    <div class="mt-2 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-bold text-ink sm:text-3xl">{{ $vehicle->name }}</h1>
        <x-ui.badge>{{ $vehicle->category->getLabel() }}</x-ui.badge>
    </div>

    {{-- Wraps: four unbreakable spans in a row overflowed a 320px screen. --}}
    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-ink-muted">
        <span>{{ $vehicle->year }}</span>
        <span>{{ __('booking.seats', ['count' => $vehicle->seats]) }}</span>
        <span>{{ $vehicle->fuel_type->getLabel() }}</span>
        <span>{{ $vehicle->transmission->getLabel() }}</span>
    </div>
</div>
