<?php

namespace App\Livewire\Invitations;

use App\Exceptions\DomainRuleException;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Invitation;
use App\Models\MessageTemplate;
use App\Services\ContactActionService;
use App\Services\WhatsApp\WhatsAppConfigStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(as: 'tab')]
    public string $activeTab = 'ready';

    public ?int $selectedTemplateId = null;

    public string $statusFilter = '';

    public string $modeFilter = '';

    public string $sourceFilter = '';

    public ?int $resendInvitationId = null;

    public string $resendReason = '';

    public bool $showResendModal = false;

    public ?string $flashMessage = null;

    public ?string $flashError = null;

    public function mount(): void
    {
        if (! in_array($this->activeTab, ['ready', 'pending_confirm', 'history'], true)) {
            $this->activeTab = 'ready';
        }

        $defaultTemplate = MessageTemplate::query()->where('is_default', true)->first()
            ?? MessageTemplate::query()->first();
        $this->selectedTemplateId = $defaultTemplate?->id;
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['ready', 'pending_confirm', 'history'], true)) {
            $this->activeTab = $tab;
            $this->resetPage();
        }
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingModeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSourceFilter(): void
    {
        $this->resetPage();
    }

    public function updateContactGroup(int $contactId, $groupId): void
    {
        $contact = Contact::query()->findOrFail($contactId);
        $parsedId = is_numeric($groupId) ? (int) $groupId : null;
        $contact->update(['default_group_id' => $parsedId]);
        AuditLog::record('contact.group_changed', $contact, ['group_id' => $parsedId]);
    }

    public function inviteContact(int $contactId, ContactActionService $actionService): void
    {
        $this->flashMessage = null;
        $this->flashError = null;

        $contact = Contact::query()->with('defaultGroup')->findOrFail($contactId);

        try {
            $result = $actionService->triggerPrimaryInvite(
                $contact,
                auth()->user(),
                $contact->default_group_id,
                $this->selectedTemplateId
            );

            $this->flashMessage = $result['message'];

            if ($result['wa_me_url'] !== null) {
                $this->dispatch('open-wa-me', url: $result['wa_me_url']);
            }
        } catch (DomainRuleException $e) {
            $this->flashError = $e->getMessage();
        }
    }

    public function toggleQueuePause(): void
    {
        Gate::authorize('admin-only');

        $currentlyPaused = AppSetting::getValue('queue_paused', '0') === '1';
        $next = $currentlyPaused ? '0' : '1';
        AppSetting::setValue('queue_paused', $next);
        AuditLog::record($next === '1' ? 'queue.paused' : 'queue.resumed');

        $this->flashMessage = $next === '1' ? 'Fila de envios pausada.' : 'Fila de envios retomada.';
    }

    public function confirmManualSend(int $invitationId, ContactActionService $actionService): void
    {
        $this->flashMessage = null;
        $this->flashError = null;

        $invitation = Invitation::query()->with('contact')->findOrFail($invitationId);

        try {
            $actionService->confirmManualSend($invitation, auth()->user());
            $this->flashMessage = 'Envio manual confirmado.';
        } catch (DomainRuleException $e) {
            $this->flashError = $e->getMessage();
        }
    }

    public function openResendModal(int $invitationId): void
    {
        $this->resendInvitationId = $invitationId;
        $this->resendReason = '';
        $this->showResendModal = true;
    }

    public function confirmResend(ContactActionService $actionService): void
    {
        $this->validate([
            'resendReason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $original = Invitation::query()->with(['contact', 'group', 'template'])->findOrFail($this->resendInvitationId);

        try {
            $result = $actionService->triggerPrimaryInvite(
                $original->contact,
                auth()->user(),
                $original->group_id,
                $original->message_template_id,
                isResend: true,
                resendReason: trim($this->resendReason)
            );
            $this->showResendModal = false;
            $this->flashMessage = $result['message'];

            if ($result['wa_me_url'] !== null) {
                $this->dispatch('open-wa-me', url: $result['wa_me_url']);
            }
        } catch (DomainRuleException $e) {
            $this->flashError = $e->getMessage();
        }
    }

    public function retryFailedJob(string $uuid): void
    {
        Gate::authorize('admin-only');

        Artisan::call('queue:retry', ['id' => [$uuid]]);
        AuditLog::record('queue.job_retried', null, ['job_uuid' => $uuid]);
        $this->flashMessage = 'Job recolocado na fila.';
    }

    public function render(WhatsAppConfigStatus $configStatus)
    {
        $mode = $configStatus->currentMode();
        $primaryLabel = $mode === WhatsAppConfigStatus::MODE_ASSISTED ? 'Abrir WhatsApp' : 'Enviar convite';

        $readyBaseQuery = Contact::query()->readyToInvite();

        $readyCount = (clone $readyBaseQuery)->count();
        $readyContacts = (clone $readyBaseQuery)
            ->with('defaultGroup')
            ->orderByDesc('created_at')
            ->paginate(15, ['*'], 'readyPage');

        $pendingConfirmQuery = Invitation::query()
            ->with(['contact', 'group', 'template'])
            ->where('status', Invitation::STATUS_ASSISTED_OPENED)
            ->orderByDesc('created_at');

        $pendingConfirmCount = (clone $pendingConfirmQuery)->count();
        $pendingConfirmInvitations = $pendingConfirmQuery->paginate(15, ['*'], 'pendingPage');

        $invitationsQuery = Invitation::query()
            ->with(['contact', 'group', 'template', 'user'])
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->modeFilter !== '', fn ($q) => $q->where('mode', $this->modeFilter))
            ->when($this->sourceFilter !== '', fn ($q) => $q->where('record_source', $this->sourceFilter))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $historyCount = Invitation::query()->count();
        $invitations = $invitationsQuery->paginate(15, ['*'], 'historyPage');

        $queuePaused = AppSetting::getValue('queue_paused', '0') === '1';
        $pendingJobsCount = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->orderByDesc('failed_at')->limit(20)->get();
        $heartbeatRaw = AppSetting::getValue('worker_last_heartbeat_at');
        $heartbeatAt = is_string($heartbeatRaw) && $heartbeatRaw !== '' ? Carbon::parse($heartbeatRaw) : null;
        $workerAlert = $pendingJobsCount > 0 && ($heartbeatAt === null || $heartbeatAt->lt(now()->subMinutes(2)));

        return view('livewire.invitations.index', [
            'mode' => $mode,
            'primaryLabel' => $primaryLabel,
            'readyCount' => $readyCount,
            'readyContacts' => $readyContacts,
            'pendingConfirmCount' => $pendingConfirmCount,
            'pendingConfirmInvitations' => $pendingConfirmInvitations,
            'historyCount' => $historyCount,
            'invitations' => $invitations,
            'templates' => MessageTemplate::query()->orderByDesc('is_default')->orderBy('display_name')->get(),
            'activeGroups' => Group::query()->where('is_active', true)->whereNull('revoked_at')->orderBy('name')->get(),
            'queuePaused' => $queuePaused,
            'pendingJobsCount' => $pendingJobsCount,
            'failedJobs' => $failedJobs,
            'workerAlert' => $workerAlert,
            'heartbeatAt' => $heartbeatAt,
        ]);
    }
}
