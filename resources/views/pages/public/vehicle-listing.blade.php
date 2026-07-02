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

    #[Computed]
    public function pageLayout(): string
    {
        /** @var array<int, string> $allowed */
        $allowed = config('branding.layouts.vehicles', []);
        $layout = (string) tenant()?->setting('layout_vehicles');

        return in_array($layout, $allowed, true)
            ? $layout
            : (string) config('branding.defaults.layout_vehicles');
    }
}; ?>

<div>
    @include('pages.public.partials.vehicles.' . $this->pageLayout)
</div>
