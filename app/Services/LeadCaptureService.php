<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\ConsentEvent;
use App\Models\Contact;
use App\Models\Group;
use App\Models\SuppressionEntry;
use Illuminate\Support\Facades\DB;

class LeadCaptureService
{
    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {}

    /**
     * Capture a lead with explicit consent from public form or external webhook.
     *
     * @param  array<int, string>  $interests
     * @return array{
     *     contact: Contact,
     *     created: bool,
     *     matched_group: Group|null,
     *     group_invite_url: string|null,
     *     support_whatsapp_url: string|null
     * }
     */
    public function capture(
        string $name,
        string $rawPhone,
        ?string $countryCode,
        array $interests,
        string $origin,
        string $evidenceReference,
        bool $allowReoptIn,
        string $auditAction = 'contact.captured_public'
    ): array {
        $phoneE164 = $this->phoneNormalizer->normalize($rawPhone, $countryCode);
        $cleanName = trim($name);
        $cleanInterests = array_values(array_unique(array_filter(array_map('trim', $interests))));
        $cleanOrigin = trim($origin) !== '' ? trim($origin) : 'captacao_publica';
        $policyVersion = (string) AppSetting::getValue('consent_policy_version', config('aguia.default_policy_version'));

        $matchedGroup = $this->findMatchedGroup($cleanInterests);

        $result = DB::transaction(function () use (
            $cleanName,
            $phoneE164,
            $cleanInterests,
            $cleanOrigin,
            $evidenceReference,
            $policyVersion,
            $matchedGroup,
            $allowReoptIn,
            $auditAction
        ): array {
            $suppressionQuery = SuppressionEntry::query()->where('phone_e164', $phoneE164);

            if (! $allowReoptIn && $suppressionQuery->exists()) {
                throw new DomainRuleException('Contato possui descadastro/bloqueio ativo e não pode ser reativado via webhook.');
            }

            // Self-service explicit re-opt-in removes prior suppression entry only when submitted directly by the user
            $hadSuppression = $allowReoptIn && $suppressionQuery->delete() > 0;

            $contact = Contact::query()->where('phone_e164', $phoneE164)->first();
            $created = $contact === null;

            if ($contact === null) {
                $contact = Contact::create([
                    'name' => $cleanName,
                    'phone_e164' => $phoneE164,
                    'origin' => $cleanOrigin,
                    'interests' => $cleanInterests,
                    'interests_text' => implode(', ', $cleanInterests),
                    'default_group_id' => $matchedGroup?->id,
                    'pipeline_stage' => Contact::STAGE_AUTORIZADO,
                    'consent_status' => Contact::CONSENT_AUTORIZADO,
                    'record_source' => Contact::SOURCE_REAL,
                ]);
            } else {
                $mergedInterests = array_values(array_unique(array_merge($contact->interests ?? [], $cleanInterests)));
                $contact->update([
                    'name' => $cleanName,
                    'interests' => $mergedInterests,
                    'interests_text' => implode(', ', $mergedInterests),
                    'default_group_id' => $contact->default_group_id ?? $matchedGroup?->id,
                    'consent_status' => Contact::CONSENT_AUTORIZADO,
                    'pipeline_stage' => in_array($contact->pipeline_stage, [Contact::STAGE_NOVO, Contact::STAGE_NAO_INTERESSADO], true)
                        ? Contact::STAGE_AUTORIZADO
                        : $contact->pipeline_stage,
                ]);
            }

            ConsentEvent::create([
                'contact_id' => $contact->id,
                'status' => ConsentEvent::STATUS_GRANTED,
                'origin' => $cleanOrigin,
                'scope' => 'convite_grupos_aguia',
                'policy_version' => $policyVersion,
                'evidence_reference' => $evidenceReference,
                'occurred_at' => now(),
            ]);

            AuditLog::record($hadSuppression ? 'contact.reopted_in_public' : $auditAction, $contact, [
                'phone_masked' => $this->phoneNormalizer->mask($phoneE164),
                'origin' => $cleanOrigin,
                'policy_version' => $policyVersion,
                'interests' => $cleanInterests,
            ]);

            return [
                'contact' => $contact,
                'created' => $created,
            ];
        });

        $contact = $result['contact'];
        $effectiveGroup = $matchedGroup ?? $contact->defaultGroup;
        $groupInviteUrl = ($effectiveGroup !== null && $effectiveGroup->hasValidInviteUrl())
            ? $effectiveGroup->invite_url
            : null;

        return [
            'contact' => $contact,
            'created' => $result['created'],
            'matched_group' => $effectiveGroup,
            'group_invite_url' => $groupInviteUrl,
            'support_whatsapp_url' => $this->buildSupportWhatsAppUrl($contact, $cleanInterests),
        ];
    }

    /**
     * @param  array<int, string>  $interests
     */
    private function findMatchedGroup(array $interests): ?Group
    {
        if ($interests === []) {
            return null;
        }

        $candidates = Group::query()
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->whereIn('category', $interests)
            ->get();

        return $candidates->first(static fn (Group $group): bool => $group->hasValidInviteUrl())
            ?? $candidates->first();
    }

    /**
     * @param  array<int, string>  $interests
     */
    private function buildSupportWhatsAppUrl(Contact $contact, array $interests): ?string
    {
        $supportPhone = trim((string) AppSetting::getValue('capture_support_whatsapp', ''));
        if ($supportPhone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $supportPhone) ?? '';
        if ($digits === '') {
            return null;
        }

        $interestPart = $interests !== []
            ? ' (interesse em ' . implode(', ', $interests) . ')'
            : '';

        $text = sprintf(
            'Olá! Me chamo %s, acabei de me inscrever no Águia das Promos%s e gostaria do link do grupo.',
            $contact->name,
            $interestPart
        );

        return 'https://wa.me/' . $digits . '?text=' . rawurlencode($text);
    }
}
