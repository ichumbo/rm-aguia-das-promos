<?php

namespace App\Livewire\Settings;

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\SuppressionEntry;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppConfigStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CrmSettings extends Component
{
    public string $whatsappMode = WhatsAppConfigStatus::MODE_ASSISTED;

    public int $retentionDaysContacts = 365;

    public int $retentionDaysSuppression = 1825;

    public string $palettePrimary = '#F9C80E';

    public string $paletteDark = '#171717';

    public string $paletteSurface = '#FFFFFF';

    public string $paletteBg = '#F5F6F8';

    // New user form
    public string $newUserName = '';

    public string $newUserEmail = '';

    public string $newUserPassword = '';

    public string $newUserRole = User::ROLE_OPERATOR;

    public ?string $flashMessage = null;

    public ?string $flashError = null;

    public function mount(WhatsAppConfigStatus $configStatus): void
    {
        Gate::authorize('admin-only');

        $this->whatsappMode = $configStatus->currentMode();
        $this->retentionDaysContacts = (int) AppSetting::getValue('retention_days_contacts', 365);
        $this->retentionDaysSuppression = (int) AppSetting::getValue('retention_days_suppression', 1825);
        $this->palettePrimary = (string) AppSetting::getValue('palette_primary', '#F9C80E');
        $this->paletteDark = (string) AppSetting::getValue('palette_dark', '#171717');
        $this->paletteSurface = (string) AppSetting::getValue('palette_surface', '#FFFFFF');
        $this->paletteBg = (string) AppSetting::getValue('palette_bg', '#F5F6F8');
    }

    public function saveOperationSettings(): void
    {
        Gate::authorize('manage-integrations');

        $this->validate([
            'whatsappMode' => ['required', Rule::in([
                WhatsAppConfigStatus::MODE_ASSISTED,
                WhatsAppConfigStatus::MODE_OFFICIAL_API,
                WhatsAppConfigStatus::MODE_SIMULATED,
            ])],
            'retentionDaysContacts' => ['required', 'integer', 'min:30', 'max:3650'],
            'retentionDaysSuppression' => ['required', 'integer', 'min:30', 'max:3650'],
        ]);

        AppSetting::setValue('whatsapp_mode', $this->whatsappMode);
        AppSetting::setValue('retention_days_contacts', $this->retentionDaysContacts);
        AppSetting::setValue('retention_days_suppression', $this->retentionDaysSuppression);

        AuditLog::record('settings.operation_updated', null, [
            'whatsapp_mode' => $this->whatsappMode,
            'retention_days_contacts' => $this->retentionDaysContacts,
            'retention_days_suppression' => $this->retentionDaysSuppression,
        ]);

        $this->flashMessage = 'Configurações de modo e retenção salvas.';
    }

    public function savePalette(): void
    {
        Gate::authorize('admin-only');

        $this->validate([
            'palettePrimary' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'paletteDark' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'paletteSurface' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'paletteBg' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        AppSetting::setValue('palette_primary', $this->palettePrimary);
        AppSetting::setValue('palette_dark', $this->paletteDark);
        AppSetting::setValue('palette_surface', $this->paletteSurface);
        AppSetting::setValue('palette_bg', $this->paletteBg);

        AuditLog::record('settings.palette_updated');
        $this->flashMessage = 'Paleta visual atualizada.';
    }

    public function resetPalette(): void
    {
        Gate::authorize('admin-only');

        $this->palettePrimary = '#F9C80E';
        $this->paletteDark = '#171717';
        $this->paletteSurface = '#FFFFFF';
        $this->paletteBg = '#F5F6F8';
        $this->savePalette();
    }

    public function runRetentionCleanup(): void
    {
        Gate::authorize('purge-contacts');

        $contactCutoff = now()->subDays(max(30, $this->retentionDaysContacts));

        $purgedContacts = 0;
        $purgedSuppression = 0;

        DB::transaction(function () use ($contactCutoff, &$purgedContacts, &$purgedSuppression): void {
            // Purge inactive unengaged contacts older than retention window while keeping active suppression phone hashes/entries
            $purgedContacts = Contact::query()
                ->where('updated_at', '<', $contactCutoff)
                ->where('consent_status', '!=', Contact::CONSENT_AUTORIZADO)
                ->delete();

            $purgedSuppression = SuppressionEntry::query()
                ->whereNotNull('retain_until')
                ->where('retain_until', '<', now())
                ->delete();

            AuditLog::record('retention.purged', null, [
                'purged_contacts' => $purgedContacts,
                'purged_suppression' => $purgedSuppression,
            ]);
        });

        $this->flashMessage = "Política de retenção executada: {$purgedContacts} contato(s) expirado(s) e {$purgedSuppression} bloqueio(s) vencido(s) removidos.";
    }

    public function createUser(): void
    {
        Gate::authorize('manage-users');

        $this->validate([
            'newUserName' => ['required', 'string', 'min:2', 'max:150'],
            'newUserEmail' => ['required', 'email', 'unique:users,email'],
            'newUserPassword' => ['required', 'string', 'min:8'],
            'newUserRole' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_OPERATOR])],
        ]);

        $user = User::create([
            'name' => trim($this->newUserName),
            'email' => mb_strtolower(trim($this->newUserEmail)),
            'password' => Hash::make($this->newUserPassword),
            'role' => $this->newUserRole,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        AuditLog::record('user.created', $user, ['role' => $user->role]);
        $this->reset(['newUserName', 'newUserEmail', 'newUserPassword']);
        $this->newUserRole = User::ROLE_OPERATOR;
        $this->flashMessage = 'Usuário criado com sucesso.';
    }

    public function toggleUserRole(int $userId): void
    {
        Gate::authorize('manage-users');

        if ($userId === auth()->id()) {
            $this->flashError = 'Você não pode rebaixar seu próprio usuário logado.';

            return;
        }

        $user = User::query()->findOrFail($userId);
        $nextRole = $user->isAdmin() ? User::ROLE_OPERATOR : User::ROLE_ADMIN;
        $user->update(['role' => $nextRole]);
        AuditLog::record('user.role_changed', $user, ['role' => $nextRole]);
        $this->flashMessage = "Perfil de {$user->name} alterado para {$nextRole}.";
    }

    public function toggleUserActive(int $userId): void
    {
        Gate::authorize('manage-users');

        if ($userId === auth()->id()) {
            $this->flashError = 'Você não pode desativar seu próprio usuário logado.';

            return;
        }

        $user = User::query()->findOrFail($userId);
        $user->update(['is_active' => ! $user->is_active]);
        AuditLog::record('user.toggled_active', $user, ['is_active' => $user->is_active]);
        $this->flashMessage = 'Status do usuário atualizado.';
    }

    public function render(WhatsAppConfigStatus $configStatus)
    {
        Gate::authorize('admin-only');

        return view('livewire.settings.crm-settings', [
            'isApiConfigured' => $configStatus->isConfigured(),
            'checklist' => $configStatus->checklist(),
            'webhookUrl' => route('webhooks.whatsapp.handle'),
            'users' => User::query()->orderBy('name')->get(),
            'auditLogs' => AuditLog::query()->with('user')->orderByDesc('created_at')->limit(30)->get(),
        ]);
    }
}
