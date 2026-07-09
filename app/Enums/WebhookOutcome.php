<?php

namespace App\Enums;

enum WebhookOutcome: string
{
    case Accepted = 'accepted';
    case Duplicate = 'duplicate';
    case Ignored = 'ignored';
    case UnknownToken = 'unknown_token';
    case InvalidSignature = 'invalid_signature';

    /**
     * A human-readable label for the outcome.
     */
    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Accepted',
            self::Duplicate => 'Duplicate (redelivery)',
            self::Ignored => 'Ignored (not a transcription)',
            self::UnknownToken => 'Rejected — unknown token',
            self::InvalidSignature => 'Rejected — invalid signature',
        };
    }
}
