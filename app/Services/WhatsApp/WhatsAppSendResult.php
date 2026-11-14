<?php

namespace App\Services\WhatsApp;

readonly class WhatsAppSendResult
{
    public function __construct(
        public bool $accepted,
        public bool $uncertain = false,
        public bool $rateLimited = false,
        public bool $transientFailure = false,
        public ?string $providerMessageId = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public ?int $retryAfterSeconds = null,
        public bool $isSimulated = false,
    ) {}
}
