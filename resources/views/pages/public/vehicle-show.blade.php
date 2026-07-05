<?php

use App\Enums\VehicleStatus;
use App\Models\Review;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.public')] #[Title('Vehicle Details')] class extends Component {
    public Vehicle $vehicle;

    public function mount(Vehicle $vehicle): void
    {
        abort_unless($vehicle->is_public && $vehicle->status === VehicleStatus::Available, 404);

        $this->vehicle = $vehicle;
    }

    /** @return array<int, array{web: string, thumb: string}> */
    #[Computed]
    public function photos(): array
    {
        return $this->vehicle->getMedia('vehicle_photos')
            ->map(fn ($media): array => [
                'web' => $media->getUrl('web'),
                'thumb' => $media->getUrl('thumb'),
            ])
            ->all();
    }

    /**
     * Approved reviews for this vehicle, newest first. Free on every plan so
     * accumulated reviews are always visible. Auto tenant-scoped via BelongsToTenant.
     *
     * @return Collection<int, Review>
     */
    #[Computed]
    public function reviews(): Collection
    {
        return Review::query()
            ->where('vehicle_id', $this->vehicle->id)
            ->where('is_approved', true)
            ->latest('submitted_at')
            ->get();
    }

    #[Computed]
    public function averageRating(): ?float
    {
        $reviews = $this->reviews;

        return $reviews->isNotEmpty() ? round((float) $reviews->avg('rating'), 1) : null;
    }

    #[Computed]
    public function pageLayout(): string
    {
        /** @var array<int, string> $allowed */
        $allowed = config('branding.layouts.vehicle_show', []);
        $layout = (string) tenant()?->setting('layout_vehicle_show');

        return in_array($layout, $allowed, true)
            ? $layout
            : (string) config('branding.defaults.layout_vehicle_show');
    }
}; ?>

<div>
    @php
        $vehicle = $this->vehicle;
        $photos = $this->photos;
    @endphp

    @include('pages.public.partials.vehicle-show.' . $this->pageLayout)

    @include('pages.public.partials.vehicle-show._reviews')
</div>
