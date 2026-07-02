@php
    /** Filter controls. $vertical=true stacks them for sidebar placement. */
    $vertical = $vertical ?? false;
@endphp

<div class="bg-white rounded-lg border border-gray-200 p-4">
    <div class="{{ $vertical ? 'space-y-4' : 'grid grid-cols-2 sm:grid-cols-4 gap-4 items-end' }}">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('booking.filters_category') }}</label>
            <select wire:model.live="category" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                <option value="">{{ __('booking.filters_any') }}</option>
                @foreach ($this->categories as $cat)
                    <option value="{{ $cat->value }}">{{ $cat->getLabel() }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('booking.filters_transmission') }}</label>
            <select wire:model.live="transmission" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                <option value="">{{ __('booking.filters_any') }}</option>
                @foreach ($this->transmissions as $tr)
                    <option value="{{ $tr->value }}">{{ $tr->getLabel() }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('booking.filters_price_min') }}</label>
            <input wire:model.live.debounce.300ms="minPrice" type="number" min="0" placeholder="€0"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('booking.filters_price_max') }}</label>
            <input wire:model.live.debounce.300ms="maxPrice" type="number" min="0" placeholder="€999"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>
    </div>

    @if ($category || $transmission || $minPrice !== '' || $maxPrice !== '')
        <div class="mt-3 {{ $vertical ? '' : 'text-right' }}">
            <button wire:click="resetFilters" class="text-sm text-primary hover:underline">
                {{ __('booking.filters_reset') }}
            </button>
        </div>
    @endif
</div>
