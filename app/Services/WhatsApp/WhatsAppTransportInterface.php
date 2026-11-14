<?php

namespace App\Services\WhatsApp;

use App\Models\Invitation;

interface WhatsAppTransportInterface
{
    public function sendTemplateInvitation(Invitation $invitation): WhatsAppSendResult;
}
