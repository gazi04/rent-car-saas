<?php

namespace App\Ai\Agents;

use App\Ai\Contracts\ReportsAiUsage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

/**
 * Answers a storefront visitor's question strictly from the FAQ knowledge base
 * the operator wrote — never from outside knowledge. Structured output returns
 * the answer plus a `confident` flag; the calling service discards the answer
 * and substitutes a "contact the operator" line when `confident` is false, so a
 * thin or empty FAQ produces something safe rather than an invented policy.
 *
 * The first agent in the app to implement Conversational: the SDK splices
 * messages() into the prompt for multi-turn chat (GeneratesText), without the
 * unusable RemembersConversations trait. History is passed in from the widget's
 * component state, so nothing is persisted. The answer is one-shot in the
 * visitor's current locale, so language is a constructor param (like
 * PricingSuggestionAgent) rather than a bilingual schema.
 * Routed to the GitHub Models "github" provider (see config/ai.php).
 */
#[Provider('github')]
#[Model('openai/gpt-4.1')]
class FaqConciergeAgent implements Agent, Conversational, HasStructuredOutput, ReportsAiUsage
{
    use Promptable;

    /**
     * @param  list<array{role: string, content: string}>  $history
     */
    public function __construct(
        protected string $knowledge,
        protected string $language = 'English',
        protected array $history = [],
    ) {}

    public function aiFeature(): string
    {
        return 'concierge';
    }

    public function instructions(): string
    {
        return "You are a helpful assistant on a car-rental company's public booking website. "
            ."Answer the visitor's question using ONLY the facts in the FAQ text below. "
            .'If the answer is not in that text, do not guess or use outside knowledge — instead set '
            .'"confident" to false and leave a short apology in "answer". '
            ."Reply in {$this->language}, in 1-3 plain sentences, no headings or lists.\n\n"
            ."=== FAQ TEXT (the only source you may use) ===\n"
            .$this->knowledge;
    }

    /**
     * Prior turns of this conversation, spliced into the prompt by the SDK so the
     * model has context. Sourced from the widget's in-memory state — not stored.
     *
     * @return iterable<Message>
     */
    public function messages(): iterable
    {
        foreach ($this->history as $turn) {
            yield $turn['role'] === 'assistant'
                ? new AssistantMessage($turn['content'])
                : new Message('user', $turn['content']);
        }
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'answer' => $schema->string()->required(),
            'confident' => $schema->boolean()->required(),
        ];
    }
}
