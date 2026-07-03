<?php

namespace App\Services\Ai;

use App\Exceptions\AiRequestFailedException;
use JsonException;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

/**
 * The single touchpoint with the OpenAI SDK. Every AI
 * feature is one chat request/response through here — no agent loop, no
 * streaming. Callers get either plain text or, when a JSON schema is given, a
 * decoded array guaranteed to match it (the API enforces the schema).
 */
class AiChatService
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array{name: string, schema: array<string, mixed>}|null  $jsonSchema
     * @return ($jsonSchema is null ? string : array<string, mixed>)
     *
     * @throws AiRequestFailedException
     */
    public function chat(array $messages, ?array $jsonSchema = null): string|array
    {
        $parameters = [
            'model' => (string) config('ai.model'),
            'messages' => $messages,
        ];

        if ($jsonSchema !== null) {
            $parameters['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $jsonSchema['name'],
                    'strict' => true,
                    'schema' => $jsonSchema['schema'],
                ],
            ];
        }

        try {
            $response = OpenAI::chat()->create($parameters);
        } catch (Throwable $e) {
            throw AiRequestFailedException::wrap($e);
        }

        $content = $response->choices[0]->message->content ?? null;

        if ($content === null || trim($content) === '') {
            throw AiRequestFailedException::malformedResponse();
        }

        if ($jsonSchema === null) {
            return $content;
        }

        try {
            /** @var array<string, mixed> */
            return json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw AiRequestFailedException::wrap($e);
        }
    }
}
