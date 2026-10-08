<?php

namespace Rishadblack\WireModals\Exceptions;

final class InvalidPayloadException extends WireModalsException
{
    public static function missing(string $field): self
    {
        return new self("The trigger payload has no [{$field}].");
    }

    public static function invalid(string $field, string $reason): self
    {
        return new self("The trigger payload [{$field}] {$reason}.");
    }
}
