{{-- "Notify me when this vehicle is available again" (backlog #3). Gated by
     PlanFeature::StockAlert — the parent guards the include, and joinStockAlert()
     re-asserts the gate server-side because a public Livewire action is callable
     over the wire regardless of what the page renders.

     No date inputs, unlike the waitlist panel: the want here is the vehicle
     itself, whenever it comes back. That is what a null range stores. --}}
<section class="mt-8">
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-5">
        @if ($stockAlertJoined)
            <div class="flex items-start gap-3">
                <span class="text-xl leading-none">✓</span>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('booking.stock_alert_joined_heading') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('booking.stock_alert_joined_body') }}</p>
                </div>
            </div>
        @else
            <h2 class="text-lg font-semibold text-gray-900">{{ __('booking.stock_alert_heading') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ __('booking.stock_alert_intro') }}</p>

            @if ($stockAlertError)
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    {{ $stockAlertError }}
                </div>
            @endif

            <form wire:submit="joinStockAlert" class="mt-4 space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label for="stock-alert-name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('booking.stock_alert_name') }}</label>
                        <input id="stock-alert-name" type="text" wire:model="stockAlertName"
                               class="w-full rounded-md border-gray-300 text-sm focus:border-primary focus:ring-primary">
                        @error('stockAlertName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="stock-alert-email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('booking.stock_alert_email') }}</label>
                        <input id="stock-alert-email" type="email" wire:model="stockAlertEmail"
                               class="w-full rounded-md border-gray-300 text-sm focus:border-primary focus:ring-primary">
                        @error('stockAlertEmail') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="stock-alert-phone" class="block text-sm font-medium text-gray-700 mb-1">{{ __('booking.stock_alert_phone') }}</label>
                        <input id="stock-alert-phone" type="text" wire:model="stockAlertPhone"
                               class="w-full rounded-md border-gray-300 text-sm focus:border-primary focus:ring-primary">
                        @error('stockAlertPhone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" wire:loading.attr="disabled"
                            class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-50">
                        {{ __('booking.stock_alert_submit') }}
                    </button>
                    {{-- Everyone waiting is told at once, so nobody has a claim.
                         Say so here as well as in the email. --}}
                    <span class="text-xs text-gray-500">{{ __('booking.stock_alert_no_hold') }}</span>
                </div>
            </form>
        @endif
    </div>
</section>
