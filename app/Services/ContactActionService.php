<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\ConsentEvent;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Invitation;
use App\Models\MessageEvent;
use App\Models\MessageTemplate;
use App\Models\SuppressionEntry;
use App\Models\User;
use App\Services\WhatsApp\InvitationDispatchService;
use App\Services\WhatsApp\WhatsAppConfigStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContactActionService
{
    public const MAX_BULK_BATCH = 3;

    public function __construct(
        private readonly InvitationDispatchService $dispatchService,
        private readonly WhatsAppConfigStatus $configStatus,
        private readonly SuppressionService $suppressionService,
    ) {}

    /**
     * Trigger primary action for a contact based on current CRM mode.
     *
     * @return array{mode: string, invitation: Invitation, wa_me_url: string|null, message: string}
     */
    public function triggerPrimaryInvite(
        Contact $contact,
        User $actor,
        ?int $groupId = null,
        ?int $templateId = null,
        bool $isResend = false,
        ?string $resendReason = null
    ): array {
        $group = $groupId !== null
            ? Group::query()->find($groupId)
            : ($contact->defaultGroup ?? Group::query()->where('is_active', true)->whereNull('revoked_at')->first());

        if ($group === null) {
            throw new DomainRuleException('Nenhum grupo ativo definido para este contato.');
        }

        $template = $templateId !== null
            ? MessageTemplate::query()->find($templateId)
            : (MessageTemplate::query()->where('is_default', true)->first() ?? MessageTemplate::query()->first());

        if ($template === null) {
            throw new DomainRuleException('Nenhum modelo de mensagem cadastrado.');
        }

        $mode = $this->configStatus->currentMode();

        if ($mode === WhatsAppConfigStatus::MODE_ASSISTED) {
            $invitation = $this->dispatchService->openAssistedInvite(
                $contact,
                $group,
                $template,
                $actor,
                $isResend,
                $resendReason
            );

            return [
                'mode' => $mode,
                'invitation' => $invitation,
                'wa_me_url' => $invitation->wa_me_url,
                'message' => 'Conversa aberta no WhatsApp (modo assistido). Confirme o envio manualmente após enviar no aplicativo.',
            ];
        }

        $simulated = $mode === WhatsAppConfigStatus::MODE_SIMULATED;
        $invitation = $this->dispatchService->queueOfficialInvite(
            $contact,
            $group,
            $template,
            $actor,
            $isResend,
            $resendReason,
            $simulated
        );

        return [
            'mode' => $mode,
            'invitation' => $invitation,
            'wa_me_url' => null,
            'message' => $simulated
                ? 'Convite enfileirado em MODO SIMULADO (não é envio real).'
                : 'Convite na fila para envio pela API oficial.',
        ];
    }

    public function confirmManualSend(Invitation $invitation, User $actor): Invitation
    {
        return $this->dispatchService->confirmManualSend($invitation, $actor);
    }

    public function markResponded(Contact $contact, User $actor): Contact
    {
        return DB::transaction(function () use ($contact, $actor): Contact {
            $contact->update([
                'pipeline_stage' => Contact::STAGE_RESPONDEU,
                'last_inbound_at' => $contact->last_inbound_at ?? now(),
            ]);

            MessageEvent::create([
                'contact_id' => $contact->id,
                'provider_event_id' => 'manual.responded.' . $contact->id . '.' . Str::uuid(),
                'event_type' => 'manual_response_marked',
                'event_timestamp' => now(),
                'payload_summary' => ['marked_by' => $actor->id],
                'source' => 'manual',
            ]);

            AuditLog::record('contact.marked_responded', $contact, [], $actor->id);

            return $contact->fresh();
        });
    }

    public function confirmGroupEntry(Contact $contact, User $actor): Contact
    {
        return DB::transaction(function () use ($contact, $actor): Contact {
            $contact->update([
                'pipeline_stage' => Contact::STAGE_ENTRADA_CONFIRMADA,
                'joined_confirmed_at' => now(),
                'joined_confirmed_by' => $actor->id,
            ]);

            AuditLog::record('contact.joined_confirmed', $contact, [
                'group_id' => $contact->default_group_id,
            ], $actor->id);

            return $contact->fresh();
        });
    }

    public function optOut(Contact $contact, User $actor, string $reason = 'nao_contatar_operador'): SuppressionEntry
    {
        return $this->suppressionService->optOut(
            $contact,
            reason: $reason,
            source: 'operator',
            evidenceReference: 'acao_operador_' . $actor->id,
            actor: $actor
        );
    }

    public function authorizePendingContact(
        Contact $contact,
        User $actor,
        string $evidenceReference = 'autorizacao_operador'
    ): Contact {
        if ($contact->isSuppressed() || $contact->consent_status === Contact::CONSENT_REVOGADO) {
            throw new DomainRuleException('Contato descadastrado não pode ser reativado ou autorizado.');
        }

        return DB::transaction(function () use ($contact, $actor, $evidenceReference): Contact {
            $policyVersion = (string) AppSetting::getValue(
                'consent_policy_version',
                config('aguia.default_policy_version')
            );

            ConsentEvent::create([
                'contact_id' => $contact->id,
                'status' => ConsentEvent::STATUS_GRANTED,
                'origin' => 'autorizacao_operador',
                'scope' => 'convite_grupos_aguia',
                'policy_version' => $policyVersion,
                'evidence_reference' => trim($evidenceReference) !== '' ? trim($evidenceReference) : 'autorizacao_operador',
                'recorded_by' => $actor->id,
                'occurred_at' => now(),
            ]);

            $contact->update([
                'consent_status' => Contact::CONSENT_AUTORIZADO,
                'pipeline_stage' => $contact->pipeline_stage === Contact::STAGE_NOVO
                    ? Contact::STAGE_AUTORIZADO
                    : $contact->pipeline_stage,
            ]);

            AuditLog::record('contact.consent_granted_by_operator', $contact, [
                'evidence_reference' => $evidenceReference,
            ], $actor->id);

            return $contact->fresh();
        });
    }

    /**
     * Trigger invites for up to MAX_BULK_BATCH (3) contacts per batch.
     *
     * @param list<int|string> $contactIds
     * @return array{
     *     count: int,
     *     wa_links: list<array{invitation_id: int, contact_name: string, phone: string, url: string}>,
     *     skipped: list<string>
     * }
     */
    public function triggerBulkInvites(array $contactIds, User $actor, ?int $templateId = null): array
    {
        $uniqueIds = array_values(array_unique(array_filter(
            array_map('intval', $contactIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($uniqueIds === []) {
            throw new DomainRuleException('Nenhum contato elegível encontrado para envio em massa.');
        }

        if (count($uniqueIds) > self::MAX_BULK_BATCH) {
            throw new DomainRuleException('O envio em massa permite no máximo ' . self::MAX_BULK_BATCH . ' contatos por vez.');
        }

        $contacts = Contact::query()
            ->with('defaultGroup')
            ->whereIn('id', $uniqueIds)
            ->get()
            ->keyBy('id');

        $sentCount = 0;
        $waLinks = [];
        $skipped = [];

        foreach ($uniqueIds as $id) {
            $contact = $contacts->get($id);
            if ($contact === null) {
                $skipped[] = "Contato #{$id} não encontrado.";
                continue;
            }

            try {
                $result = $this->triggerPrimaryInvite($contact, $actor, templateId: $templateId);
                $sentCount++;

                if ($result['wa_me_url'] !== null) {
                    $waLinks[] = [
                        'invitation_id' => $result['invitation']->id,
                        'contact_name' => $contact->name,
                        'phone' => $contact->phone_e164,
                        'url' => $result['wa_me_url'],
                    ];
                }
            } catch (DomainRuleException $e) {
                $skipped[] = "{$contact->name}: {$e->getMessage()}";
            }
        }

        if ($sentCount === 0) {
            throw new DomainRuleException($skipped[0] ?? 'Nenhum contato elegível para envio em massa.');
        }

        AuditLog::record('invitation.bulk_triggered', null, [
            'count' => $sentCount,
            'contact_ids' => $uniqueIds,
        ], $actor->id);

        return [
            'count' => $sentCount,
            'wa_links' => $waLinks,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param list<int|string> $invitationIds
     */
    public function confirmBulkManualSends(array $invitationIds, User $actor): int
    {
        $uniqueIds = array_values(array_unique(array_filter(
            array_map('intval', $invitationIds),
            static fn (int $id): bool => $id > 0
        )));

        $invitations = Invitation::query()
            ->whereIn('id', $uniqueIds)
            ->where('status', Invitation::STATUS_ASSISTED_OPENED)
            ->get();

        $confirmed = 0;
        foreach ($invitations as $invitation) {
            $this->confirmManualSend($invitation, $actor);
            $confirmed++;
        }

        return $confirmed;
    }
}

