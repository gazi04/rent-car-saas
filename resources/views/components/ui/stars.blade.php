@props([
    'rating',
    'size' => 'sm',
])

@php
    $filled = max(0, min(5, (int) round((float) $rating)));
    $textSize = ['sm' => 'text-sm', 'md' => 'text-base', 'lg' => 'text-xl'][$size] ?? 'text-sm';
@endphp

{{-- One aria-label carries the meaning; the glyphs themselves are decorative,
     so a screen reader announces "4/5" rather than five separate stars. --}}
<span {{ $attributes->class(['inline-block tracking-tight text-star', $textSize]) }}
      role="img"
      aria-label="{{ __('booking.rating_out_of_five', ['rating' => $filled]) }}">
    <span aria-hidden="true">{{ str_repeat('★', $filled) }}<span class="text-line-strong">{{ str_repeat('★', 5 - $filled) }}</span></span>
</span>
