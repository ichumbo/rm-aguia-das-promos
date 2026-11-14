<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\AuditLog;
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
}
