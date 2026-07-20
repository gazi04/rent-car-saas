{{-- "Notify me when these dates free up" (backlog #2). Gated by
     PlanFeature::Waitlist — the parent guards the include, and joinWaitlist()
     re-asserts the gate server-side because a public Livewire action is callable
     over the wire regardless of what the page renders.

     Its own date inputs on purpose: the booking form's calendar disables taken
     dates, which is exactly the want this panel exists to capture. --}}
<section class="mt-8">
    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 shadow-sm sm:p-6">
        @if ($waitlistJoined)
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </span>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('booking.waitlist_joined_heading') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('booking.waitlist_joined_body') }}</p>
                </div>
            </div>
        @else
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008Z" />
                    </svg>
                </span>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('booking.waitlist_heading') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('booking.waitlist_intro') }}</p>
                </div>
            </div>

            @if ($waitlistError)
                <div class="mt-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="mt-0.5 h-4 w-4 shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                    <span>{{ $waitlistError }}</span>
                </div>
            @endif

            <form wire:submit="joinWaitlist" class="mt-5 space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="waitlist-start-picker" class="mb-1.5 block text-sm font-medium text-gray-700">{{ __('booking.waitlist_start') }}</label>
                        {{-- Plain flatpickr instance (dd/mm/yyyy), no wire:model — reports
                             back via a dispatched event, see resources/js/waitlist-form.js
                             and vehicle-show.blade.php's onWaitlistStartSelected(). --}}
                        <input id="waitlist-start-picker" type="text" placeholder="dd/mm/yyyy"
                               class="w-full rounded-xl border-gray-200 bg-white px-3.5 py-2.5 text-sm focus:border-primary focus:ring-2 focus:ring-primary/30 focus:outline-none">
                        @error('waitlistStart') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="waitlist-end-picker" class="mb-1.5 block text-sm font-medium text-gray-700">{{ __('booking.waitlist_end') }}</label>
                        <input id="waitlist-end-picker" type="text" placeholder="dd/mm/yyyy"
                               class="w-full rounded-xl border-gray-200 bg-white px-3.5 py-2.5 text-sm focus:border-primary focus:ring-2 focus:ring-primary/30 focus:outline-none">
                        @error('waitlistEnd') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label for="waitlist-name" class="mb-1.5 block text-sm font-medium text-gray-700">{{ __('booking.waitlist_name') }}</label>
                        <input id="waitlist-name" type="text" wire:model="waitlistName"
                               class="w-full rounded-xl border-gray-200 bg-white px-3.5 py-2.5 text-sm focus:border-primary focus:ring-2 focus:ring-primary/30 focus:outline-none">
                        @error('waitlistName') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="waitlist-email" class="mb-1.5 block text-sm font-medium text-gray-700">{{ __('booking.waitlist_email') }}</label>
                        <input id="waitlist-email" type="email" wire:model="waitlistEmail"
                               class="w-full rounded-xl border-gray-200 bg-white px-3.5 py-2.5 text-sm focus:border-primary focus:ring-2 focus:ring-primary/30 focus:outline-none">
                        @error('waitlistEmail') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="waitlist-phone" class="mb-1.5 block text-sm font-medium text-gray-700">{{ __('booking.waitlist_phone') }}</label>
                        <input id="waitlist-phone" type="text" wire:model="waitlistPhone"
                               class="w-full rounded-xl border-gray-200 bg-white px-3.5 py-2.5 text-sm focus:border-primary focus:ring-2 focus:ring-primary/30 focus:outline-none">
                        @error('waitlistPhone') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-1">
                    <button type="submit" wire:loading.attr="disabled" wire:target="joinWaitlist"
                            class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-90 active:scale-[.98] disabled:opacity-50">
                        <svg wire:loading wire:target="joinWaitlist" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 animate-spin">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        <svg wire:loading.remove wire:target="joinWaitlist" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                        </svg>
                        {{ __('booking.waitlist_submit') }}
                    </button>
                    {{-- Being told first is a head start, not a hold. Say so here as
                         well as in the email, so nobody assumes the car is theirs. --}}
                    <span class="text-xs text-gray-500">{{ __('booking.waitlist_no_hold') }}</span>
                </div>
            </form>

            @push('scripts')
                @vite('resources/js/waitlist-form.js')
            @endpush
        @endif
    </div>
</section>
