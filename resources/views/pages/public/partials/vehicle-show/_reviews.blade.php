{{-- Approved customer reviews for this vehicle + average rating. Free on every
     plan so accumulated reviews stay visible. --}}
@php
    $reviews = $this->reviews;
    $average = $this->averageRating;
@endphp

@if ($reviews->isNotEmpty())
    <section class="mt-8">
        <div class="mb-5 flex flex-wrap items-center gap-3">
            <h2 class="text-lg font-semibold text-ink">{{ __('booking.reviews_heading') }}</h2>
            <span class="inline-flex items-center gap-1 text-sm text-ink-muted">
                <x-ui.stars :rating="$average" />
                <span class="font-semibold text-ink">{{ number_format((float) $average, 1) }}</span>
                <span class="text-ink-faint">({{ trans_choice('booking.reviews_count', $reviews->count(), ['count' => $reviews->count()]) }})</span>
            </span>
        </div>

        <div class="space-y-4">
            @foreach ($reviews as $review)
                <x-ui.card pad="sm">
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm font-medium text-ink">{{ $review->reviewer_name }}</span>
                        <x-ui.stars :rating="$review->rating" />
                    </div>
                    @if ($review->comment)
                        <p class="text-sm leading-relaxed text-ink-muted">{{ $review->comment }}</p>
                    @endif
                    <p class="mt-2 text-xs text-ink-faint">{{ $review->submitted_at->format('d M Y') }}</p>
                </x-ui.card>
            @endforeach
        </div>
    </section>
@endif
