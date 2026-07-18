<?php

use App\Enums\PlanFeature;
use App\Enums\VehicleStatus;
use App\Models\Review;
use App\Models\Vehicle;
use App\Services\WaitlistService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.public')] #[Title('Vehicle Details')] class extends Component {
    public Vehicle $vehicle;

    /**
     * Only is_public gates the page — status does not (backlog #3).
     *
     * An unavailable vehicle still has a real page, because that page is the only
     * place a visitor can ask to hear when it comes back. is_public stays absolute:
     * hiding a vehicle is a deliberate act and must keep meaning hidden, so there
     * is nothing to subscribe to. The booking page and the availability endpoint
     * keep the strict check — you can look, but you cannot book.
     */
    public function mount(Vehicle $vehicle): void
    {
        abort_unless($vehicle->is_public, 404);

        $this->vehicle = $vehicle;
    }

    #[Computed]
    public function isBookable(): bool
    {
        return $this->vehicle->status === VehicleStatus::Available;
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

    // ── Waitlist (backlog #2) ───────────────────────────────────────────────────

    public string $waitlistStart = '';

    public string $waitlistEnd = '';

    public string $waitlistName = '';

    public string $waitlistEmail = '';

    public string $waitlistPhone = '';

    public bool $waitlistJoined = false;

    public ?string $waitlistError = null;

    /**
     * A waitlist is about dates, so it only makes sense while the vehicle is
     * actually on the road. Once it is off, dates are moot and the stock alert
     * takes over — the two panels are never shown together.
     */
    #[Computed]
    public function showsWaitlist(): bool
    {
        return $this->isBookable
            && (tenant()?->allowsFeature(PlanFeature::Waitlist)
                ?? (bool) PlanFeature::Waitlist->default());
    }

    /**
     * The booking calendar disables taken dates, so a visitor can never ask for
     * them — this panel is the only way to express that want, with its own
     * pickers where every date is selectable.
     */
    public function joinWaitlist(): void
    {
        // Re-assert the gate server-side. Unlike Filament — which re-checks
        // Page::canAccess() on every hydration and refuses to mount a hidden
        // action — a public Livewire SFC has no authorization hook, so this
        // method is directly callable over the wire and hiding the panel proves
        // nothing.
        abort_unless($this->showsWaitlist, 404);

        // Nothing else in this app throttles a public action, and this one takes
        // an email address and causes mail. Keyed per vehicle + IP.
        $key = 'waitlist-join:'.$this->vehicle->id.':'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            $this->waitlistError = __('booking.waitlist_throttled');

            return;
        }

        $this->validate([
            'waitlistStart' => ['required', 'date', 'after_or_equal:today'],
            'waitlistEnd' => ['required', 'date', 'after:waitlistStart'],
            'waitlistName' => ['required', 'string', 'max:255'],
            'waitlistEmail' => ['required', 'email', 'max:255'],
            'waitlistPhone' => ['nullable', 'string', 'max:50'],
        ]);

        RateLimiter::hit($key, decaySeconds: 3600);

        try {
            app(WaitlistService::class)->join($this->vehicle, [
                'name' => $this->waitlistName,
                'email' => $this->waitlistEmail,
                'phone' => $this->waitlistPhone ?: null,
                'start_date' => $this->waitlistStart,
                'end_date' => $this->waitlistEnd,
                'locale' => app()->getLocale(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Already waiting on this exact vehicle+dates. Show the same
            // thank-you rather than confirming the address is on the list.
        } catch (\InvalidArgumentException) {
            $this->waitlistError = __('booking.waitlist_invalid_dates');

            return;
        }

        $this->waitlistJoined = true;
        $this->waitlistError = null;
    }

    // ── Stock alert (backlog #3) ────────────────────────────────────────────────

    public string $stockAlertName = '';

    public string $stockAlertEmail = '';

    public string $stockAlertPhone = '';

    public bool $stockAlertJoined = false;

    public ?string $stockAlertError = null;

    #[Computed]
    public function showsStockAlert(): bool
    {
        return ! $this->isBookable
            && (tenant()?->allowsFeature(PlanFeature::StockAlert)
                ?? (bool) PlanFeature::StockAlert->default());
    }

    /**
     * No dates asked for: the want here is the vehicle itself, whenever it returns.
     * That is what a null range means in waitlist_entries.
     */
    public function joinStockAlert(): void
    {
        // Re-assert the gate server-side. Unlike Filament — which re-checks
        // Page::canAccess() on every hydration and refuses to mount a hidden
        // action — a public Livewire SFC has no authorization hook, so this
        // method is directly callable over the wire and hiding the panel proves
        // nothing. This also covers a bookable vehicle, where the panel is gone
        // but the wire call would otherwise still land.
        abort_unless($this->showsStockAlert, 404);

        $key = 'stock-alert-join:'.$this->vehicle->id.':'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            $this->stockAlertError = __('booking.stock_alert_throttled');

            return;
        }

        $this->validate([
            'stockAlertName' => ['required', 'string', 'max:255'],
            'stockAlertEmail' => ['required', 'email', 'max:255'],
            'stockAlertPhone' => ['nullable', 'string', 'max:50'],
        ]);

        RateLimiter::hit($key, decaySeconds: 3600);

        try {
            app(WaitlistService::class)->joinStockAlert($this->vehicle, [
                'name' => $this->stockAlertName,
                'email' => $this->stockAlertEmail,
                'phone' => $this->stockAlertPhone ?: null,
                'locale' => app()->getLocale(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Already waiting on this vehicle. Show the same thank-you rather than
            // confirming the address is on the list.
        }

        $this->stockAlertJoined = true;
        $this->stockAlertError = null;
    }
}; ?>

<div>
    @php
        $vehicle = $this->vehicle;
        $photos = $this->photos;
        $isBookable = $this->isBookable;
    @endphp

    @include('pages.public.partials.vehicle-show.' . $this->pageLayout)

    @include('pages.public.partials.vehicle-show._reviews')

    @if ($this->showsWaitlist)
        @include('pages.public.partials.vehicle-show._waitlist')
    @endif

    @if ($this->showsStockAlert)
        @include('pages.public.partials.vehicle-show._stock-alert')
    @endif
</div>
