@php
    /** Filter controls. The sidebar variant went with the f-shape layout. */
    $hasFilters = $category || $transmission || $fuelType || $seats !== '' || $year !== ''
        || $minPrice !== '' || $maxPrice !== '' || $search !== '' || $sort !== ''
        || $startDate !== '' || $endDate !== '';
@endphp

<div class="rounded-panel border border-line bg-surface-raised p-4">
    {{-- Row 1: search, pickup/return dates, sort — the controls customers reach for first. --}}
    <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-muted">{{ __('booking.filters_search') }}</label>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="{{ __('booking.filters_search_placeholder') }}"
                class="w-full rounded-control border border-line-strong bg-surface-raised px-3 py-2 text-sm text-ink placeholder:text-ink-faint focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
        </div>

        <div>
            <label for="listing-start-picker" class="mb-1 block text-xs font-medium text-ink-muted">{{ __('booking.filters_pickup_date') }}</label>
            {{-- Plain flatpickr instance (dd/mm/yyyy), no wire:model — reports back
                 via a dispatched event, see resources/js/vehicle-filters.js. --}}
            <input id="listing-start-picker" type="text" placeholder="dd/mm/yyyy"
                class="w-full rounded-control border border-line-strong bg-surface-raised px-3 py-2 text-sm text-ink placeholder:text-ink-faint focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
        </div>

        <div>
            <label for="listing-end-picker" class="mb-1 block text-xs font-medium text-ink-muted">{{ __('booking.filters_return_date') }}</label>
            <input id="listing-end-picker" type="text" placeholder="dd/mm/yyyy"
                class="w-full rounded-control border border-line-strong bg-surface-raised px-3 py-2 text-sm text-ink placeholder:text-ink-faint focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-ink-muted">{{ __('booking.filters_sort') }}</label>
            <select wire:model.live="sort" class="w-full rounded-control border border-line-strong bg-surface-raised px-3 py-2 text-sm text-ink placeholder:text-ink-faint focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                <option value="newest">{{ __('booking.sort_newest') }}</option>
                <option value="price_asc">{{ __('booking.sort_price_asc') }}</option>
                <option value="price_desc">{{ __('booking.sort_price_desc') }}</option>
            </select>
        </div>
    </div>

    {{-- Row 2: refine filters. --}}
    <div class="mt-4 grid grid-cols-2 items-end gap-4 sm:grid-cols-3 lg:grid-cols-6">
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-muted">{{ __('booking.filters_category') }}</label>
            <select wire:model.live="category" class="w-full rounded-control border border-line-strong bg-surface-raised px-3 py-2 text-sm text-ink placeholder:text-ink-faint focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                <option value="">{{ __('booking.filters_any') }}</option>
                @foreach ($this->categories as $cat)
                    <option value="{{ $cat->value }}">{{ $cat->getLabel() }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-ink-muted">{{ __('booking.filters_transmission') }}</label>
            <select wire:model.live="transmission" class="w-full rounded-control border border-line-strong bg-surface-raised px-3 py-2 text-sm text-ink placeholder:text-ink-faint focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                <option value="">{{ __('booking.filters_any') }}</option>
                @foreach ($this->transmissions as $tr)
                    <option value="{{ $tr->value }}">{{ $tr->getLabel() }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-ink-muted">{{ __('booking.filters_fuel_type') }}</label>
            <select wire:model.live="fuelType" class="w-full rounded-control border border-line-strong bg-surface-raised px-3 py-2 text-sm text-ink placeholder:text-ink-faint focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                <option value="">{{ __('booking.filters_any') }}</option>
                @foreach ($this->fuelTypes as $fuel)
                    <option value="{{ $fuel->value }}">{{ $fuel->getLabel() }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-ink-muted">{{ __('booking.filters_seats') }}</label>
            <select wire:model.live="seats" class="w-full rounded-control border border-line-strong bg-surface-raised px-3 py-2 text-sm text-ink placeholder:text-ink-faint focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                <option value="">{{ __('booking.filters_any') }}</option>
                @foreach ($this->seatOptions as $count)
                    <option value="{{ $count }}">{{ $count }}+</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-ink-muted">{{ __('booking.filters_year') }}</label>
            <select wire:model.live="year" class="w-full rounded-control border border-line-strong bg-surface-raised px-3 py-2 text-sm text-ink placeholder:text-ink-faint focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                <option value="">{{ __('booking.filters_any') }}</option>
                @foreach ($this->years as $y)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-ink-muted">{{ __('booking.filters_price_min') }}</label>
                <input wire:model.live.debounce.300ms="minPrice" type="number" min="0" placeholder="{{ __('booking.currency_symbol') }}0"
                    class="w-full rounded-control border border-line-strong bg-surface-raised px-3 py-2 text-sm text-ink placeholder:text-ink-faint focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-ink-muted">{{ __('booking.filters_price_max') }}</label>
                <input wire:model.live.debounce.300ms="maxPrice" type="number" min="0" placeholder="{{ __('booking.currency_symbol') }}999"
                    class="w-full rounded-control border border-line-strong bg-surface-raised px-3 py-2 text-sm text-ink placeholder:text-ink-faint focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
            </div>
        </div>
    </div>

    @if ($hasFilters)
        <div class="mt-3 text-right">
            <button wire:click="resetFilters" class="inline-flex min-h-11 items-center text-sm text-primary hover:underline">
                {{ __('booking.filters_reset') }}
            </button>
        </div>
    @endif
</div>

