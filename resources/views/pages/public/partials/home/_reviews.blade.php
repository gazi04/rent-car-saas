{{-- Home-page review showcase — gated (Standard/Pro). Aggregate rating + a few
     featured approved reviews across the fleet. --}}
@php
    $reviews = $this->showcaseReviews;
    $average = $this->averageRating;
    $count = $this->reviewsCount;
@endphp

<section class="mt-16">
    <div class="text-center mb-8">
        <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">{{ __('booking.reviews_heading') }}</h2>
        <div class="inline-flex items-center gap-2 text-gray-600">
            <span class="text-amber-400 text-xl leading-none">★</span>
            <span class="text-lg font-semibold text-gray-900">{{ number_format((float) $average, 1) }}</span>
            <span class="text-gray-400">/ 5</span>
            <span class="text-gray-400">·</span>
            <span>{{ trans_choice('booking.reviews_count', $count, ['count' => $count]) }}</span>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($reviews as $review)
            <div class="rounded-lg border border-gray-200 p-5 bg-white">
                <div class="text-amber-400 text-sm mb-2 tracking-tight" aria-label="{{ $review->rating }}/5">
                    {{ str_repeat('★', $review->rating) }}<span class="text-gray-300">{{ str_repeat('★', 5 - $review->rating) }}</span>
                </div>
                @if ($review->comment)
                    <p class="text-sm text-gray-600 leading-relaxed mb-3">{{ $review->comment }}</p>
                @endif
                <p class="text-sm font-medium text-gray-900">{{ $review->reviewer_name }}</p>
                <p class="text-xs text-gray-400">{{ $review->vehicle->name }}</p>
            </div>
        @endforeach
    </div>
</section>
