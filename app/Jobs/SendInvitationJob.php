<?php

namespace App\Jobs;

use App\Exceptions\DomainRuleException;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Invitation;
use App\Models\MessageEvent;
use App\Services\EligibilityService;
use App\Services\WhatsApp\WhatsAppTransportResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class SendInvitationJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [15, 60, 180];

    public function __construct(
        public readonly int $invitationId
    ) {}

    public function handle(
        EligibilityService $eligibilityService,
        WhatsAppTransportResolver $transportResolver
    ): void {
        AppSetting::setValue('worker_last_heartbeat_at', now()->toIso8601String());

        if (AppSetting::getValue('queue_paused', '0') === '1') {
            $this->release(30);

            return;
        }

        /** @var Invitation|null $invitation */
        $invitation = Invitation::query()
            ->with(['contact', 'group', 'template'])
            ->find($this->invitationId);

        if ($invitation === null) {
            return;
        }

        // Idempotency guard: never re-send already processed, uncertain, sent, or cancelled invitations
        if ($invitation->status !== Invitation::STATUS_QUEUED) {
            return;
        }

        // Re-validate eligibility inside worker right before sending
        try {
            $eligibilityService->assertCanInvite(
                $invitation->contact,
                $invitation->group,
                $invitation->template,
                $invitation->mode,
                isResend: true,
                resendReason: 'worker_precheck',
                ignoreInvitationId: $invitation->id
            );
        } catch (DomainRuleException $e) {
            $invitation->update([
                'status' => Invitation::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'error_code' => 'ELIGIBILITY_REVOKED',
                'error_message' => $e->getMessage(),
                'active_dedup_key' => null,
            ]);

            MessageEvent::create([
                'invitation_id' => $invitation->id,
                'contact_id' => $invitation->contact_id,
                'provider_event_id' => 'worker.cancelled.' . $invitation->id . '.' . Str::uuid(),
                'event_type' => 'cancelled_eligibility',
                'event_timestamp' => now(),
                'payload_summary' => ['reason' => $e->getMessage()],
                'source' => 'worker',
            ]);

            return;
        }

        $invitation->update(['status' => Invitation::STATUS_PROCESSING]);

        $transport = $transportResolver->forMode($invitation->mode);
        $result = $transport->sendTemplateInvitation($invitation);

        if ($result->accepted) {
            $invitation->update([
                'status' => Invitation::STATUS_SENT,
                'provider_message_id' => $result->providerMessageId,
                'status_origin' => $result->isSimulated ? 'simulated' : 'system',
                'sent_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ]);

            $contact = $invitation->contact;
            $updates = ['last_invited_at' => now()];
            if (in_array($contact->pipeline_stage, [Contact::STAGE_NOVO, Contact::STAGE_AUTORIZADO], true)) {
                $updates['pipeline_stage'] = Contact::STAGE_CONVITE_ENVIADO;
            }
            $contact->update($updates);

            MessageEvent::create([
                'invitation_id' => $invitation->id,
                'contact_id' => $invitation->contact_id,
                'provider_event_id' => ($result->isSimulated ? 'sim.accepted.' : 'api.accepted.') . $invitation->id . '.' . ($result->providerMessageId ?? Str::uuid()),
                'event_type' => $result->isSimulated ? 'simulated_sent' : 'api_accepted',
                'event_timestamp' => now(),
                'payload_summary' => [
                    'provider_message_id' => $result->providerMessageId,
                    'simulated' => $result->isSimulated,
                ],
                'source' => $result->isSimulated ? 'simulated' : 'worker',
            ]);

            return;
        }

        if ($result->uncertain) {
            $invitation->update([
                'status' => Invitation::STATUS_UNCERTAIN,
                'uncertain_at' => now(),
                'error_code' => $result->errorCode,
                'error_message' => $result->errorMessage,
            ]);

            MessageEvent::create([
                'invitation_id' => $invitation->id,
                'contact_id' => $invitation->contact_id,
                'provider_event_id' => 'worker.uncertain.' . $invitation->id . '.' . Str::uuid(),
                'event_type' => 'timeout_uncertain',
                'event_timestamp' => now(),
                'payload_summary' => ['error_code' => $result->errorCode],
                'source' => 'worker',
            ]);

            AuditLog::record('invitation.timeout_uncertain', $invitation, [
                'error_code' => $result->errorCode,
            ]);

            return;
        }

        if (($result->rateLimited || $result->transientFailure) && $this->attempts() < $this->tries) {
            $delay = $result->rateLimited
                ? ($result->retryAfterSeconds ?? 60)
                : ($this->backoff[$this->attempts() - 1] ?? 60);

            $invitation->update([
                'status' => Invitation::STATUS_QUEUED,
                'error_code' => $result->errorCode,
                'error_message' => $result->errorMessage,
            ]);

            $this->release($delay);

            return;
        }

        $invitation->update([
            'status' => Invitation::STATUS_FAILED,
            'failed_at' => now(),
            'error_code' => $result->errorCode,
            'error_message' => $result->errorMessage,
            'active_dedup_key' => null,
        ]);

        MessageEvent::create([
            'invitation_id' => $invitation->id,
            'contact_id' => $invitation->contact_id,
            'provider_event_id' => 'worker.failed.' . $invitation->id . '.' . Str::uuid(),
            'event_type' => 'send_failed',
            'event_timestamp' => now(),
            'payload_summary' => [
                'error_code' => $result->errorCode,
                'error_message' => $result->errorMessage,
            ],
            'source' => 'worker',
        ]);
    }
}
