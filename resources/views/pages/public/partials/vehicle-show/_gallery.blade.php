{{-- Alpine image carousel. $photos = [['web' => url, 'thumb' => url], …] --}}
@if (count($photos) > 0)
    <div x-data="{ current: 0, count: {{ count($photos) }} }" class="select-none" wire:ignore.self>
        {{-- Main slide track --}}
        <div class="relative rounded-lg overflow-hidden bg-gray-100 aspect-video">
            <div class="flex h-full transition-transform duration-300 ease-out"
                 :style="`transform: translateX(-${current * 100}%)`">
                @foreach ($photos as $photo)
                    <img src="{{ $photo['web'] }}"
                         alt="{{ $vehicle->name }} — {{ $loop->iteration }}"
                         class="w-full h-full object-cover shrink-0"
                         @if (! $loop->first) loading="lazy" @endif>
                @endforeach
            </div>

            @if (count($photos) > 1)
                <button type="button"
                        @click="current = (current - 1 + count) % count"
                        class="absolute left-3 top-1/2 -translate-y-1/2 rounded-full bg-white/80 hover:bg-white p-2 text-gray-700 shadow"
                        aria-label="{{ __('booking.photo_previous') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 19.5L8.25 12l7.5-7.5"/>
                    </svg>
                </button>
                <button type="button"
                        @click="current = (current + 1) % count"
                        class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full bg-white/80 hover:bg-white p-2 text-gray-700 shadow"
                        aria-label="{{ __('booking.photo_next') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                </button>

                {{-- Dots --}}
                <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5">
                    @foreach ($photos as $index => $photo)
                        <button type="button"
                                @click="current = {{ $index }}"
                                class="h-2 w-2 rounded-full transition-colors"
                                :class="current === {{ $index }} ? 'bg-white' : 'bg-white/50'"
                                aria-label="{{ __('booking.photo_show', ['number' => $index + 1]) }}"></button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Thumbnail strip --}}
        @if (count($photos) > 1)
            <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                @foreach ($photos as $index => $photo)
                    <button type="button"
                            @click="current = {{ $index }}"
                            class="shrink-0 rounded-md overflow-hidden border-2 transition-colors"
                            :class="current === {{ $index }} ? 'border-primary' : 'border-transparent opacity-70 hover:opacity-100'"
                            aria-label="{{ __('booking.photo_show', ['number' => $index + 1]) }}">
                        <img src="{{ $photo['thumb'] }}" alt="" class="h-16 w-24 object-cover">
                    </button>
                @endforeach
            </div>
        @endif
    </div>
@else
    <div class="rounded-lg bg-gray-100 aspect-video flex items-center justify-center text-gray-400">
        <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
        </svg>
    </div>
@endif
