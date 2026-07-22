<?php

use App\Enums\PlanFeature;
use App\Enums\VehicleStatus;
use App\Exceptions\PromoCodeInvalidException;
use App\Exceptions\VehicleNotAvailableException;
use App\Models\PromoCode;
use App\Models\Vehicle;
use App\Services\BookingService;
use App\Services\PricingService;
use Illuminate\Support\Facades\Date;
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

            if ($start->lt($end)) {
                $this->startDate = $this->prefillStartDate;
                $this->endDate = $this->prefillEndDate;
                $this->refreshPrice();
            }
        }
    }

    #[On('dates-selected')]
    public function onDatesSelected(string $start, string $end): void
    {
        $this->startDate = $start;
        $this->endDate = $end;
        $this->slotTaken = false;
        $this->refreshPrice();
    }

    public function nextStep(): void
    {
        $this->slotTaken = false;

        if ($this->step === 1) {
            $this->validate([
                'startDate' => 'required|date',
                'endDate' => 'required|date|after:startDate',
            ]);
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
        $this->validate([
            'startDate' => 'required|date',
            'endDate' => 'required|date|after:startDate',
            'customerName' => 'required|string|max:255',
            'customerPhone' => 'required|string|max:50',
            'customerEmail' => 'required|email|max:255',
            'pickupLocation' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $this->slotTaken = false;

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
        } catch (PromoCodeInvalidException) {
            $this->promoError = __('booking.promo_invalid');
        }
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

<div>
    {{-- Slot taken flash --}}
    @if ($slotTaken)
        <div class="mb-6 rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">
            {{ __('booking.slot_taken') }}
        </div>
    @endif

    {{-- Vehicle summary --}}
    <div class="bg-white rounded-lg border border-gray-200 p-6 mb-8 flex gap-6">
        <div class="w-32 h-24 rounded-md bg-gray-100 overflow-hidden shrink-0">
            @if ($vehicle->getFirstMedia('vehicle_photos'))
                <img src="{{ $vehicle->getFirstMediaUrl('vehicle_photos', 'thumb') }}"
                     alt="{{ $vehicle->name }}" class="w-full h-full object-cover">
            @endif
        </div>
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ $vehicle->name }}</h1>
            <div class="flex items-center gap-3 text-sm text-gray-500 mt-1">
                <span>{{ $vehicle->category->getLabel() }}</span>
                <span>{{ $vehicle->seats }} seats</span>
                <span>{{ $vehicle->fuel_type->getLabel() }}</span>
                <span>{{ $vehicle->transmission->getLabel() }}</span>
            </div>
            @php($vehicleDescription = $vehicle->descriptionFor())
            @if ($vehicleDescription !== '')
                <p class="text-sm text-gray-600 mt-2">{{ $vehicleDescription }}</p>
            @endif
        </div>
    </div>

    {{-- Step indicators --}}
    <div class="flex items-center gap-2 mb-8">
        @foreach ([1 => __('booking.step_dates'), 2 => __('booking.step_details'), 3 => __('booking.step_review')] as $n => $label)
            <div class="flex items-center gap-2">
                <div @class([
                    'w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold',
                    'bg-primary text-white' => $step >= $n,
                    'bg-gray-200 text-gray-500' => $step < $n,
                ])>{{ $n }}</div>
                <span @class(['text-sm', 'font-medium text-gray-900' => $step === $n, 'text-gray-400' => $step !== $n])>{{ $label }}</span>
            </div>
            @if ($n < 3)
                <div class="flex-1 h-px bg-gray-200 mx-1"></div>
            @endif
        @endforeach
    </div>

    {{-- Step 1 — Dates --}}
    @if ($step === 1)
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('booking.pick_dates') }}</h2>

            <div class="mb-6">
                <input id="date-range-picker"
                       type="text"
                       placeholder="{{ __('booking.date_placeholder') }}"
                       data-availability-url="{{ route('vehicle.availability', $vehicle) }}"
                       data-default-start="{{ $startDate }}"
                       data-default-end="{{ $endDate }}"
                       class="w-full rounded-md border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
                @error('startDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                @error('endDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Price preview --}}
            @if ($priceBreakdown)
                <div class="rounded-lg bg-primary/10 border border-primary/20 p-4 mb-6">
                    <h3 class="text-sm font-semibold text-primary mb-3">{{ __('booking.price_preview') }}</h3>
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-600">{{ __('booking.rate_type') }}</dt>
                            <dd class="font-medium">{{ $priceBreakdown['rate_type'] }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-600">{{ __('booking.subtotal') }}</dt>
                            <dd>€{{ number_format($priceBreakdown['subtotal'], 2) }}</dd>
                        </div>
                        @if ($priceBreakdown['discount'] > 0)
                            <div class="flex justify-between text-green-700">
                                <dt>{{ __('booking.discount') }}</dt>
                                <dd>-€{{ number_format($priceBreakdown['discount'], 2) }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between font-bold text-primary pt-1 border-t border-primary/30">
                            <dt>{{ __('booking.total') }}</dt>
                            <dd>€{{ number_format($priceBreakdown['total'], 2) }}</dd>
                        </div>
                        @if ($priceBreakdown['deposit'] > 0)
                            <div class="flex justify-between text-gray-500">
                                <dt>{{ __('booking.deposit') }}</dt>
                                <dd>€{{ number_format($priceBreakdown['deposit'], 2) }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                @if (tenant()?->allowsFeature(\App\Enums\PlanFeature::PromoCodes) ?? (bool) \App\Enums\PlanFeature::PromoCodes->default())
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('booking.promo_label') }}</label>
                        <div class="flex gap-2">
                            <input type="text" wire:model="promoCode"
                                   placeholder="{{ __('booking.promo_placeholder') }}"
                                   class="flex-1 rounded-md border-gray-300 text-sm uppercase focus:border-primary focus:ring-primary">
                            <button type="button" wire:click="applyPromo"
                                    class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 transition-colors">
                                {{ __('booking.promo_apply') }}
                            </button>
                        </div>
                        @if ($promoNotice)
                            <p class="mt-1 text-sm text-green-700">{{ $promoNotice }}</p>
                        @elseif ($promoError)
                            <p class="mt-1 text-sm text-red-600">{{ $promoError }}</p>
                        @endif
                    </div>
                @endif
            @endif

            <button wire:click="nextStep"
                    class="w-full rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-secondary transition-colors">
                {{ __('booking.next') }}
            </button>
        </div>

        @push('scripts')
            @vite('resources/js/booking-form.js')
        @endpush
    @endif

    {{-- Step 2 — Customer details --}}
    @if ($step === 2)
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('booking.step_details') }}</h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('booking.customer_name') }} *</label>
                    <input wire:model="customerName" type="text" autocomplete="name"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-primary">
                    @error('customerName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('booking.customer_phone') }} *</label>
                    <input wire:model="customerPhone" type="tel" autocomplete="tel"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-primary">
                    @error('customerPhone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('booking.customer_email') }} *</label>
                    <input wire:model="customerEmail" type="email" autocomplete="email"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-primary">
                    @error('customerEmail') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('booking.pickup_location') }}</label>
                    <input wire:model="pickupLocation" type="text"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-primary">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('booking.notes') }}</label>
                    <textarea wire:model="notes" rows="3"
                              class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-primary"></textarea>
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button wire:click="prevStep"
                        class="flex-1 rounded-md border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    {{ __('booking.back') }}
                </button>
                <button wire:click="nextStep"
                        class="flex-1 rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-secondary transition-colors">
                    {{ __('booking.next') }}
                </button>
            </div>
        </div>
    @endif

    {{-- Step 3 — Review & submit --}}
    @if ($step === 3)
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('booking.review_heading') }}</h2>

            <dl class="divide-y divide-gray-100 text-sm mb-6">
                <div class="py-3 flex justify-between">
                    <dt class="font-medium text-gray-500">{{ __('booking.vehicle') }}</dt>
                    <dd class="text-gray-900">{{ $vehicle->name }}</dd>
                </div>
                <div class="py-3 flex justify-between">
                    <dt class="font-medium text-gray-500">{{ __('booking.dates') }}</dt>
                    <dd class="text-gray-900">{{ $startDate }} → {{ $endDate }}</dd>
                </div>
                @if ($priceBreakdown)
                    <div class="py-3 flex justify-between">
                        <dt class="font-medium text-gray-500">{{ __('booking.total') }}</dt>
                        <dd class="font-bold text-gray-900">€{{ number_format($priceBreakdown['total'], 2) }}</dd>
                    </div>
                    @if ($priceBreakdown['deposit'] > 0)
                        <div class="py-3 flex justify-between">
                            <dt class="font-medium text-gray-500">{{ __('booking.deposit') }}</dt>
                            <dd class="text-gray-900">€{{ number_format($priceBreakdown['deposit'], 2) }}</dd>
                        </div>
                    @endif
                @endif
                <div class="py-3 flex justify-between">
                    <dt class="font-medium text-gray-500">{{ __('booking.customer_name') }}</dt>
                    <dd class="text-gray-900">{{ $customerName }}</dd>
                </div>
                <div class="py-3 flex justify-between">
                    <dt class="font-medium text-gray-500">{{ __('booking.customer_phone') }}</dt>
                    <dd class="text-gray-900">{{ $customerPhone }}</dd>
                </div>
                @if ($customerEmail)
                    <div class="py-3 flex justify-between">
                        <dt class="font-medium text-gray-500">{{ __('booking.customer_email') }}</dt>
                        <dd class="text-gray-900">{{ $customerEmail }}</dd>
                    </div>
                @endif
                @if ($pickupLocation)
                    <div class="py-3 flex justify-between">
                        <dt class="font-medium text-gray-500">{{ __('booking.pickup_location') }}</dt>
                        <dd class="text-gray-900">{{ $pickupLocation }}</dd>
                    </div>
                @endif
                <div class="py-3 flex justify-between">
                    <dt class="font-medium text-gray-500">{{ __('booking.payment_note') }}</dt>
                    <dd class="text-gray-900 text-right max-w-xs">{{ tenant()?->setting('payment_instructions', __('booking.payment_note_value')) }}</dd>
                </div>
            </dl>

            <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 mb-6 text-xs text-amber-800">
                {{ __('booking.pending_notice') }}
            </div>

            <div class="flex gap-3">
                <button wire:click="prevStep"
                        class="flex-1 rounded-md border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    {{ __('booking.back') }}
                </button>
                <button wire:click="submit" wire:loading.attr="disabled"
                        class="flex-1 rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-secondary transition-colors disabled:opacity-60">
                    <span wire:loading.remove>{{ __('booking.confirm_booking') }}</span>
                    <span wire:loading>…</span>
                </button>
            </div>
        </div>
    @endif
</div>
