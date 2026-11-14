<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\ConsentEvent;
use App\Models\Contact;
use App\Models\Invitation;
use App\Models\MessageEvent;
use App\Models\SuppressionEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SuppressionService
{
    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer
    ) {}

    public function optOut(
        Contact $contact,
        string $reason,
        string $source,
        ?string $evidenceReference = null,
        ?User $actor = null
    ): SuppressionEntry {
        $retentionDays = max(30, (int) AppSetting::getValue('retention_days_suppression', 1825));
        $policyVersion = (string) AppSetting::getValue('consent_policy_version', config('aguia.default_policy_version'));

        return DB::transaction(function () use ($contact, $reason, $source, $evidenceReference, $actor, $retentionDays, $policyVersion): SuppressionEntry {
            $entry = SuppressionEntry::query()->updateOrCreate(
                ['phone_e164' => $contact->phone_e164],
                [
                    'contact_id' => $contact->id,
                    'reason' => $reason,
                    'source' => $source,
                    'retain_until' => now()->addDays($retentionDays),
                    'created_by' => $actor?->id,
                ]
            );

            ConsentEvent::create([
                'contact_id' => $contact->id,
                'status' => ConsentEvent::STATUS_REVOKED,
                'origin' => $source === 'webhook' ? 'webhook_sair' : 'operador_nao_contatar',
                'scope' => 'convite_grupos_aguia',
                'policy_version' => $policyVersion,
                'evidence_reference' => $evidenceReference,
                'recorded_by' => $actor?->id,
                'occurred_at' => now(),
            ]);

            $contact->update([
                'consent_status' => Contact::CONSENT_REVOGADO,
                'pipeline_stage' => Contact::STAGE_NAO_INTERESSADO,
            ]);

            // Cancel any queued or processing invitations immediately
            $pendingInvitations = Invitation::query()
                ->where('contact_id', $contact->id)
                ->whereIn('status', [Invitation::STATUS_QUEUED, Invitation::STATUS_PROCESSING])
                ->get();

            foreach ($pendingInvitations as $pending) {
                $pending->update([
                    'status' => Invitation::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'error_code' => 'CONTACT_OPTED_OUT',
                    'error_message' => 'Cancelado automaticamente por descadastro do contato.',
                    'active_dedup_key' => null,
                ]);

                MessageEvent::create([
                    'invitation_id' => $pending->id,
                    'contact_id' => $contact->id,
                    'provider_event_id' => 'optout.cancel.' . $pending->id . '.' . Str::uuid(),
                    'event_type' => 'cancelled_opt_out',
                    'event_timestamp' => now(),
                    'payload_summary' => ['reason' => $reason, 'source' => $source],
                    'source' => $source,
                ]);
            }

            AuditLog::record('contact.opt_out', $contact, [
                'phone_masked' => $this->phoneNormalizer->mask($contact->phone_e164),
                'reason' => $reason,
                'source' => $source,
                'cancelled_invitations' => $pendingInvitations->count(),
            ], $actor?->id);

            return $entry;
        });
    }
}
