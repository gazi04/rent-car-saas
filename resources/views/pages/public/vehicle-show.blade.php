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

    // ── Waitlist (backlog #2) ───────────────────────────────────────────────────

    public string $waitlistStart = '';

    public string $waitlistEnd = '';

    public string $waitlistName = '';

    public string $waitlistEmail = '';

    public string $waitlistPhone = '';

    public bool $waitlistJoined = false;

    public ?string $waitlistError = null;

    #[Computed]
    public function showsWaitlist(): bool
    {
        return tenant()?->allowsFeature(PlanFeature::Waitlist)
            ?? (bool) PlanFeature::Waitlist->default();
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
}; ?>

<div>
    @php
        $vehicle = $this->vehicle;
        $photos = $this->photos;
    @endphp

    @include('pages.public.partials.vehicle-show.' . $this->pageLayout)

    @include('pages.public.partials.vehicle-show._reviews')

    @if ($this->showsWaitlist)
        @include('pages.public.partials.vehicle-show._waitlist')
    @endif
</div>
