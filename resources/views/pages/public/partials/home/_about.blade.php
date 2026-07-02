@php
    /** Shared about-us section. Operator text is escaped; newlines preserved. */
    $align = $align ?? 'left';
@endphp

<div id="about" class="{{ $align === 'center' ? 'text-center max-w-2xl mx-auto' : 'max-w-3xl' }}">
    <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-4">{{ $content['about_title'] }}</h2>
    <p class="text-gray-600 leading-relaxed">{!! nl2br(e($content['about_text'])) !!}</p>
</div>
