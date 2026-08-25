{{-- Operator-written about text. Escaped, with newlines preserved. --}}
<section id="about" class="max-w-3xl">
    <x-ui.section-heading>{{ $content['about_title'] }}</x-ui.section-heading>
    <p class="mt-4 leading-relaxed text-ink-muted">{!! nl2br(e($content['about_text'])) !!}</p>
</section>
