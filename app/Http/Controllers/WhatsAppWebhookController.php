<?php

namespace App\Http\Controllers;

use App\Services\WhatsApp\WhatsAppWebhookProcessor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = (string) ($request->query('hub_mode') ?? $request->query('hub.mode', ''));
        $token = (string) ($request->query('hub_verify_token') ?? $request->query('hub.verify_token', ''));
        $challenge = (string) ($request->query('hub_challenge') ?? $request->query('hub.challenge', ''));

        $expectedToken = (string) config('services.whatsapp.verify_token', '');

        if ($expectedToken !== '' && $mode === 'subscribe' && hash_equals($expectedToken, $token)) {
            return response($challenge, 200, ['Content-Type' => 'text/plain']);
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request, WhatsAppWebhookProcessor $processor): Response
    {
        $appSecret = (string) config('services.whatsapp.app_secret', '');
        $signatureHeader = (string) $request->header('X-Hub-Signature-256', '');

        if ($appSecret === '' || ! str_starts_with($signatureHeader, 'sha256=')) {
            return response('Invalid signature', 403);
        }

        $providedHash = substr($signatureHeader, 7);
        $expectedHash = hash_hmac('sha256', $request->getContent(), $appSecret);

        if (! hash_equals($expectedHash, $providedHash)) {
            return response('Invalid signature', 403);
        }

        $payload = json_decode($request->getContent(), true);
        if (is_array($payload)) {
            $processor->process($payload);
        }

        return response('EVENT_RECEIVED', 200);
    }
}
