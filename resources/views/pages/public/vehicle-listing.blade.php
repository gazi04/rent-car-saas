<?php

use App\Enums\Transmission;
use App\Enums\VehicleCategory;
use App\Enums\VehicleStatus;
use App\Models\Vehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.public')] #[Title('Browse Fleet')] class extends Component {
    use WithPagination;

    #[Url]
    public string $category = '';

    #[Url]
    public string $transmission = '';

    #[Url]
    public string $minPrice = '';

    #[Url]
    public string $maxPrice = '';

    public function updatedCategory(): void { $this->resetPage(); }
    public function updatedTransmission(): void { $this->resetPage(); }
    public function updatedMinPrice(): void { $this->resetPage(); }
    public function updatedMaxPrice(): void { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->category = '';
        $this->transmission = '';
        $this->minPrice = '';
        $this->maxPrice = '';
        $this->resetPage();
    }

    #[Computed]
    public function vehicles(): LengthAwarePaginator
    {
        return Vehicle::query()
            ->where('is_public', true)
            ->where('status', VehicleStatus::Available)
            ->with('media')
            ->when($this->category, fn ($q) => $q->where('category', $this->category))
            ->when($this->transmission, fn ($q) => $q->where('transmission', $this->transmission))
            ->when($this->minPrice !== '', fn ($q) => $q->where('daily_rate', '>=', (float) $this->minPrice))
            ->when($this->maxPrice !== '', fn ($q) => $q->where('daily_rate', '<=', (float) $this->maxPrice))
            ->paginate(12);
    }

    /** @return array<int, VehicleCategory> */
    #[Computed]
    public function categories(): array
    {
        return VehicleCategory::cases();
    }

    /** @return array<int, Transmission> */
    #[Computed]
    public function transmissions(): array
    {
        return Transmission::cases();
    }
}; ?>

<div>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ __('booking.browse_fleet') }}</h1>

    {{-- Filters --}}
    <div class="bg-white rounded-lg border border-gray-200 p-4 mb-8">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 items-end">
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
            <div class="mt-3 text-right">
                <button wire:click="resetFilters" class="text-sm text-primary hover:underline">
                    {{ __('booking.filters_reset') }}
                </button>
            </div>
        @endif
    </div>

    {{-- Vehicle grid --}}
    @if ($this->vehicles->isEmpty())
        <p class="text-center text-gray-500 py-16">{{ __('booking.no_vehicles') }}</p>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($this->vehicles as $vehicle)
                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
                    {{-- Cover photo --}}
                    <div class="aspect-video bg-gray-100 overflow-hidden">
                        @if ($vehicle->getFirstMedia('vehicle_photos'))
                            <img src="{{ $vehicle->getFirstMediaUrl('vehicle_photos', 'web') }}"
                                 alt="{{ $vehicle->name }}"
                                 class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-400">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                                </svg>
                            </div>
                        @endif
                    </div>

                    <div class="p-4">
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
            @endforeach
        </div>

        <div class="mt-8">
            {{ $this->vehicles->links() }}
        </div>
    @endif
</div>
