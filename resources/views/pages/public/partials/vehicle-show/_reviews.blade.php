{{-- Approved customer reviews for this vehicle + average rating. Free on every
     plan so accumulated reviews stay visible. --}}
@php
    $reviews = $this->reviews;
    $average = $this->averageRating;
@endphp

@if ($reviews->isNotEmpty())
    <section class="mt-8">
        <div class="flex items-center gap-3 mb-5">
            <h2 class="text-lg font-semibold text-gray-900">{{ __('booking.reviews_heading') }}</h2>
            <span class="inline-flex items-center gap-1 text-sm text-gray-600">
                <span class="text-amber-400 text-base leading-none">★</span>
                <span class="font-semibold text-gray-900">{{ number_format((float) $average, 1) }}</span>
                <span class="text-gray-400">({{ trans_choice('booking.reviews_count', $reviews->count(), ['count' => $reviews->count()]) }})</span>
            </span>
        </div>

        <div class="space-y-4">
            @foreach ($reviews as $review)
                <div class="rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-medium text-gray-900 text-sm">{{ $review->reviewer_name }}</span>
                        <span class="text-amber-400 text-sm tracking-tight" aria-label="{{ $review->rating }}/5">
                            {{ str_repeat('★', $review->rating) }}<span class="text-gray-300">{{ str_repeat('★', 5 - $review->rating) }}</span>
                        </span>
                    </div>
                    @if ($review->comment)
                        <p class="text-sm text-gray-600 leading-relaxed">{{ $review->comment }}</p>
                    @endif
                    <p class="mt-2 text-xs text-gray-400">{{ $review->submitted_at->format('d M Y') }}</p>
                </div>
            @endforeach
        </div>
    </section>
@endif
