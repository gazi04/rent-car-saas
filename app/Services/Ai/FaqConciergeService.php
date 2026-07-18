<?php

namespace App\Services\Ai;

use App\Ai\Agents\FaqConciergeAgent;
use App\Exceptions\AiRequestFailedException;
use App\Models\Tenant;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Answers one storefront question from the operator's FAQ knowledge base. The
 * agent returns an answer plus a `confident` flag; when the model is not
 * confident the answer is thrown away and a localized "contact the operator"
 * line is returned instead, so the model never authors the fallback and an
 * empty/thin FAQ is unhelpful-but-safe rather than wrong.
 */
class FaqConciergeService
{
    /**
     * @param  list<array{role: string, content: string}>  $history
     *
     * @throws AiRequestFailedException
     */
    public function answer(Tenant $tenant, string $question, string $locale = 'en', array $history = []): string
    {
        $knowledge = trim((string) $tenant->localizedSetting('faq_content', ''));
        $language = $locale === 'sq' ? 'Albanian' : 'English';

        try {
            /** @var StructuredAgentResponse $response */
            $response = (new FaqConciergeAgent($knowledge, $language, $history))->prompt($question);
        } catch (Throwable $e) {
            throw AiRequestFailedException::wrap($e);
        }

        /** @var array{answer?: string, confident?: bool} $result */
        $result = $response->toArray();

        if (! ($result['confident'] ?? false)) {
            return $this->contactFallback($tenant);
        }

        $answer = trim((string) ($result['answer'] ?? ''));

        if ($answer === '') {
            throw AiRequestFailedException::malformedResponse();
        }

        return $answer;
    }

    /**
     * The localized "I'm not sure — contact the operator" line, with the
     * operator's own phone/email appended when they are set.
     */
    private function contactFallback(Tenant $tenant): string
    {
        $contact = array_filter([
            $tenant->setting('contact_phone'),
            $tenant->setting('contact_email'),
        ]);

        return $contact === []
            ? __('booking.concierge_fallback')
            : __('booking.concierge_fallback_with_contact', ['contact' => implode(' / ', $contact)]);
    }
}
