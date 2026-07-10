<?php

use App\Enums\PlanFeature;
use App\Enums\VehicleStatus;
use App\Models\Review;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.public')] #[Title('Home')] class extends Component {
    /** @return Collection<int, Vehicle> */
    #[Computed]
    public function featuredVehicles(): Collection
    {
        return Vehicle::query()
            ->where('is_public', true)
            ->where('status', VehicleStatus::Available)
            ->with('media')
            ->latest()
            ->limit(6)
            ->get();
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function content(): array
    {
        $tenant = tenant();

        return [
            'hero_heading' => $tenant?->localizedSetting('home_hero_heading') ?: __('booking.home_hero_heading'),
            'hero_subheading' => $tenant?->localizedSetting('home_hero_subheading') ?: __('booking.home_hero_subheading'),
            'hero_cta' => $tenant?->localizedSetting('home_hero_cta_label') ?: __('booking.home_hero_cta'),
            'about_title' => $tenant?->localizedSetting('home_about_title') ?: __('booking.home_about_title'),
            'about_text' => $tenant?->localizedSetting('home_about_text') ?: __('booking.home_about_text'),
            'services' => collect([1, 2, 3])->map(fn (int $i): array => [
                'title' => $tenant?->localizedSetting("home_service_{$i}_title") ?: __("booking.home_service_{$i}_title"),
                'text' => $tenant?->localizedSetting("home_service_{$i}_text") ?: __("booking.home_service_{$i}_text"),
            ])->all(),
        ];
    }

    /**
     * Whether to show the home-page review showcase — gated (Standard/Pro).
     */
    #[Computed]
    public function showsReviewShowcase(): bool
    {
        return (tenant()?->allowsFeature(PlanFeature::Reviews) ?? (bool) PlanFeature::Reviews->default())
            && $this->showcaseReviews->isNotEmpty();
    }

    /**
     * A few featured approved reviews across the whole fleet. Auto tenant-scoped.
     *
     * @return Collection<int, Review>
     */
    #[Computed]
    public function showcaseReviews(): Collection
    {
        return Review::query()
            ->where('is_approved', true)
            ->with('vehicle')
            ->latest('submitted_at')
            ->limit(6)
            ->get();
    }

    #[Computed]
    public function averageRating(): ?float
    {
        $average = Review::query()->where('is_approved', true)->avg('rating');

        return $average !== null ? round((float) $average, 1) : null;
    }

    #[Computed]
    public function reviewsCount(): int
    {
        return Review::query()->where('is_approved', true)->count();
    }

    #[Computed]
    public function pageLayout(): string
    {
        /** @var array<int, string> $allowed */
        $allowed = config('branding.layouts.home', []);
        $layout = (string) tenant()?->setting('layout_home');

        return in_array($layout, $allowed, true)
            ? $layout
            : (string) config('branding.defaults.layout_home');
    }
}; ?>

<div>
    @php
        $content = $this->content;
        $vehicles = $this->featuredVehicles;
    @endphp

    @include('pages.public.partials.home.' . $this->pageLayout)

    @if ($this->showsReviewShowcase)
        @include('pages.public.partials.home._reviews')
    @endif
</div>
