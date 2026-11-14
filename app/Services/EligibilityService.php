<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Exceptions\InvalidPhoneException;
use App\Models\ConsentEvent;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Invitation;
use App\Models\MessageTemplate;
use App\Models\SuppressionEntry;

class EligibilityService
{
    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer
    ) {}

    /**
     * Assert full eligibility right before sending or queuing an invitation.
     *
     * @throws DomainRuleException
     */
    public function assertCanInvite(
        Contact $contact,
        ?Group $group,
        ?MessageTemplate $template,
        string $mode,
        bool $isResend = false,
        ?string $resendReason = null,
        ?int $ignoreInvitationId = null
    ): void {
        if (trim((string) $contact->phone_e164) === '') {
            throw new DomainRuleException('Contato sem telefone normalizado.');
        }

        try {
            $this->phoneNormalizer->normalize($contact->phone_e164);
        } catch (InvalidPhoneException $e) {
            throw new DomainRuleException('Telefone do contato inválido: ' . $e->getMessage());
        }

        // Priority 1: independent suppression block
        $suppressed = SuppressionEntry::query()
            ->where('phone_e164', $contact->phone_e164)
            ->orWhere('contact_id', $contact->id)
            ->exists();

        if ($suppressed || $contact->consent_status === Contact::CONSENT_REVOGADO) {
            throw new DomainRuleException('Contato descadastrado/bloqueado para envio.');
        }

        // Priority 2: explicit authorization required
        if ($contact->consent_status !== Contact::CONSENT_AUTORIZADO) {
            throw new DomainRuleException('Contato sem autorização registrada não pode receber convites.');
        }

        $latestConsent = ConsentEvent::query()
            ->where('contact_id', $contact->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first();

        if ($latestConsent === null || $latestConsent->status !== ConsentEvent::STATUS_GRANTED) {
            throw new DomainRuleException('Não há registro válido de autorização concedida para este contato.');
        }

        // Priority 3: valid active group link
        if ($group === null || ! $group->hasValidInviteUrl()) {
            throw new DomainRuleException('Grupo de destino inativo, revogado ou sem link oficial válido.');
        }

        // Priority 4: template checks
        if ($template === null) {
            throw new DomainRuleException('Selecione um modelo de mensagem válido.');
        }

        if ($mode === Invitation::MODE_OFFICIAL_API) {
            if (! $template->isUsable()) {
                throw new DomainRuleException("Modelo '{$template->display_name}' bloqueado (status: {$template->status}). Apenas modelos APPROVED podem iniciar conversas na API.");
            }

            if (trim((string) $template->language) === '') {
                throw new DomainRuleException('Idioma do modelo não definido.');
            }
        } elseif (! $template->isUsable() && $template->status === MessageTemplate::STATUS_REJECTED) {
            throw new DomainRuleException("Modelo '{$template->display_name}' está recusado e bloqueado para envio.");
        }

        // Validate required template variables against contact/group
        $placeholders = $template->extractPlaceholders();
        $values = $this->resolveTemplateVariables($contact, $group);
        foreach ($placeholders as $placeholder) {
            if (! array_key_exists($placeholder, $values) || trim((string) $values[$placeholder]) === '') {
                throw new DomainRuleException("Variável obrigatória '{{{$placeholder}}}' ausente para renderizar o modelo.");
            }
        }

        // Priority 5: prevent duplicate invitation for same contact + group unless explicit audited resend
        if (! $isResend) {
            $existingQuery = Invitation::query()
                ->where('contact_id', $contact->id)
                ->where('group_id', $group->id)
                ->whereNotIn('status', [Invitation::STATUS_FAILED, Invitation::STATUS_CANCELLED]);

            if ($ignoreInvitationId !== null) {
                $existingQuery->where('id', '!=', $ignoreInvitationId);
            }

            if ($existingQuery->exists()) {
                throw new DomainRuleException('Já existe um convite registrado para este contato e grupo. Use "Reenviar" explicitamente com justificativa caso necessário.');
            }
        } elseif (trim((string) $resendReason) === '') {
            throw new DomainRuleException('Reenvio exige justificativa explícita.');
        }
    }

    /**
     * @return array<string, string>
     */
    public function resolveTemplateVariables(Contact $contact, Group $group): array
    {
        return [
            'nome' => trim($contact->name) ?: 'Olá',
            'categoria' => $group->category,
            'grupo' => $group->name,
            'link_grupo' => $group->invite_url,
        ];
    }

    /**
     * Non-throwing check for UI button state.
     *
     * @return array{eligible: bool, reason: string|null}
     */
    public function checkQuickEligibility(Contact $contact, ?Group $group, ?MessageTemplate $template, string $mode): array
    {
        try {
            $this->assertCanInvite($contact, $group, $template, $mode, isResend: false);

            return ['eligible' => true, 'reason' => null];
        } catch (DomainRuleException $e) {
            return ['eligible' => false, 'reason' => $e->getMessage()];
        }
    }
}
