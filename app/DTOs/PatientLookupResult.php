<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\LookupStatus;

final readonly class PatientLookupResult
{
    public function __construct(
        public LookupStatus $status,
        public ?PatientData $patient = null,
        public ?string $message = null,
        public ?string $sourceFetchedAt = null,
    ) {
    }

    public function isFound(): bool
    {
        return $this->status === LookupStatus::Found && $this->patient !== null;
    }

    public function displayMessage(): string
    {
        return $this->message ?? $this->status->userMessageTh();
    }
}
