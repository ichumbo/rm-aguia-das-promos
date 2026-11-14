<?php

namespace App\Livewire;

use App\Exceptions\DomainRuleException;
use App\Models\AppSetting;
use App\Models\Contact;
use App\Models\Invitation;
use App\Services\ContactActionService;
use App\Services\WhatsApp\WhatsAppConfigStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Dashboard extends Component
{
    public ?string $flashMessage = null;

    public ?string $flashError = null;

    public function inviteNext(int $contactId, ContactActionService $actionService): void
    {
        $this->flashMessage = null;
        $this->flashError = null;

        $contact = Contact::query()->with('defaultGroup')->find($contactId);
        if ($contact === null) {
            $this->flashError = 'Contato não encontrado.';

            return;
        }

        try {
            $result = $actionService->triggerPrimaryInvite($contact, auth()->user());
            $this->flashMessage = $result['message'];

            if ($result['wa_me_url'] !== null) {
                $this->dispatch('open-wa-me', url: $result['wa_me_url']);
            }
        } catch (DomainRuleException $e) {
            $this->flashError = $e->getMessage();
        }
    }

    public function render(WhatsAppConfigStatus $configStatus)
    {
        $mode = $configStatus->currentMode();
        $isApiConfigured = $configStatus->isConfigured();
        $missingCredentials = $configStatus->missingCredentials();
        $queuePaused = AppSetting::getValue('queue_paused', '0') === '1';

        $pendingJobsCount = DB::table('jobs')->count();
        $failedJobsCount = DB::table('failed_jobs')->count();
        $heartbeatRaw = AppSetting::getValue('worker_last_heartbeat_at');
        $heartbeatAt = is_string($heartbeatRaw) && $heartbeatRaw !== '' ? Carbon::parse($heartbeatRaw) : null;
        $workerAlert = $pendingJobsCount > 0 && ($heartbeatAt === null || $heartbeatAt->lt(now()->subMinutes(2)));

        // 1. Real production metrics (never mixed with manual or demo/simulated)
        $realMetrics = [
            'authorized_contacts' => Contact::query()
                ->where('record_source', Contact::SOURCE_REAL)
                ->where('consent_status', Contact::CONSENT_AUTORIZADO)
                ->count(),
            'api_sent' => Invitation::query()
                ->where('record_source', Contact::SOURCE_REAL)
                ->where('mode', Invitation::MODE_OFFICIAL_API)
                ->whereIn('status', [Invitation::STATUS_SENT, Invitation::STATUS_DELIVERED, Invitation::STATUS_READ])
                ->count(),
            'delivered' => Invitation::query()
                ->where('record_source', Contact::SOURCE_REAL)
                ->where('mode', Invitation::MODE_OFFICIAL_API)
                ->whereIn('status', [Invitation::STATUS_DELIVERED, Invitation::STATUS_READ])
                ->count(),
            'responses' => Contact::query()
                ->where('record_source', Contact::SOURCE_REAL)
                ->where('pipeline_stage', Contact::STAGE_RESPONDEU)
                ->count(),
            'manual_entries_confirmed' => Contact::query()
                ->where('record_source', Contact::SOURCE_REAL)
                ->whereNotNull('joined_confirmed_at')
                ->count(),
        ];

        // 2. Manual / Assisted metrics
        $manualMetrics = [
            'manual_contacts' => Contact::query()
                ->where('record_source', Contact::SOURCE_MANUAL)
                ->count(),
            'assisted_opened' => Invitation::query()
                ->where('mode', Invitation::MODE_ASSISTED)
                ->where('record_source', '!=', Contact::SOURCE_DEMO)
                ->where('status', Invitation::STATUS_ASSISTED_OPENED)
                ->count(),
            'assisted_confirmed' => Invitation::query()
                ->where('mode', Invitation::MODE_ASSISTED)
                ->where('record_source', '!=', Contact::SOURCE_DEMO)
                ->where('status', Invitation::STATUS_ASSISTED_SENT_MANUAL)
                ->count(),
            'manual_entries_confirmed' => Contact::query()
                ->where('record_source', Contact::SOURCE_MANUAL)
                ->whereNotNull('joined_confirmed_at')
                ->count(),
        ];

        // 3. Demo & Simulated metrics (strictly separated)
        $demoMetrics = [
            'demo_contacts' => Contact::query()
                ->where('record_source', Contact::SOURCE_DEMO)
                ->count(),
            'simulated_invites' => Invitation::query()
                ->where(function ($q): void {
                    $q->where('mode', Invitation::MODE_SIMULATED)
                        ->orWhere('record_source', Contact::SOURCE_DEMO);
                })
                ->count(),
        ];

        $eligibleQuery = Contact::query()
            ->with(['defaultGroup', 'invitations' => fn ($q) => $q->latest()->limit(1)])
            ->readyToInvite();

        $readyToInviteCount = (clone $eligibleQuery)->count();
        $pendingConfirmCount = Invitation::query()
            ->where('status', Invitation::STATUS_ASSISTED_OPENED)
            ->count();

        $eligibleQueue = $eligibleQuery
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        $primaryButtonLabel = $mode === WhatsAppConfigStatus::MODE_ASSISTED ? 'Abrir WhatsApp' : 'Enviar convite';

        return view('livewire.dashboard', [
            'mode' => $mode,
            'isApiConfigured' => $isApiConfigured,
            'missingCredentials' => $missingCredentials,
            'queuePaused' => $queuePaused,
            'pendingJobsCount' => $pendingJobsCount,
            'failedJobsCount' => $failedJobsCount,
            'workerAlert' => $workerAlert,
            'heartbeatAt' => $heartbeatAt,
            'realMetrics' => $realMetrics,
            'manualMetrics' => $manualMetrics,
            'demoMetrics' => $demoMetrics,
            'readyToInviteCount' => $readyToInviteCount,
            'pendingConfirmCount' => $pendingConfirmCount,
            'eligibleQueue' => $eligibleQueue,
            'primaryButtonLabel' => $primaryButtonLabel,
        ]);
    }
}

