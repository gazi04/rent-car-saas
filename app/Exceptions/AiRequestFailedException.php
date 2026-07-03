<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Single catchable type for anything that goes wrong talking to the AI API
 * (transport errors, rate limits, malformed/unparseable replies). UI call
 * sites catch this and show a notification — an AI hiccup must never block
 * the operator's actual workflow.
 */
class AiRequestFailedException extends RuntimeException
{
    public static function wrap(Throwable $previous): self
    {
        return new self('AI request failed: '.$previous->getMessage(), previous: $previous);
    }

    public static function malformedResponse(): self
    {
        return new self('AI request failed: the model returned an empty or malformed response.');
    }
}
