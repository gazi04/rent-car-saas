<?php

use App\Enums\PlanFeature;
use App\Exceptions\AiRequestFailedException;
use App\Services\Ai\FaqConciergeService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    /**
     * How many prior turns are worth grounding an answer on. Past this the
     * marginal answer quality is nil and every extra turn is paid for on every
     * subsequent ask, since the whole history is re-sent each time.
     */
    private const int MAX_HISTORY_TURNS = 20;

    /**
     * Conversation so far, oldest first. Lives only in component state — passed
     * to the agent as history and gone when the page unloads (no storage).
     *
     * Locked because this is not the server's record of the chat: it is whatever
     * the browser sends back, and history() splices it into the model prompt
     * verbatim. Unlocked, a crafted /livewire/update payload can forge `assistant`
     * turns — which the agent yields as AssistantMessage, i.e. words the model
     * believes it said itself — defeating both the FAQ-only grounding and the
     * `confident` fallback gate. It can also carry unbounded history, which the
     * limiters in ask() do not bound because they count asks, not tokens.
     * ask() still appends server-side; the lock only rejects client-side changes.
     *
     * @var list<array{role: string, content: string}>
     */
    #[Locked]
    public array $messages = [];

    public string $question = '';

    /**
     * Whether this tenant may show the widget at all: the plan feature is on AND
     * the operator actually wrote FAQ content (no content → no widget → no spend,
     * and no bot with nothing to ground on). Recomputed server-side on every
     * hydration, and — critically — re-asserted at the top of ask(), because a
     * public Livewire action has no framework authorization of its own.
     */
    #[Computed]
    public function isEnabled(): bool
    {
        $gated = tenant()?->allowsFeature(PlanFeature::AiConcierge)
            ?? (bool) PlanFeature::AiConcierge->default();

        return $gated && trim((string) (tenant()?->localizedSetting('faq_content', '') ?? '')) !== '';
    }

    public function ask(): void
    {
        // Gate FIRST — the @if wrapping the widget is cosmetic; this method is
        // directly callable over /livewire/update by anyone.
        abort_unless($this->isEnabled, 404);

        $this->validate([
            'question' => ['required', 'string', 'max:500'],
        ]);

        $ipKey = 'concierge-ask:'.tenant()->id.':'.request()->ip();
        $tenantKey = 'concierge-tenant:'.tenant()->id;

        $question = trim($this->question);
        $this->messages[] = ['role' => 'user', 'content' => $question];
        $this->question = '';

        // Two feature-local limiters. On cap, answer with the fallback line —
        // a visitor must never see the operator's budget or an error.
        if (RateLimiter::tooManyAttempts($ipKey, maxAttempts: 10)
            || RateLimiter::tooManyAttempts($tenantKey, maxAttempts: (int) config('ai.concierge_daily_cap', 200))) {
            $this->messages[] = ['role' => 'assistant', 'content' => __('booking.concierge_throttled')];

            return;
        }

        // Hit after validation + the throttle check, so a rejected question never
        // burns an attempt.
        RateLimiter::hit($ipKey, decaySeconds: 3600);
        RateLimiter::hit($tenantKey, decaySeconds: 86400);

        try {
            $answer = resolve(FaqConciergeService::class)->answer(
                tenant(),
                $question,
                app()->getLocale(),
                $this->history(),
            );
        } catch (AiRequestFailedException) {
            $this->messages[] = ['role' => 'assistant', 'content' => __('booking.concierge_error')];

            return;
        }

        $this->messages[] = ['role' => 'assistant', 'content' => $answer];
    }

    /**
     * Prior turns to ground the next answer on: everything except the question
     * just appended, capped to the most recent MAX_HISTORY_TURNS. The lock on
     * $messages stops a forged history; this caps an honest one — a page left
     * open all afternoon would otherwise grow the prompt, and the spend, on
     * every turn without ever tripping the per-ask limiters.
     *
     * @return list<array{role: string, content: string}>
     */
    private function history(): array
    {
        $prior = array_slice($this->messages, 0, -1);

        return array_slice($prior, -self::MAX_HISTORY_TURNS);
    }
}; ?>

