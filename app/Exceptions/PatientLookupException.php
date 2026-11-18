<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\LookupStatus;
use RuntimeException;
use Throwable;

class PatientLookupException extends RuntimeException
{
    public function __construct(
        public readonly LookupStatus $lookupStatus,
        string $internalMessage = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($internalMessage !== '' ? $internalMessage : $lookupStatus->value, $code, $previous);
    }

    public static function timeout(string $reason = 'Query timeout', ?Throwable $previous = null): self
    {
        return new self(LookupStatus::Timeout, $reason, 0, $previous);
    }

    public static function connectionFailed(string $reason = 'Connection failed', ?Throwable $previous = null): self
    {
        return new self(LookupStatus::ConnectionFailed, $reason, 0, $previous);
    }

    public static function mappingError(string $reason = 'Column mapping error', ?Throwable $previous = null): self
    {
        return new self(LookupStatus::MappingError, $reason, 0, $previous);
    }

    public static function unexpected(string $reason = 'Unexpected lookup error', ?Throwable $previous = null): self
    {
        return new self(LookupStatus::UnexpectedError, $reason, 0, $previous);
    }
}
