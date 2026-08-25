{{-- Home-page review showcase — gated (Standard/Pro). Aggregate rating + a few
     featured approved reviews across the fleet. --}}
@php
    $reviews = $this->showcaseReviews;
    $average = $this->averageRating;
    $count = $this->reviewsCount;
@endphp

<section>
    <div class="mb-8 text-center">
        <h2 class="mb-2 text-2xl font-bold text-ink sm:text-3xl">{{ __('booking.reviews_heading') }}</h2>
        <div class="inline-flex flex-wrap items-center justify-center gap-2 text-ink-muted">
            <x-ui.stars :rating="$average" size="lg" />
            <span class="text-lg font-semibold text-ink">{{ number_format((float) $average, 1) }}</span>
            <span class="text-ink-faint">/ 5</span>
            <span class="text-ink-faint" aria-hidden="true">·</span>
            <span>{{ trans_choice('booking.reviews_count', $count, ['count' => $count]) }}</span>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($reviews as $review)
            <x-ui.card>
                <x-ui.stars :rating="$review->rating" class="mb-2" />
                @if ($review->comment)
                    <p class="mb-3 text-sm leading-relaxed text-ink-muted">{{ $review->comment }}</p>
                @endif
                <p class="text-sm font-medium text-ink">{{ $review->reviewer_name }}</p>
                <p class="text-xs text-ink-faint">{{ $review->vehicle->name }}</p>
            </x-ui.card>
        @endforeach
    </div>
</section>
