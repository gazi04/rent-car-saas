{{-- "Notify me when these dates free up" (backlog #2). Gated by
     PlanFeature::Waitlist — the parent guards the include, and joinWaitlist()
     re-asserts the gate server-side because a public Livewire action is callable
     over the wire regardless of what the page renders.

     Its own date inputs on purpose: the booking form's calendar disables taken
     dates, which is exactly the want this panel exists to capture. --}}
<section class="mt-8">
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-5">
        @if ($waitlistJoined)
            <div class="flex items-start gap-3">
                <span class="text-xl leading-none">✓</span>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('booking.waitlist_joined_heading') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('booking.waitlist_joined_body') }}</p>
                </div>
            </div>
        @else
            <h2 class="text-lg font-semibold text-gray-900">{{ __('booking.waitlist_heading') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ __('booking.waitlist_intro') }}</p>

            @if ($waitlistError)
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    {{ $waitlistError }}
                </div>
            @endif

            <form wire:submit="joinWaitlist" class="mt-4 space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="waitlist-start" class="block text-sm font-medium text-gray-700 mb-1">{{ __('booking.waitlist_start') }}</label>
                        <input id="waitlist-start" type="date" wire:model="waitlistStart"
                               class="w-full rounded-md border-gray-300 text-sm focus:border-primary focus:ring-primary">
                        @error('waitlistStart') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="waitlist-end" class="block text-sm font-medium text-gray-700 mb-1">{{ __('booking.waitlist_end') }}</label>
                        <input id="waitlist-end" type="date" wire:model="waitlistEnd"
                               class="w-full rounded-md border-gray-300 text-sm focus:border-primary focus:ring-primary">
                        @error('waitlistEnd') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label for="waitlist-name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('booking.waitlist_name') }}</label>
                        <input id="waitlist-name" type="text" wire:model="waitlistName"
                               class="w-full rounded-md border-gray-300 text-sm focus:border-primary focus:ring-primary">
                        @error('waitlistName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="waitlist-email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('booking.waitlist_email') }}</label>
                        <input id="waitlist-email" type="email" wire:model="waitlistEmail"
                               class="w-full rounded-md border-gray-300 text-sm focus:border-primary focus:ring-primary">
                        @error('waitlistEmail') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="waitlist-phone" class="block text-sm font-medium text-gray-700 mb-1">{{ __('booking.waitlist_phone') }}</label>
                        <input id="waitlist-phone" type="text" wire:model="waitlistPhone"
                               class="w-full rounded-md border-gray-300 text-sm focus:border-primary focus:ring-primary">
                        @error('waitlistPhone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" wire:loading.attr="disabled"
                            class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-50">
                        {{ __('booking.waitlist_submit') }}
                    </button>
                    {{-- Being told first is a head start, not a hold. Say so here as
                         well as in the email, so nobody assumes the car is theirs. --}}
                    <span class="text-xs text-gray-500">{{ __('booking.waitlist_no_hold') }}</span>
                </div>
            </form>
        @endif
    </div>
</section>
