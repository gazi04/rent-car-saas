<?php

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Enums\VehicleCategory;
use App\Enums\VehicleStatus;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
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
    public string $fuelType = '';

    #[Url]
    public string $seats = '';

    #[Url]
    public string $year = '';

    #[Url]
    public string $minPrice = '';

    #[Url]
    public string $maxPrice = '';

    #[Url]
    public string $search = '';

    #[Url(as: 'sort')]
    public string $sort = '';

    #[Url(as: 'start_date')]
    public string $startDate = '';

    #[Url(as: 'end_date')]
    public string $endDate = '';

    public function updatedCategory(): void { $this->resetPage(); }
    public function updatedTransmission(): void { $this->resetPage(); }
    public function updatedFuelType(): void { $this->resetPage(); }
    public function updatedSeats(): void { $this->resetPage(); }
    public function updatedYear(): void { $this->resetPage(); }
    public function updatedMinPrice(): void { $this->resetPage(); }
    public function updatedMaxPrice(): void { $this->resetPage(); }
    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedSort(): void { $this->resetPage(); }

    #[On('listing-start-selected')]
    public function onListingStartSelected(string $date): void
    {
        $this->startDate = $date;
        $this->resetPage();
    }

    #[On('listing-end-selected')]
    public function onListingEndSelected(string $date): void
    {
        $this->endDate = $date;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->category = '';
        $this->transmission = '';
        $this->fuelType = '';
        $this->seats = '';
        $this->year = '';
        $this->minPrice = '';
        $this->maxPrice = '';
        $this->search = '';
        $this->sort = '';
        $this->startDate = '';
        $this->endDate = '';
        $this->resetPage();
    }

    /**
     * The date-range filter is driven entirely by query-string input (either typed
     * directly or forwarded from another page), so it must never trust the raw
     * strings — a malformed value should just disable the filter, not 500.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    private function parsedDateRange(): ?array
    {
        if ($this->startDate === '' || $this->endDate === '') {
            return null;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->startDate) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->endDate)) {
            return null;
        }

        try {
            $start = CarbonImmutable::createFromFormat('Y-m-d', $this->startDate)->startOfDay();
            $end = CarbonImmutable::createFromFormat('Y-m-d', $this->endDate)->startOfDay();
        } catch (\Exception) {
            return null;
        }

        return $start->lt($end) ? [$start, $end] : null;
    }

    /**
     * Unavailable vehicles are listed, not hidden (backlog #3) — their page is
     * where the stock alert lives, so removing them from the fleet would leave
     * nothing to click. They sort last so the bookable fleet still leads, and
     * their cards say plainly that they cannot be booked. is_public still hides.
     */
    #[Computed]
    public function vehicles(): LengthAwarePaginator
    {
        return Vehicle::query()
            ->where('is_public', true)
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [VehicleStatus::Available->value])
            ->when($this->sort === 'price_asc', fn ($q) => $q->orderBy('daily_rate'))
            ->when($this->sort === 'price_desc', fn ($q) => $q->orderByDesc('daily_rate'))
            ->when($this->sort === '' || $this->sort === 'newest', fn ($q) => $q->orderByDesc('created_at'))
            ->with('media')
            ->when($this->category, fn ($q) => $q->where('category', $this->category))
            ->when($this->transmission, fn ($q) => $q->where('transmission', $this->transmission))
            ->when($this->fuelType, fn ($q) => $q->where('fuel_type', $this->fuelType))
            ->when($this->seats !== '', fn ($q) => $q->where('seats', '>=', (int) $this->seats))
            ->when($this->year !== '', fn ($q) => $q->where('year', (int) $this->year))
            ->when($this->minPrice !== '', fn ($q) => $q->where('daily_rate', '>=', (float) $this->minPrice))
            ->when($this->maxPrice !== '', fn ($q) => $q->where('daily_rate', '<=', (float) $this->maxPrice))
            ->when($this->search !== '', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($this->search).'%']))
            ->when($this->parsedDateRange(), fn ($q, array $range) => $q->availableBetween($range[0], $range[1]))
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

    /** @return array<int, FuelType> */
    #[Computed]
    public function fuelTypes(): array
    {
        return FuelType::cases();
    }

    /** Seat-count thresholds offered in the filter — matches typical rental-fleet groupings. */
    #[Computed]
    public function seatOptions(): array
    {
        return [2, 4, 5, 7];
    }

    /** @return array<int, int> */
    #[Computed]
    public function years(): array
    {
        return Vehicle::query()
            ->where('is_public', true)
            ->whereNotNull('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->all();
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
