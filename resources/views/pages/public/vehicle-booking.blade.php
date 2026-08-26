<?php

use App\Enums\PlanFeature;
use App\Enums\VehicleStatus;
use App\Exceptions\InvalidBookingWindowException;
use App\Exceptions\PromoCodeInvalidException;
use App\Exceptions\VehicleNotAvailableException;
use App\Models\PromoCode;
use App\Models\Vehicle;
use Carbon\CarbonInterface;
use App\Services\BookingService;
use App\Services\PricingService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.public')] #[Title('Book a Vehicle')] class extends Component {
    public Vehicle $vehicle;

    public int $step = 1;

    // Step 1 — dates
    public string $startDate = '';

    public string $endDate = '';

    /** @var array<string, mixed>|null */
    public ?array $priceBreakdown = null;

    /**
     * Dates forwarded from the listing page's date-range filter (via the
     * vehicle-show page's CTA link) — read-only inputs, never written back to
     * the query string. mount() copies a valid pair into startDate/endDate so
     * the customer doesn't have to re-pick what they already chose upstream.
     */
    #[Url(as: 'start_date')]
    public string $prefillStartDate = '';

    #[Url(as: 'end_date')]
    public string $prefillEndDate = '';

    // Promo code (gated feature)
    public string $promoCode = '';

    public ?string $promoNotice = null;

    public ?string $promoError = null;

    // Step 2 — customer details
    public string $customerName = '';

    public string $customerPhone = '';

    public string $customerEmail = '';

    public string $pickupLocation = '';

    public string $notes = '';

    public bool $slotTaken = false;

    public ?string $submitError = null;

    public function mount(Vehicle $vehicle): void
    {
        abort_unless($vehicle->is_public && $vehicle->status === VehicleStatus::Available, 404);
        $this->vehicle = $vehicle;

        if ($this->prefillStartDate !== '' && $this->prefillEndDate !== '') {
            try {
                $start = Date::parse($this->prefillStartDate);
                $end = Date::parse($this->prefillEndDate);
            } catch (\Exception) {
                return;
            }

            // A hand-edited ?start_date= is as untrusted as any other input: an
            // unbookable range is dropped rather than adopted, so the wizard
            // never opens on dates it would refuse at submit.
            if ($this->windowIsBookable($start, $end)) {
                $this->startDate = $this->prefillStartDate;
                $this->endDate = $this->prefillEndDate;
                $this->refreshPrice();
            }
        }
    }

    /**
     * Dispatched by resources/js/booking-form.js. A Livewire event is browser
     * input, so the pair is re-checked here — flatpickr's minDate/maxDate are
     * feedback, not a guard, and a forged event must not seed a price preview
     * for a window the server would refuse.
     */
    #[On('dates-selected')]
    public function onDatesSelected(string $start, string $end): void
    {
        $this->slotTaken = false;

        try {
            $parsedStart = Date::parse($start);
            $parsedEnd = Date::parse($end);
        } catch (\Exception) {
            return;
        }

        if (! $this->windowIsBookable($parsedStart, $parsedEnd)) {
            $this->priceBreakdown = null;

            return;
        }

        $this->startDate = $start;
        $this->endDate = $end;
        $this->refreshPrice();
    }

    public function nextStep(): void
    {
        $this->slotTaken = false;

        if ($this->step === 1) {
            $this->validate($this->dateRules(), $this->dateMessages());
        } elseif ($this->step === 2) {
            $this->validate([
                'customerName' => 'required|string|max:255',
                'customerPhone' => 'required|string|max:50',
                'customerEmail' => 'required|email|max:255',
                'pickupLocation' => 'nullable|string|max:255',
                'notes' => 'nullable|string|max:1000',
            ]);
        }

        $this->step++;
    }

    public function prevStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function applyPromo(): void
    {
        $this->refreshPrice();
    }

    public function submit(): void
    {
        // Highest-impact public action in the app (real row, vehicle-date lock,
        // operator email) — keyed per vehicle + IP, same pattern as waitlist/
        // stock-alert. Checked before validate() so invalid attempts are free;
        // hit() lands after validate() but before the actual booking attempt,
        // so a genuine customer retrying after a slot conflict still spends budget.
        $key = 'booking-submit:'.$this->vehicle->id.':'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            $this->submitError = __('booking.submit_throttled');

            return;
        }

        $this->validate([
            ...$this->dateRules(),
            'customerName' => 'required|string|max:255',
            'customerPhone' => 'required|string|max:50',
            'customerEmail' => 'required|email|max:255',
            'pickupLocation' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ], $this->dateMessages());

        $this->slotTaken = false;
        RateLimiter::hit($key, decaySeconds: 3600);

        try {
            $booking = resolve(BookingService::class)->create([
                'vehicle_id' => $this->vehicle->id,
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
                'customer_name' => $this->customerName,
                'customer_phone' => $this->customerPhone,
                'customer_email' => $this->customerEmail ?: null,
                'pickup_location' => $this->pickupLocation ?: null,
                'notes' => $this->notes ?: null,
                'promo_code' => $this->promoCode ?: null,
            ]);

            $this->redirect(route('public.booking.confirmation', $booking->reference), navigate: false);
        } catch (VehicleNotAvailableException) {
            $this->slotTaken = true;
            $this->step = 1;
            $this->startDate = '';
            $this->endDate = '';
            $this->priceBreakdown = null;
        } catch (InvalidBookingWindowException) {
            // A wizard left open across midnight, or forged dates. Back to step 1
            // for new dates — the customer's typed details are kept.
            $this->submitError = __('booking.date_window_invalid');
            $this->step = 1;
            $this->priceBreakdown = null;
        } catch (PromoCodeInvalidException) {
            $this->promoError = __('booking.promo_invalid');
        }
    }

    /**
     * The one definition of a bookable window, shared by both validate() calls
     * so they cannot drift. The real guard is BookingService::lockAndValidate();
     * these rules exist to say so inline on step 1 instead of at the last click.
     *
     * @return array<string, array<int, string>>
     */
    private function dateRules(): array
    {
        $rules = [
            'startDate' => ['required', 'date', 'after_or_equal:today'],
            'endDate' => ['required', 'date', 'after:startDate'],
        ];

        if ($this->startDate !== '') {
            try {
                $latestEnd = Date::parse($this->startDate)->startOfDay()->addDays($this->maxRentalDays());
                $rules['endDate'][] = 'before_or_equal:'.$latestEnd->toDateString();
            } catch (\Exception) {
                // Unparseable start — the 'date' rule on startDate reports it.
            }
        }

        return $rules;
    }

    /** @return array<string, string> */
    private function dateMessages(): array
    {
        // The repo ships no lang/*/validation.php, so the framework defaults are
        // English-only — unacceptable on an Albanian-default storefront.
        return [
            'startDate.after_or_equal' => __('booking.date_in_past'),
            'endDate.before_or_equal' => __('booking.date_range_too_long', ['count' => $this->maxRentalDays()]),
        ];
    }

    private function maxRentalDays(): int
    {
        return Config::integer('bookings.max_rental_days');
    }

    /** Whether a parsed pair is one the server would accept — the untrusted-input gate. */
    private function windowIsBookable(CarbonInterface $start, CarbonInterface $end): bool
    {
        if (! $start->lt($end)) {
            return false;
        }

        if ($start->copy()->startOfDay()->lt(today())) {
            return false;
        }

        return resolve(PricingService::class)->rentalDays($start, $end) <= $this->maxRentalDays();
    }

    /**
     * Resolve the entered promo code for the price preview (read-only — no lock,
     * no usage increment; the authoritative check + redemption happen in
     * BookingService at submit). Sets promoNotice / promoError.
     */
    private function previewPromo(): ?PromoCode
    {
        $this->promoNotice = null;
        $this->promoError = null;

        $code = strtoupper(trim($this->promoCode));

        if ($code === '' || ! (tenant()?->allowsFeature(PlanFeature::PromoCodes) ?? (bool) PlanFeature::PromoCodes->default())) {
            return null;
        }

        $promo = PromoCode::query()->where('code', $code)->first();

        if ($promo === null || ! $promo->isCurrentlyValid()) {
            $this->promoError = __('booking.promo_invalid');

            return null;
        }

        $this->promoNotice = __('booking.promo_applied');

        return $promo;
    }

    private function refreshPrice(): void
    {
        if (! $this->startDate || ! $this->endDate) {
            $this->priceBreakdown = null;

            return;
        }

        $start = Date::parse($this->startDate);
        $end = Date::parse($this->endDate);

        if (! $start->lt($end)) {
            $this->priceBreakdown = null;

            return;
        }

        $pricing = resolve(PricingService::class)->calculate($this->vehicle, $start, $end, $this->previewPromo());

        $this->priceBreakdown = [
            'rate_type' => $pricing['rate_type']->getLabel(),
            'subtotal' => $pricing['subtotal'],
            'discount' => $pricing['discount'],
            'total' => $pricing['total'],
            'deposit' => $pricing['deposit'],
        ];
    }
}; ?>

