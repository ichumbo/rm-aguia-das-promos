<?php

namespace App\Services\WhatsApp;

use App\Exceptions\DomainRuleException;
use App\Jobs\SendInvitationJob;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Invitation;
use App\Models\MessageEvent;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\EligibilityService;
use App\Services\PhoneNormalizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvitationDispatchService
{
    public function __construct(
        private readonly EligibilityService $eligibilityService,
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly WhatsAppConfigStatus $configStatus,
    ) {}

    public static function buildWhatsAppSendUrl(string $digits, string $message): string
    {
        // Use api.whatsapp.com/send directly instead of wa.me shortlink redirect,
        // which can corrupt 4-byte UTF-8 emojis (%F0%9F...) into U+FFFD replacement chars.
        return 'https://api.whatsapp.com/send?phone=' . $digits . '&text=' . rawurlencode($message);
    }

    public function buildWaMeUrl(string $phoneE164, string $message): string
    {
        $digits = $this->phoneNormalizer->digitsOnly($phoneE164);

        return self::buildWhatsAppSendUrl($digits, $message);
    }

    public function openAssistedInvite(
        Contact $contact,
        Group $group,
        MessageTemplate $template,
        User $actor,
        bool $isResend = false,
        ?string $resendReason = null
    ): Invitation {
        $lockKey = "invite:assisted:{$contact->id}:{$group->id}:" . ($isResend ? 'resend' : 'initial');

        return Cache::lock($lockKey, 10)->block(5, function () use ($contact, $group, $template, $actor, $isResend, $resendReason): Invitation {
            if (! $isResend) {
                $existing = Invitation::query()
                    ->where('contact_id', $contact->id)
                    ->where('group_id', $group->id)
                    ->where('mode', Invitation::MODE_ASSISTED)
                    ->whereNotIn('status', [Invitation::STATUS_FAILED, Invitation::STATUS_CANCELLED])
                    ->first();

                if ($existing !== null) {
                    if ($existing->status === Invitation::STATUS_ASSISTED_OPENED && str_starts_with((string) $existing->wa_me_url, 'https://wa.me/')) {
                        $existing->update([
                            'wa_me_url' => $this->buildWaMeUrl($contact->phone_e164, (string) $existing->rendered_message),
                        ]);
                    }

                    return $existing;
                }
            }

            $this->eligibilityService->assertCanInvite(
                $contact,
                $group,
                $template,
                Invitation::MODE_ASSISTED,
                $isResend,
                $resendReason
            );

            $rendered = $template->render($this->eligibilityService->resolveTemplateVariables($contact, $group));
            $waMeUrl = $this->buildWaMeUrl($contact->phone_e164, $rendered);
            $activeDedupKey = $isResend ? null : "{$contact->id}:{$group->id}";
            $idempotencyKey = $isResend
                ? "assisted:resend:{$contact->id}:{$group->id}:" . Str::uuid()
                : "assisted:{$contact->id}:{$group->id}";

            return DB::transaction(function () use ($contact, $group, $template, $actor, $rendered, $waMeUrl, $activeDedupKey, $idempotencyKey, $isResend, $resendReason): Invitation {
                $invitation = Invitation::create([
                    'contact_id' => $contact->id,
                    'group_id' => $group->id,
                    'message_template_id' => $template->id,
                    'user_id' => $actor->id,
                    'mode' => Invitation::MODE_ASSISTED,
                    'idempotency_key' => $idempotencyKey,
                    'active_dedup_key' => $activeDedupKey,
                    'is_resend' => $isResend,
                    'resend_reason' => $resendReason,
                    'status' => Invitation::STATUS_ASSISTED_OPENED,
                    'status_origin' => 'manual',
                    'record_source' => $contact->record_source === Contact::SOURCE_DEMO ? Contact::SOURCE_DEMO : Contact::SOURCE_MANUAL,
                    'rendered_message' => $rendered,
                    'wa_me_url' => $waMeUrl,
                ]);

                MessageEvent::create([
                    'invitation_id' => $invitation->id,
                    'contact_id' => $contact->id,
                    'provider_event_id' => 'assisted.open.' . $invitation->id . '.' . Str::uuid(),
                    'event_type' => 'assisted_opened',
                    'event_timestamp' => now(),
                    'payload_summary' => [
                        'mode' => Invitation::MODE_ASSISTED,
                        'group_id' => $group->id,
                        'phone_masked' => $this->phoneNormalizer->mask($contact->phone_e164),
                    ],
                    'source' => 'manual',
                ]);

                AuditLog::record($isResend ? 'invitation.assisted_resent' : 'invitation.assisted_opened', $invitation, [
                    'contact_id' => $contact->id,
                    'group_id' => $group->id,
                    'reason' => $resendReason,
                ], $actor->id);

                return $invitation;
            });
        });
    }

    public function confirmManualSend(Invitation $invitation, User $actor): Invitation
    {
        if ($invitation->mode !== Invitation::MODE_ASSISTED) {
            throw new DomainRuleException('Confirmação manual só se aplica a convites do modo assistido.');
        }

        if ($invitation->status === Invitation::STATUS_ASSISTED_SENT_MANUAL) {
            return $invitation;
        }

        return DB::transaction(function () use ($invitation, $actor): Invitation {
            $invitation->update([
                'status' => Invitation::STATUS_ASSISTED_SENT_MANUAL,
                'status_origin' => 'manual',
                'record_source' => $invitation->contact->record_source === Contact::SOURCE_DEMO ? Contact::SOURCE_DEMO : Contact::SOURCE_MANUAL,
                'sent_at' => now(),
            ]);

            $contact = $invitation->contact;
            $updates = ['last_invited_at' => now()];
            if (in_array($contact->pipeline_stage, [Contact::STAGE_NOVO, Contact::STAGE_AUTORIZADO], true)) {
                $updates['pipeline_stage'] = Contact::STAGE_CONVITE_ENVIADO;
            }
            $contact->update($updates);

            MessageEvent::create([
                'invitation_id' => $invitation->id,
                'contact_id' => $contact->id,
                'provider_event_id' => 'manual.confirm.' . $invitation->id . '.' . Str::uuid(),
                'event_type' => 'manual_confirmed',
                'event_timestamp' => now(),
                'payload_summary' => [
                    'confirmed_by' => $actor->id,
                    'origin' => 'manual',
                ],
                'source' => 'manual',
            ]);

            AuditLog::record('invitation.manual_confirmed', $invitation, [
                'contact_id' => $contact->id,
                'group_id' => $invitation->group_id,
            ], $actor->id);

            return $invitation->fresh();
        });
    }

    public function queueOfficialInvite(
        Contact $contact,
        Group $group,
        MessageTemplate $template,
        User $actor,
        bool $isResend = false,
        ?string $resendReason = null,
        bool $simulated = false
    ): Invitation {
        $mode = $simulated ? Invitation::MODE_SIMULATED : Invitation::MODE_OFFICIAL_API;

        if (! $simulated && ! $this->configStatus->isConfigured()) {
            throw new DomainRuleException('API oficial do WhatsApp não configurada. Utilize o Modo Assistido ou configure as credenciais da Meta.');
        }

        $lockKey = "invite:api:{$contact->id}:{$group->id}:" . ($isResend ? 'resend' : 'initial');

        return Cache::lock($lockKey, 10)->block(5, function () use ($contact, $group, $template, $actor, $isResend, $resendReason, $mode): Invitation {
            if (! $isResend) {
                $existing = Invitation::query()
                    ->where('contact_id', $contact->id)
                    ->where('group_id', $group->id)
                    ->whereIn('mode', [Invitation::MODE_OFFICIAL_API, Invitation::MODE_SIMULATED])
                    ->whereNotIn('status', [Invitation::STATUS_FAILED, Invitation::STATUS_CANCELLED])
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            $this->eligibilityService->assertCanInvite(
                $contact,
                $group,
                $template,
                $mode,
                $isResend,
                $resendReason
            );

            $rendered = $template->render($this->eligibilityService->resolveTemplateVariables($contact, $group));
            $activeDedupKey = $isResend ? null : "{$contact->id}:{$group->id}";
            $idempotencyKey = $isResend
                ? "{$mode}:resend:{$contact->id}:{$group->id}:" . Str::uuid()
                : "{$mode}:{$contact->id}:{$group->id}";

            $recordSource = $mode === Invitation::MODE_SIMULATED || $contact->record_source === Contact::SOURCE_DEMO
                ? Contact::SOURCE_DEMO
                : Contact::SOURCE_REAL;

            try {
                $invitation = DB::transaction(function () use ($contact, $group, $template, $actor, $mode, $idempotencyKey, $activeDedupKey, $isResend, $resendReason, $recordSource, $rendered): Invitation {
                    $invitation = Invitation::create([
                        'contact_id' => $contact->id,
                        'group_id' => $group->id,
                        'message_template_id' => $template->id,
                        'user_id' => $actor->id,
                        'mode' => $mode,
                        'idempotency_key' => $idempotencyKey,
                        'active_dedup_key' => $activeDedupKey,
                        'is_resend' => $isResend,
                        'resend_reason' => $resendReason,
                        'status' => Invitation::STATUS_QUEUED,
                        'status_origin' => $mode === Invitation::MODE_SIMULATED ? 'simulated' : 'system',
                        'record_source' => $recordSource,
                        'rendered_message' => $rendered,
                        'queued_at' => now(),
                    ]);

                    AuditLog::record($isResend ? 'invitation.resent' : 'invitation.queued', $invitation, [
                        'mode' => $mode,
                        'contact_id' => $contact->id,
                        'group_id' => $group->id,
                        'reason' => $resendReason,
                    ], $actor->id);

                    return $invitation;
                });
            } catch (UniqueConstraintViolationException) {
                $existing = Invitation::query()
                    ->where('contact_id', $contact->id)
                    ->where('group_id', $group->id)
                    ->whereNotIn('status', [Invitation::STATUS_FAILED, Invitation::STATUS_CANCELLED])
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }

                throw new DomainRuleException('Operação já registrada para este contato e grupo.');
            }

            SendInvitationJob::dispatch($invitation->id);

            return $invitation;
        });
    }
}

