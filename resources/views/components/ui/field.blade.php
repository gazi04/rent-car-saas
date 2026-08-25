@props([
    'label' => null,
    /** Wired to the control's id so the label is clickable. */
    'for' => null,
    /** Livewire property name — when given, the field renders its own @error. */
    'name' => null,
    'hint' => null,
    'required' => false,
    /** 'sm' matches the compact filter row; 'md' is the default form scale. */
    'size' => 'md',
])

@php
    $labelClasses = $size === 'sm'
        ? 'block text-xs font-medium text-ink-muted mb-1'
        : 'block text-sm font-medium text-ink mb-1.5';
@endphp

<div {{ $attributes->class('w-full') }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="{{ $labelClasses }}">
            {{ $label }}@if ($required)<span class="text-critical" aria-hidden="true"> *</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p class="mt-1 text-xs text-ink-faint">{{ $hint }}</p>
    @endif

    @if ($name)
        @error($name)
            <p class="mt-1 text-xs text-critical">{{ $message }}</p>
        @enderror
    @endif
</div>
