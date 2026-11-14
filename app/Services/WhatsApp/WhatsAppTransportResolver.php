<?php

namespace App\Services\WhatsApp;

use App\Models\Invitation;

class WhatsAppTransportResolver
{
    public function __construct(
        private readonly MetaCloudWhatsAppTransport $metaTransport,
        private readonly SimulatedWhatsAppTransport $simulatedTransport,
    ) {}

    public function forMode(string $mode): WhatsAppTransportInterface
    {
        return match ($mode) {
            Invitation::MODE_SIMULATED => $this->simulatedTransport,
            default => $this->metaTransport,
        };
    }
}
