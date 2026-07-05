<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Review;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts.public')] #[Title('Leave a Review')] class extends Component {
    public Booking $booking;

    /** True once the booking is not eligible (wrong status) or already reviewed. */
    public bool $unavailable = false;

    public bool $alreadyReviewed = false;

    public bool $submitted = false;

    #[Validate('required|integer|min:1|max:5')]
    public int $rating = 0;

    #[Validate('nullable|string|max:1000')]
    public string $comment = '';

    public function mount(Booking $booking): void
    {
        $this->booking = $booking;

        if ($booking->status !== BookingStatus::Completed) {
            $this->unavailable = true;

            return;
        }

        if ($booking->review()->exists()) {
            $this->alreadyReviewed = true;
        }
    }

    public function submit(): void
    {
        // Re-guard on submit: state could have changed since mount, and a second
        // tab must not create a duplicate (enforced by the unique booking_id too).
        abort_if($this->unavailable, 404);

        if ($this->booking->status !== BookingStatus::Completed || $this->booking->review()->exists()) {
            $this->alreadyReviewed = true;

            return;
        }

        $this->validate();

        Review::create([
            'booking_id' => $this->booking->id,
            'vehicle_id' => $this->booking->vehicle_id,
            'customer_id' => $this->booking->customer_id,
            'reviewer_name' => $this->booking->customer_name,
            'rating' => $this->rating,
            'comment' => $this->comment !== '' ? $this->comment : null,
            'is_approved' => false,
            'submitted_at' => now(),
        ]);

        $this->submitted = true;
    }
}; ?>

<div class="max-w-lg mx-auto">
    <div class="bg-white rounded-lg border border-gray-200 p-8">
        @if ($submitted)
            <div class="text-center">
                <div class="w-14 h-14 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ __('booking.review_thanks') }}</h1>
                <a href="{{ route('public.home') }}"
                   class="inline-block mt-4 rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-secondary transition-colors">
                    {{ __('booking.back_to_fleet') }}
                </a>
            </div>
        @elseif ($unavailable)
            <div class="text-center">
                <h1 class="text-xl font-bold text-gray-900 mb-2">{{ __('booking.review_unavailable') }}</h1>
            </div>
        @elseif ($alreadyReviewed)
            <div class="text-center">
                <h1 class="text-xl font-bold text-gray-900 mb-2">{{ __('booking.review_already') }}</h1>
            </div>
        @else
            <h1 class="text-2xl font-bold text-gray-900 mb-1">{{ __('booking.review_title') }}</h1>
            <p class="text-sm text-gray-600 mb-6">{{ $booking->vehicle->name }}</p>

            <form wire:submit="submit" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('booking.review_rating') }}</label>
                    <div class="flex gap-1" role="radiogroup">
                        @for ($star = 1; $star <= 5; $star++)
                            <button type="button"
                                    wire:click="$set('rating', {{ $star }})"
                                    aria-label="{{ $star }}"
                                    class="p-1 focus:outline-none">
                                <svg class="w-9 h-9 {{ $star <= $rating ? 'text-amber-400' : 'text-gray-300' }}"
                                     fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.368 2.446a1 1 0 00-.364 1.118l1.287 3.957c.3.921-.755 1.688-1.54 1.118l-3.367-2.446a1 1 0 00-1.176 0l-3.367 2.446c-.784.57-1.838-.197-1.539-1.118l1.286-3.957a1 1 0 00-.363-1.118L2.075 9.384c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.95-.69l1.286-3.957z"/>
                                </svg>
                            </button>
                        @endfor
                    </div>
                    @error('rating')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="comment" class="block text-sm font-medium text-gray-700 mb-2">{{ __('booking.review_comment') }}</label>
                    <textarea id="comment" wire:model="comment" rows="4"
                              class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm"></textarea>
                    @error('comment')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                        class="w-full rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-secondary transition-colors">
                    {{ __('booking.review_submit') }}
                </button>
            </form>
        @endif
    </div>
</div>
