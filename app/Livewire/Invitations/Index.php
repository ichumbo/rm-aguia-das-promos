<?php

namespace App\Livewire\Invitations;

use App\Exceptions\DomainRuleException;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Services\ContactActionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $statusFilter = '';

    public string $modeFilter = '';

    public string $sourceFilter = '';

    public ?int $resendInvitationId = null;

    public string $resendReason = '';

    public bool $showResendModal = false;

    public ?string $flashMessage = null;

    public ?string $flashError = null;

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

    public function render()
    {
        $invitations = Invitation::query()
            ->with(['contact', 'group', 'template', 'user'])
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->modeFilter !== '', fn ($q) => $q->where('mode', $this->modeFilter))
            ->when($this->sourceFilter !== '', fn ($q) => $q->where('record_source', $this->sourceFilter))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);

        $queuePaused = AppSetting::getValue('queue_paused', '0') === '1';
        $pendingJobsCount = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->orderByDesc('failed_at')->limit(20)->get();
        $heartbeatRaw = AppSetting::getValue('worker_last_heartbeat_at');
        $heartbeatAt = is_string($heartbeatRaw) && $heartbeatRaw !== '' ? Carbon::parse($heartbeatRaw) : null;
        $workerAlert = $pendingJobsCount > 0 && ($heartbeatAt === null || $heartbeatAt->lt(now()->subMinutes(2)));

        return view('livewire.invitations.index', [
            'invitations' => $invitations,
            'queuePaused' => $queuePaused,
            'pendingJobsCount' => $pendingJobsCount,
            'failedJobs' => $failedJobs,
            'workerAlert' => $workerAlert,
            'heartbeatAt' => $heartbeatAt,
        ]);
    }
}
