<?php

namespace App\Services\WhatsApp;

use App\Models\Invitation;
use App\Services\PhoneNormalizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class MetaCloudWhatsAppTransport implements WhatsAppTransportInterface
{
    public function __construct(
        private readonly WhatsAppConfigStatus $configStatus,
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {}

    public function sendTemplateInvitation(Invitation $invitation): WhatsAppSendResult
    {
        if (! $this->configStatus->isConfigured()) {
            return new WhatsAppSendResult(
                accepted: false,
                errorCode: 'API_NOT_CONFIGURED',
                errorMessage: 'API oficial do WhatsApp não configurada no servidor.'
            );
        }

        $invitation->loadMissing(['contact', 'group', 'template']);
        $contact = $invitation->contact;
        $group = $invitation->group;
        $template = $invitation->template;

        if ($contact === null || $group === null || $template === null) {
            return new WhatsAppSendResult(
                accepted: false,
                errorCode: 'INVALID_INVITATION_PAYLOAD',
                errorMessage: 'Dados do contato, grupo ou modelo ausentes para envio.'
            );
        }

        $version = (string) config('services.whatsapp.api_version', 'v21.0');
        $baseUrl = rtrim((string) config('services.whatsapp.base_url', 'https://graph.facebook.com'), '/');
        $phoneNumberId = (string) config('services.whatsapp.phone_number_id');
        $token = (string) config('services.whatsapp.access_token');
        $timeout = (int) config('services.whatsapp.timeout_seconds', 10);
        $connectTimeout = (int) config('services.whatsapp.connect_timeout_seconds', 5);

        $endpoint = "{$baseUrl}/{$version}/{$phoneNumberId}/messages";
        $recipientDigits = $this->phoneNormalizer->digitsOnly($contact->phone_e164);

        $placeholders = $template->extractPlaceholders();
        $varMap = [
            'nome' => trim($contact->name) ?: 'Olá',
            'categoria' => $group->category,
            'grupo' => $group->name,
            'link_grupo' => $group->invite_url,
        ];

        $parameters = [];
        foreach ($placeholders as $placeholder) {
            $parameters[] = [
                'type' => 'text',
                'text' => (string) ($varMap[$placeholder] ?? ''),
            ];
        }

        $components = [];
        if ($headerUrl = $template->publicSignedMediaUrl()) {
            $components[] = [
                'type' => 'header',
                'parameters' => [
                    [
                        'type' => 'image',
                        'image' => [
                            'link' => $headerUrl,
                        ],
                    ],
                ],
            ];
        }

        if ($parameters !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => $parameters,
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $recipientDigits,
            'type' => 'template',
            'template' => [
                'name' => $template->name,
                'language' => [
                    'code' => $template->language,
                ],
                'components' => $components,
            ],
        ];

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->post($endpoint, $payload);
        } catch (ConnectionException $e) {
            $rawMessage = $e->getMessage();
            $sanitized = $this->configStatus->sanitizeError($rawMessage);

            // If timeout occurred after request may have left the client, mark UNCERTAIN to prevent duplicate blind retries
            $isReadOrOperationTimeout = str_contains(mb_strtolower($rawMessage), 'timed out')
                || str_contains(mb_strtolower($rawMessage), 'timeout')
                || str_contains($rawMessage, 'cURL error 28');

            if ($isReadOrOperationTimeout) {
                return new WhatsAppSendResult(
                    accepted: false,
                    uncertain: true,
                    errorCode: 'API_TIMEOUT_UNCERTAIN',
                    errorMessage: 'Timeout na comunicação com a Meta após possível aceitação. Resultado marcado como incerto para evitar envio duplicado. ' . $sanitized,
                );
            }

            return new WhatsAppSendResult(
                accepted: false,
                transientFailure: true,
                errorCode: 'NETWORK_CONNECTION_ERROR',
                errorMessage: 'Falha de conexão com a API da Meta: ' . $sanitized,
            );
        }

        $status = $response->status();
        $json = $response->json() ?? [];

        if ($response->successful()) {
            $messageId = data_get($json, 'messages.0.id');

            return new WhatsAppSendResult(
                accepted: true,
                providerMessageId: is_string($messageId) ? $messageId : null,
            );
        }

        $retryAfter = $response->header('Retry-After');
        $retrySeconds = is_numeric($retryAfter) ? max(5, (int) $retryAfter) : 60;
        $metaCode = (string) data_get($json, 'error.code', $status);
        $metaMessage = (string) data_get($json, 'error.message', $response->body());
        $sanitizedError = $this->configStatus->sanitizeError("HTTP {$status} ({$metaCode}): {$metaMessage}");

        if ($status === 429 || in_array((int) $metaCode, [4, 17, 130429, 131048, 131056], true)) {
            return new WhatsAppSendResult(
                accepted: false,
                rateLimited: true,
                transientFailure: true,
                errorCode: $metaCode,
                errorMessage: $sanitizedError,
                retryAfterSeconds: $retrySeconds,
            );
        }

        if ($status >= 500) {
            return new WhatsAppSendResult(
                accepted: false,
                transientFailure: true,
                errorCode: $metaCode,
                errorMessage: $sanitizedError,
            );
        }

        return new WhatsAppSendResult(
            accepted: false,
            errorCode: $metaCode,
            errorMessage: $sanitizedError,
        );
    }
}

