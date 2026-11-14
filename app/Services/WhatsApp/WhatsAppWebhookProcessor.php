<?php

namespace App\Services\WhatsApp;

use App\Exceptions\InvalidPhoneException;
use App\Models\Contact;
use App\Models\Invitation;
use App\Models\MessageEvent;
use App\Services\PhoneNormalizer;
use App\Services\SuppressionService;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class WhatsAppWebhookProcessor
{
    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly SuppressionService $suppressionService,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function process(array $payload): void
    {
        $entries = $payload['entry'] ?? [];
        if (! is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            if (! is_array($changes)) {
                continue;
            }

            foreach ($changes as $change) {
                $value = $change['value'] ?? [];
                if (! is_array($value)) {
                    continue;
                }

                foreach (($value['statuses'] ?? []) as $statusItem) {
                    if (is_array($statusItem)) {
                        $this->processStatusUpdate($statusItem);
                    }
                }

                foreach (($value['messages'] ?? []) as $messageItem) {
                    if (is_array($messageItem)) {
                        $this->processInboundMessage($messageItem);
                    }
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $statusItem
     */
    private function processStatusUpdate(array $statusItem): void
    {
        $wamid = (string) ($statusItem['id'] ?? '');
        $status = (string) ($statusItem['status'] ?? '');
        $timestampRaw = (string) ($statusItem['timestamp'] ?? '');

        if ($wamid === '' || $status === '') {
            return;
        }

        $eventId = "status:{$wamid}:{$status}:{$timestampRaw}";
        if (MessageEvent::query()->where('provider_event_id', $eventId)->exists()) {
            return;
        }

        $invitation = Invitation::query()->where('provider_message_id', $wamid)->first();
        $eventTime = is_numeric($timestampRaw) ? Carbon::createFromTimestamp((int) $timestampRaw) : now();

        try {
            DB::transaction(function () use ($eventId, $status, $statusItem, $invitation, $eventTime): void {
                MessageEvent::create([
                    'invitation_id' => $invitation?->id,
                    'contact_id' => $invitation?->contact_id,
                    'provider_event_id' => $eventId,
                    'event_type' => 'status_' . $status,
                    'event_timestamp' => $eventTime,
                    'payload_summary' => [
                        'status' => $status,
                        'errors' => $statusItem['errors'] ?? null,
                    ],
                    'source' => 'webhook',
                ]);

                if ($invitation === null) {
                    return;
                }

                $mappedStatus = match ($status) {
                    'sent' => Invitation::STATUS_SENT,
                    'delivered' => Invitation::STATUS_DELIVERED,
                    'read' => Invitation::STATUS_READ,
                    'failed' => Invitation::STATUS_FAILED,
                    default => null,
                };

                if ($mappedStatus === null) {
                    return;
                }

                $updates = [];

                // Always record the milestone timestamp without regressing current higher status
                if ($mappedStatus === Invitation::STATUS_SENT && $invitation->sent_at === null) {
                    $updates['sent_at'] = $eventTime;
                }
                if ($mappedStatus === Invitation::STATUS_DELIVERED && $invitation->delivered_at === null) {
                    $updates['delivered_at'] = $eventTime;
                }
                if ($mappedStatus === Invitation::STATUS_READ && $invitation->read_at === null) {
                    $updates['read_at'] = $eventTime;
                }

                $incomingRank = match ($mappedStatus) {
                    Invitation::STATUS_SENT => 30,
                    Invitation::STATUS_DELIVERED => 40,
                    Invitation::STATUS_READ => 50,
                    Invitation::STATUS_FAILED => 25,
                    default => 0,
                };

                if ($mappedStatus === Invitation::STATUS_FAILED) {
                    if ($invitation->statusRank() < 40) {
                        $updates['status'] = Invitation::STATUS_FAILED;
                        $updates['failed_at'] = $eventTime;
                        $updates['status_origin'] = 'webhook';
                        $updates['active_dedup_key'] = null;
                        $updates['error_code'] = (string) data_get($statusItem, 'errors.0.code', 'META_DELIVERY_FAILED');
                        $updates['error_message'] = (string) data_get($statusItem, 'errors.0.title', 'Falha na entrega informada pela Meta.');
                    }
                } elseif ($incomingRank > $invitation->statusRank()) {
                    $updates['status'] = $mappedStatus;
                    $updates['status_origin'] = 'webhook';
                }

                if ($updates !== []) {
                    $invitation->update($updates);
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Concurrent duplicate webhook event safely ignored
        }
    }

    /**
     * @param array<string, mixed> $messageItem
     */
    private function processInboundMessage(array $messageItem): void
    {
        $wamid = (string) ($messageItem['id'] ?? '');
        $fromRaw = (string) ($messageItem['from'] ?? '');
        $timestampRaw = (string) ($messageItem['timestamp'] ?? '');
        $body = trim((string) data_get($messageItem, 'text.body', data_get($messageItem, 'button.text', '')));

        if ($wamid === '' || $fromRaw === '') {
            return;
        }

        $eventId = "inbound:{$wamid}";
        if (MessageEvent::query()->where('provider_event_id', $eventId)->exists()) {
            return;
        }

        try {
            $phoneE164 = $this->phoneNormalizer->normalize('+' . ltrim($fromRaw, '+'));
        } catch (InvalidPhoneException) {
            return;
        }

        $contact = Contact::query()->where('phone_e164', $phoneE164)->first();
        $eventTime = is_numeric($timestampRaw) ? Carbon::createFromTimestamp((int) $timestampRaw) : now();
        $isOptOut = $this->isOptOutText($body);

        try {
            DB::transaction(function () use ($eventId, $contact, $phoneE164, $eventTime, $body, $isOptOut, $wamid): void {
                MessageEvent::create([
                    'invitation_id' => null,
                    'contact_id' => $contact?->id,
                    'provider_event_id' => $eventId,
                    'event_type' => $isOptOut ? 'opt_out_keyword' : 'inbound_message',
                    'event_timestamp' => $eventTime,
                    'payload_summary' => [
                        'phone_masked' => $this->phoneNormalizer->mask($phoneE164),
                        'text_excerpt' => mb_substr($body, 0, 120),
                        'opt_out_detected' => $isOptOut,
                    ],
                    'source' => 'webhook',
                ]);

                if ($contact === null) {
                    return;
                }

                $contact->update([
                    'last_inbound_at' => $eventTime,
                ]);

                if ($isOptOut) {
                    $this->suppressionService->optOut(
                        $contact,
                        reason: 'opt_out_palavra_chave',
                        source: 'webhook',
                        evidenceReference: $wamid
                    );

                    return;
                }

                if (in_array($contact->pipeline_stage, [Contact::STAGE_NOVO, Contact::STAGE_AUTORIZADO, Contact::STAGE_CONVITE_ENVIADO], true)) {
                    $contact->update([
                        'pipeline_stage' => Contact::STAGE_RESPONDEU,
                    ]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Duplicate inbound message safely ignored
        }
    }

    private function isOptOutText(string $body): bool
    {
        if ($body === '') {
            return false;
        }

        $normalized = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $body) ?? ''), 'UTF-8');
        $keywords = config('aguia.opt_out_keywords', ['SAIR', 'PARAR']);

        foreach ($keywords as $keyword) {
            $upperKeyword = mb_strtoupper((string) $keyword, 'UTF-8');
            if ($normalized === $upperKeyword) {
                return true;
            }
        }

        return false;
    }
}
