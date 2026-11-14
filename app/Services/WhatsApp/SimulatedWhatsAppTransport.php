<?php

namespace App\Services\WhatsApp;

use App\Models\Invitation;
use Illuminate\Support\Str;

class SimulatedWhatsAppTransport implements WhatsAppTransportInterface
{
    public function sendTemplateInvitation(Invitation $invitation): WhatsAppSendResult
    {
        return new WhatsAppSendResult(
            accepted: true,
            providerMessageId: 'sim.wamid.' . Str::uuid(),
            isSimulated: true,
        );
    }
}