<div>
@if ($this->isEnabled)
    <div
        x-data="{ open: false, pending: '' }"
        x-init="$watch('$wire.messages', () => { pending = '' })"
        class="fixed bottom-4 end-4 z-50 print:hidden"
        translate="no"
    >
        {{-- Launcher --}}
        <button
            type="button"
            x-show="!open"
            x-on:click="open = true; $nextTick(() => $refs.question.focus())"
            class="group relative flex h-14 w-14 items-center justify-center rounded-full text-white shadow-lg transition hover:scale-105 hover:opacity-90"
            style="background-color: var(--color-primary, #1f2937);"
            aria-label="{{ __('booking.concierge_launcher') }}"
        >
            <span class="absolute -top-0.5 -end-0.5 h-3.5 w-3.5 rounded-full border-2 border-white bg-emerald-400"></span>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-6 w-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.76 9.76 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
            </svg>
            <span class="pointer-events-none absolute end-full me-3 whitespace-nowrap rounded-lg bg-gray-900 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 shadow-lg transition group-hover:opacity-100">
                {{ __('booking.concierge_launcher') }}
            </span>
        </button>

        {{-- Panel --}}
        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-2 scale-95"
            class="flex h-[32rem] max-h-[calc(100vh-6rem)] w-96 max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-3xl border border-black/5 bg-white shadow-2xl ring-1 ring-black/5"
        >
            <div class="flex shrink-0 items-center gap-3 px-4 py-3.5 text-white" style="background-color: var(--color-primary, #1f2937);">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/15">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.76 9.76 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                    </svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold">{{ __('booking.concierge_title') }}</p>
                    <p class="truncate text-xs text-white/75">{{ __('booking.concierge_subtitle') }}</p>
                </div>
                <button type="button" x-on:click="open = false" class="shrink-0 rounded-full p-1.5 text-white/80 transition hover:bg-white/10 hover:text-white" aria-label="{{ __('booking.concierge_close') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div x-ref="scrollArea" class="flex flex-1 flex-col gap-3 overflow-y-auto bg-gray-50 px-4 py-4 text-sm">
                <p class="max-w-[85%] rounded-2xl rounded-bl-md bg-white px-3.5 py-2.5 text-gray-600 shadow-sm">
                    {{ __('booking.concierge_greeting') }}
                </p>

                @foreach ($messages as $message)
                    @if ($message['role'] === 'user')
                        <p class="ms-auto max-w-[85%] rounded-2xl rounded-br-md px-3.5 py-2.5 text-white shadow-sm" style="background-color: var(--color-primary, #1f2937);">
                            {{ $message['content'] }}
                        </p>
                    @else
                        <p class="me-auto max-w-[85%] rounded-2xl rounded-bl-md bg-white px-3.5 py-2.5 text-gray-800 shadow-sm">
                            {{ $message['content'] }}
                        </p>
                    @endif
                @endforeach

                <p x-show="pending" x-cloak x-text="pending" class="ms-auto max-w-[85%] rounded-2xl rounded-br-md px-3.5 py-2.5 text-white shadow-sm" style="background-color: var(--color-primary, #1f2937);"></p>

                <div wire:loading wire:target="ask" class="me-auto flex items-center gap-1 rounded-2xl rounded-bl-md bg-white px-4 py-3 shadow-sm">
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400 [animation-delay:-0.3s]"></span>
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400 [animation-delay:-0.15s]"></span>
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400"></span>
                </div>
            </div>

            <form
                wire:submit="ask"
                x-on:submit.capture="pending = $refs.question.value; $nextTick(() => $refs.scrollArea.scrollTop = $refs.scrollArea.scrollHeight)"
                class="shrink-0 border-t border-gray-100 bg-white p-3"
            >
                <div class="flex items-end gap-2">
                    <textarea
                        x-ref="question"
                        wire:model="question"
                        rows="1"
                        maxlength="500"
                        placeholder="{{ __('booking.concierge_placeholder') }}"
                        class="min-h-[2.75rem] flex-1 resize-none rounded-2xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:border-gray-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-gray-200"
                    ></textarea>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="ask"
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-white shadow-sm transition hover:opacity-90 disabled:opacity-50"
                        style="background-color: var(--color-primary, #1f2937);"
                        aria-label="{{ __('booking.concierge_send') }}"
                    >
                        <svg wire:loading wire:target="ask" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5 animate-spin">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        <svg wire:loading.remove wire:target="ask" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.126A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.876L5.999 12Zm0 0h7.5" />
                        </svg>
                    </button>
                </div>
                @error('question')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </form>
        </div>
    </div>
@endif
</div>