<x-ui.container size="narrow" class="py-8 sm:py-12">
    {{-- Slot taken flash --}}
    @if ($slotTaken)
        <x-ui.alert tone="critical" class="mb-6">{{ __('booking.slot_taken') }}</x-ui.alert>
    @endif

    {{-- Vehicle summary.

         This block was the worst mobile bug on the site: a fixed w-32 image
         beside an unwrappable four-span spec row inside a bare `flex`, which
         forced the page to 446px on a 375px screen and clipped the text. It now
         stacks below sm, and the spec row wraps. --}}
    <div class="mb-8 flex flex-col gap-4 rounded-panel border border-line bg-surface-raised p-4 sm:flex-row sm:gap-6 sm:p-6">
        @if ($vehicle->getFirstMedia('vehicle_photos'))
            <div class="h-40 w-full shrink-0 overflow-hidden rounded-control bg-surface-sunken sm:h-24 sm:w-32">
                <img src="{{ $vehicle->getFirstMediaUrl('vehicle_photos', 'thumb') }}"
                     alt="{{ $vehicle->name }}" class="h-full w-full object-cover">
            </div>
        @endif

        <div class="min-w-0">
            <h1 class="text-xl font-bold text-ink">{{ $vehicle->name }}</h1>
            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink-muted">
                <span>{{ $vehicle->category->getLabel() }}</span>
                <span>{{ __('booking.seats', ['count' => $vehicle->seats]) }}</span>
                <span>{{ $vehicle->fuel_type->getLabel() }}</span>
                <span>{{ $vehicle->transmission->getLabel() }}</span>
            </div>
            @php($vehicleDescription = $vehicle->descriptionFor())
            @if ($vehicleDescription !== '')
                <p class="mt-2 text-sm text-ink-muted">{{ $vehicleDescription }}</p>
            @endif
        </div>
    </div>

    {{-- Step indicators. Labels are hidden below sm — three of them plus two
         connectors cannot fit on a 320px screen, and the numbered circles plus
         the heading below already say where you are. --}}
    <ol class="mb-8 flex items-center gap-2">
        @foreach ([1 => __('booking.step_dates'), 2 => __('booking.step_details'), 3 => __('booking.step_review')] as $n => $label)
            <li class="flex items-center gap-2" @if ($step === $n) aria-current="step" @endif>
                <span @class([
                    'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                    'bg-primary text-on-primary' => $step >= $n,
                    'bg-surface-sunken text-ink-faint' => $step < $n,
                ])>{{ $n }}</span>
                <span @class([
                    'hidden text-sm sm:inline',
                    'font-medium text-ink' => $step === $n,
                    'text-ink-faint' => $step !== $n,
                ])>{{ $label }}</span>
            </li>
            @if ($n < 3)
                <li class="mx-1 h-px flex-1 bg-line" aria-hidden="true"></li>
            @endif
        @endforeach
    </ol>

    {{-- Step 1 — Dates --}}
    @if ($step === 1)
        <x-ui.card>
            <h2 class="mb-4 text-lg font-semibold text-ink">{{ __('booking.pick_dates') }}</h2>

            <div class="mb-6">
                {{-- id and the three data-* attributes are the contract with
                     resources/js/booking-form.js and BookingWizardFlowTest. --}}
                <x-ui.input id="date-range-picker"
                            type="text"
                            placeholder="{{ __('booking.date_placeholder') }}"
                            data-availability-url="{{ route('vehicle.availability', $vehicle) }}"
                            data-default-start="{{ $startDate }}"
                            data-default-end="{{ $endDate }}"
                            data-max-rental-days="{{ config('bookings.max_rental_days') }}" />
                @error('startDate') <p class="mt-1 text-xs text-critical">{{ $message }}</p> @enderror
                @error('endDate') <p class="mt-1 text-xs text-critical">{{ $message }}</p> @enderror
            </div>

            {{-- Price preview --}}
            @if ($priceBreakdown)
                <div class="mb-6 rounded-panel border border-primary/20 bg-primary/10 p-4">
                    <h3 class="mb-3 text-sm font-semibold text-primary">{{ __('booking.price_preview') }}</h3>
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-muted">{{ __('booking.rate_type') }}</dt>
                            <dd class="font-medium text-ink">{{ $priceBreakdown['rate_type'] }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-muted">{{ __('booking.subtotal') }}</dt>
                            <dd><x-ui.price :amount="$priceBreakdown['subtotal']" size="sm" /></dd>
                        </div>
                        @if ($priceBreakdown['discount'] > 0)
                            <div class="flex justify-between gap-4 text-positive">
                                <dt>{{ __('booking.discount') }}</dt>
                                <dd>-{{ __('booking.currency_symbol') }}{{ number_format($priceBreakdown['discount'], 2) }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between gap-4 border-t border-primary/30 pt-1 font-bold text-primary">
                            <dt>{{ __('booking.total') }}</dt>
                            <dd>{{ __('booking.currency_symbol') }}{{ number_format($priceBreakdown['total'], 2) }}</dd>
                        </div>
                        @if ($priceBreakdown['deposit'] > 0)
                            <div class="flex justify-between gap-4 text-ink-muted">
                                <dt>{{ __('booking.deposit') }}</dt>
                                <dd><x-ui.price :amount="$priceBreakdown['deposit']" size="sm" /></dd>
                            </div>
                        @endif
                    </dl>
                </div>

                @if (tenant()?->allowsFeature(\App\Enums\PlanFeature::PromoCodes) ?? (bool) \App\Enums\PlanFeature::PromoCodes->default())
                    <x-ui.field :label="__('booking.promo_label')" class="mb-6">
                        {{-- Stacks below sm: an input and a button side by side
                             leave the input unusably narrow on a phone. --}}
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <x-ui.input type="text"
                                        wire:model="promoCode"
                                        placeholder="{{ __('booking.promo_placeholder') }}"
                                        class="uppercase sm:flex-1" />
                            <x-ui.button variant="secondary" wire:click="applyPromo">
                                {{ __('booking.promo_apply') }}
                            </x-ui.button>
                        </div>
                        @if ($promoNotice)
                            <p class="mt-1 text-sm text-positive">{{ $promoNotice }}</p>
                        @elseif ($promoError)
                            <p class="mt-1 text-sm text-critical">{{ $promoError }}</p>
                        @endif
                    </x-ui.field>
                @endif
            @endif

            <x-ui.button wire:click="nextStep" class="w-full">
                {{ __('booking.next') }}
            </x-ui.button>
        </x-ui.card>

        @push('scripts')
            @vite('resources/js/booking-form.js')
        @endpush
    @endif

    {{-- Step 2 — Customer details --}}
    @if ($step === 2)
        <x-ui.card>
            <h2 class="mb-4 text-lg font-semibold text-ink">{{ __('booking.step_details') }}</h2>

            <div class="space-y-4">
                {{-- data-test hooks are the contract with BookingWizardFlowTest. --}}
                <x-ui.field :label="__('booking.customer_name')" name="customerName" required>
                    <x-ui.input wire:model="customerName" type="text" autocomplete="name" data-test="customer-name" />
                </x-ui.field>

                <x-ui.field :label="__('booking.customer_phone')" name="customerPhone" required>
                    <x-ui.input wire:model="customerPhone" type="tel" autocomplete="tel" data-test="customer-phone" />
                </x-ui.field>

                <x-ui.field :label="__('booking.customer_email')" name="customerEmail" required>
                    <x-ui.input wire:model="customerEmail" type="email" autocomplete="email" data-test="customer-email" />
                </x-ui.field>

                <x-ui.field :label="__('booking.pickup_location')" name="pickupLocation">
                    <x-ui.input wire:model="pickupLocation" type="text" />
                </x-ui.field>

                <x-ui.field :label="__('booking.notes')" name="notes">
                    <x-ui.textarea wire:model="notes" rows="3" />
                </x-ui.field>
            </div>

            <div class="mt-6 flex gap-3">
                <x-ui.button variant="secondary" wire:click="prevStep" class="flex-1">
                    {{ __('booking.back') }}
                </x-ui.button>
                <x-ui.button wire:click="nextStep" class="flex-1">
                    {{ __('booking.next') }}
                </x-ui.button>
            </div>
        </x-ui.card>
    @endif

    {{-- Step 3 — Review & submit --}}
    @if ($step === 3)
        <x-ui.card>
            <h2 class="mb-4 text-lg font-semibold text-ink">{{ __('booking.review_heading') }}</h2>

            <dl class="mb-6 divide-y divide-line text-sm">
                <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-3">
                    <dt class="font-medium text-ink-muted">{{ __('booking.vehicle') }}</dt>
                    <dd class="text-ink">{{ $vehicle->name }}</dd>
                </div>
                <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-3">
                    <dt class="font-medium text-ink-muted">{{ __('booking.dates') }}</dt>
                    <dd class="text-ink">{{ $startDate }} → {{ $endDate }}</dd>
                </div>
                @if ($priceBreakdown)
                    <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-3">
                        <dt class="font-medium text-ink-muted">{{ __('booking.total') }}</dt>
                        <dd><x-ui.price :amount="$priceBreakdown['total']" size="sm" class="font-bold" /></dd>
                    </div>
                    @if ($priceBreakdown['deposit'] > 0)
                        <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-3">
                            <dt class="font-medium text-ink-muted">{{ __('booking.deposit') }}</dt>
                            <dd><x-ui.price :amount="$priceBreakdown['deposit']" size="sm" /></dd>
                        </div>
                    @endif
                @endif
                <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-3">
                    <dt class="font-medium text-ink-muted">{{ __('booking.customer_name') }}</dt>
                    <dd class="text-ink">{{ $customerName }}</dd>
                </div>
                <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-3">
                    <dt class="font-medium text-ink-muted">{{ __('booking.customer_phone') }}</dt>
                    <dd class="text-ink">{{ $customerPhone }}</dd>
                </div>
                @if ($customerEmail)
                    <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-3">
                        <dt class="font-medium text-ink-muted">{{ __('booking.customer_email') }}</dt>
                        <dd class="text-ink">{{ $customerEmail }}</dd>
                    </div>
                @endif
                @if ($pickupLocation)
                    <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-3">
                        <dt class="font-medium text-ink-muted">{{ __('booking.pickup_location') }}</dt>
                        <dd class="text-ink">{{ $pickupLocation }}</dd>
                    </div>
                @endif
                <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-3">
                    <dt class="font-medium text-ink-muted">{{ __('booking.payment_note') }}</dt>
                    <dd class="max-w-xs text-right text-ink">{{ tenant()?->setting('payment_instructions', __('booking.payment_note_value')) }}</dd>
                </div>
            </dl>

            <x-ui.alert tone="notice" class="mb-6 text-xs">{{ __('booking.pending_notice') }}</x-ui.alert>

            @if ($submitError)
                <x-ui.alert tone="critical" class="mb-4">{{ $submitError }}</x-ui.alert>
            @endif

            <div class="flex gap-3">
                <x-ui.button variant="secondary" wire:click="prevStep" class="flex-1">
                    {{ __('booking.back') }}
                </x-ui.button>
                <x-ui.button wire:click="submit" wire:loading.attr="disabled" class="flex-1">
                    <span wire:loading.remove>{{ __('booking.confirm_booking') }}</span>
                    <span wire:loading>…</span>
                </x-ui.button>
            </div>
        </x-ui.card>
    @endif
</x-ui.container>
