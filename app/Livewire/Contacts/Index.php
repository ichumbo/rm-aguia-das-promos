<?php

namespace App\Livewire\Contacts;

use App\Exceptions\DomainRuleException;
use App\Exceptions\InvalidPhoneException;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\ConsentEvent;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Invitation;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\ContactActionService;
use App\Services\ContactImportService;
use App\Services\PhoneNormalizer;
use App\Services\WhatsApp\WhatsAppConfigStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $stageFilter = '';

    #[Url]
    public string $consentFilter = '';

    #[Url]
    public string $groupFilter = '';

    #[Url]
    public string $sourceFilter = '';

    #[Url(as: 'eligible')]
    public bool $onlyEligible = false;

    #[Url(as: 'fila')]
    public string $queueView = 'all';

    public bool $showAdvancedFilters = false;

    public ?int $selectedTemplateId = null;

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    // Modals & Drawer
    public bool $showCreateModal = false;

    public bool $showImportModal = false;

    public bool $showResendModal = false;

    public ?int $drawerContactId = null;

    // Manual create form
    public string $newName = '';

    public string $newCountryCode = '55';

    public string $newPhone = '';

    public string $newOrigin = 'cadastro_manual';

    public string $newInterests = '';

    public ?int $newGroupId = null;

    public bool $newHasConsentEvidence = false;

    public string $newConsentOrigin = 'atendimento_whatsapp';

    public string $newConsentEvidenceRef = '';

    // Import modal state
    public string $importStep = 'input'; // input | preview

    public string $importRawText = '';

    public $importCsvFile = null;

    public bool $importHasHeader = true;

    public string $importDefaultCountry = '55';

    public ?int $importDefaultGroupId = null;

    /** @var list<list<string>> */
    public array $importRows = [];

    /** @var array{name: int|null, phone: int|null, origin: int|null, interests: int|null} */
    public array $importMapping = [
        'name' => 0,
        'phone' => 1,
        'origin' => 2,
        'interests' => 3,
    ];

    /** @var array<string, mixed> */
    public array $importPreview = [];

    public bool $importHasConsentEvidence = false;

    public string $importConsentScope = 'convite_grupos_aguia';

    public string $importConsentEvidenceRef = '';

    // Drawer edits
    public string $drawerNotes = '';

    public ?int $drawerGroupId = null;

    public ?int $drawerOwnerId = null;

    public string $drawerManualConsentRef = '';

    // Resend modal
    public ?int $resendContactId = null;

    public string $resendReason = '';

    public ?string $flashMessage = null;

    public ?string $flashError = null;

    public function mount(): void
    {
        if (request('action') === 'new') {
            $this->openCreateModal();
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStageFilter(): void
    {
        $this->resetPage();
    }

    public function updatingConsentFilter(): void
    {
        $this->resetPage();
    }

    public function updatingGroupFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSourceFilter(): void
    {
        $this->resetPage();
    }

    public function updatingOnlyEligible(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $allowed = ['name', 'created_at', 'last_invited_at', 'pipeline_stage', 'consent_status'];
        if (! in_array($field, $allowed, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'stageFilter', 'consentFilter', 'groupFilter', 'sourceFilter', 'onlyEligible']);
        $this->queueView = 'all';
        $this->resetPage();
    }

    public function setQueueView(string $view): void
    {
        if (in_array($view, ['all', 'not_sent', 'ready', 'pending_confirm', 'sent', 'invited'], true)) {
            $this->queueView = $view;
            $this->resetPage();
        }
    }

    public function openCreateModal(): void
    {
        $this->reset([
            'newName', 'newPhone', 'newInterests', 'newGroupId',
            'newHasConsentEvidence', 'newConsentEvidenceRef',
        ]);
        $this->newCountryCode = '55';
        $this->newOrigin = 'cadastro_manual';
        $this->newConsentOrigin = 'atendimento_whatsapp';
        $this->showCreateModal = true;
    }

    public function createManualContact(PhoneNormalizer $phoneNormalizer): void
    {
        $this->flashMessage = null;
        $this->flashError = null;

        $rules = [
            'newName' => ['required', 'string', 'min:2', 'max:150'],
            'newCountryCode' => ['nullable', 'string', 'regex:/^\d{0,3}$/'],
            'newPhone' => ['required', 'string', 'min:8', 'max:30'],
            'newOrigin' => ['required', 'string', 'max:100'],
            'newInterests' => ['nullable', 'string', 'max:255'],
            'newGroupId' => ['nullable', 'exists:groups,id'],
            'newHasConsentEvidence' => ['boolean'],
        ];

        if ($this->newHasConsentEvidence) {
            $rules['newConsentEvidenceRef'] = ['required', 'string', 'min:3', 'max:255'];
            $rules['newConsentOrigin'] = ['required', 'string', 'max:100'];
        }

        $this->validate($rules, [
            'newConsentEvidenceRef.required' => 'Informe a referência da evidência da autorização.',
        ]);

        try {
            $phoneE164 = $phoneNormalizer->normalize(
                $this->newPhone,
                trim($this->newCountryCode) !== '' ? trim($this->newCountryCode) : null
            );
        } catch (InvalidPhoneException $e) {
            $this->addError('newPhone', $e->getMessage());

            return;
        }

        if (Contact::query()->where('phone_e164', $phoneE164)->exists()) {
            $this->addError('newPhone', 'Já existe um contato cadastrado com este telefone.');

            return;
        }

        $interests = array_values(array_filter(array_map('trim', explode(',', $this->newInterests))));

        DB::transaction(function () use ($phoneE164, $interests, $phoneNormalizer): void {
            $contact = Contact::create([
                'name' => trim($this->newName),
                'phone_e164' => $phoneE164,
                'origin' => trim($this->newOrigin),
                'interests' => $interests,
                'interests_text' => implode(', ', $interests),
                'owner_id' => auth()->id(),
                'default_group_id' => $this->newGroupId,
                'pipeline_stage' => $this->newHasConsentEvidence ? Contact::STAGE_AUTORIZADO : Contact::STAGE_NOVO,
                'consent_status' => $this->newHasConsentEvidence ? Contact::CONSENT_AUTORIZADO : Contact::CONSENT_PENDENTE,
                'record_source' => Contact::SOURCE_MANUAL,
            ]);

            if ($this->newHasConsentEvidence) {
                ConsentEvent::create([
                    'contact_id' => $contact->id,
                    'status' => ConsentEvent::STATUS_GRANTED,
                    'origin' => trim($this->newConsentOrigin),
                    'scope' => 'convite_grupos_aguia',
                    'policy_version' => (string) AppSetting::getValue('consent_policy_version', config('aguia.default_policy_version')),
                    'evidence_reference' => trim($this->newConsentEvidenceRef),
                    'recorded_by' => auth()->id(),
                    'occurred_at' => now(),
                ]);
            }

            AuditLog::record('contact.created_manual', $contact, [
                'phone_masked' => $phoneNormalizer->mask($phoneE164),
                'with_consent' => $this->newHasConsentEvidence,
            ]);
        });

        $this->showCreateModal = false;
        $this->flashMessage = 'Contato cadastrado manualmente com sucesso.';
    }

    public function openImportModal(): void
    {
        $this->reset([
            'importRawText', 'importCsvFile', 'importRows', 'importPreview',
            'importHasConsentEvidence', 'importConsentEvidenceRef', 'importDefaultGroupId',
        ]);
        $this->importStep = 'input';
        $this->importHasHeader = true;
        $this->importDefaultCountry = '55';
        $this->importMapping = ['name' => 0, 'phone' => 1, 'origin' => 2, 'interests' => 3];
        $this->importConsentScope = 'convite_grupos_aguia';
        $this->showImportModal = true;
    }

    public function previewImport(ContactImportService $importService): void
    {
        $parsed = $this->parseImportInput($importService);
        if ($parsed === null) {
            return;
        }

        $this->importRows = $parsed['rows'];
        $this->refreshImportPreview($importService);
        $this->importStep = 'preview';
    }

    public function applyOcrText(string $rawText, ContactImportService $importService): void
    {
        $country = trim($this->importDefaultCountry) !== '' ? trim($this->importDefaultCountry) : '55';
        $phones = $importService->extractPhoneLinesFromText($rawText, $country);

        if ($phones === []) {
            $this->addError('importRawText', 'Nenhum número de telefone foi reconhecido na imagem. Tente uma imagem mais nítida ou cole os números abaixo.');

            return;
        }

        $existing = array_filter(array_map('trim', explode("\n", $this->importRawText)));
        $merged = array_values(array_unique(array_merge($existing, $phones)));
        $this->importRawText = implode("\n", $merged);
        $this->resetErrorBag('importRawText');
    }

    public function authorizeContact(int $contactId, ContactActionService $actionService): void
    {
        $this->flashMessage = null;
        $this->flashError = null;
        $contact = Contact::query()->findOrFail($contactId);

        try {
            $actionService->authorizePendingContact($contact, auth()->user());
            $this->flashMessage = "Contato {$contact->name} autorizado e pronto para envio.";
        } catch (DomainRuleException $e) {
            $this->flashError = $e->getMessage();
        }
    }

    public function quickImportText(ContactImportService $importService): void
    {
        $parsed = $this->parseImportInput($importService);
        if ($parsed === null) {
            return;
        }

        $detected = $importService->detectQuickMapping($parsed['rows']);
        $evidence = $this->resolveImportConsentEvidence();
        $defaultCountry = trim($this->importDefaultCountry) !== '' ? trim($this->importDefaultCountry) : '55';
        $groupId = is_numeric($this->importDefaultGroupId) ? (int) $this->importDefaultGroupId : null;

        $stats = $importService->commit(
            $parsed['rows'],
            $detected['mapping'],
            $detected['has_header'],
            $defaultCountry,
            auth()->user(),
            $evidence,
            $groupId,
            true
        );

        $this->queueView = 'all';
        $this->showImportModal = false;
        $this->flashMessage = $this->formatImportSummary($stats);
    }

    public function refreshImportPreview(ContactImportService $importService): void
    {
        if ($this->importRows === []) {
            return;
        }

        $this->importPreview = $importService->buildPreview(
            $this->importRows,
            $this->importMapping,
            $this->importHasHeader,
            trim($this->importDefaultCountry) !== '' ? trim($this->importDefaultCountry) : null
        );
    }

    public function commitImport(ContactImportService $importService): void
    {
        $evidence = $this->resolveImportConsentEvidence();

        $stats = $importService->commit(
            $this->importRows,
            $this->importMapping,
            $this->importHasHeader,
            trim($this->importDefaultCountry) !== '' ? trim($this->importDefaultCountry) : null,
            auth()->user(),
            $evidence,
            is_numeric($this->importDefaultGroupId) ? (int) $this->importDefaultGroupId : null
        );

        $this->queueView = 'all';
        $this->showImportModal = false;
        $this->flashMessage = $this->formatImportSummary($stats);
    }

    /**
     * @return array{delimiter: string, rows: list<list<string>>}|null
     */
    private function parseImportInput(ContactImportService $importService): ?array
    {
        $content = trim($this->importRawText);

        if ($this->importCsvFile !== null) {
            $uploaded = file_get_contents($this->importCsvFile->getRealPath());
            if (is_string($uploaded) && trim($uploaded) !== '') {
                $content = $uploaded;
            }
        }

        if ($content === '') {
            $this->addError('importRawText', 'Cole os números/linhas ou selecione um arquivo CSV antes de continuar.');

            return null;
        }

        $parsed = $importService->parseLines($content);
        if ($parsed['rows'] === []) {
            $this->addError('importRawText', 'Nenhuma linha válida encontrada.');

            return null;
        }

        return $parsed;
    }

    /**
     * @return array{origin: string, scope: string, policy_version: string, evidence_reference: string}|null
     */
    private function resolveImportConsentEvidence(): ?array
    {
        if (! $this->importHasConsentEvidence) {
            return null;
        }

        $this->validate([
            'importConsentEvidenceRef' => ['required', 'string', 'min:3', 'max:255'],
            'importConsentScope' => ['required', 'string', 'max:120'],
        ], [
            'importConsentEvidenceRef.required' => 'Informe a referência da evidência de consentimento do lote.',
        ]);

        return [
            'origin' => 'importacao_com_evidencia',
            'scope' => trim($this->importConsentScope),
            'policy_version' => (string) AppSetting::getValue('consent_policy_version', config('aguia.default_policy_version')),
            'evidence_reference' => trim($this->importConsentEvidenceRef),
        ];
    }

    /**
     * @param array{created: int, updated_existing: int, skipped_suppressed: int, skipped_duplicate: int, rejected_invalid: int} $stats
     */
    private function formatImportSummary(array $stats): string
    {
        return sprintf(
            'Importação concluída: %d criado(s), %d atualizado(s), %d descadastrado(s) preservado(s), %d duplicado(s) no lote, %d inválido(s) rejeitado(s).',
            $stats['created'],
            $stats['updated_existing'],
            $stats['skipped_suppressed'],
            $stats['skipped_duplicate'],
            $stats['rejected_invalid']
        );
    }

    public function openDrawer(int $contactId): void
    {
        $contact = Contact::query()->findOrFail($contactId);
        $this->drawerContactId = $contact->id;
        $this->drawerNotes = (string) ($contact->notes ?? '');
        $this->drawerGroupId = $contact->default_group_id;
        $this->drawerOwnerId = $contact->owner_id;
        $this->drawerManualConsentRef = '';
    }

    public function closeDrawer(): void
    {
        $this->drawerContactId = null;
    }

    public function saveDrawerDetails(): void
    {
        if ($this->drawerContactId === null) {
            return;
        }

        $contact = Contact::query()->findOrFail($this->drawerContactId);
        $contact->update([
            'notes' => trim($this->drawerNotes) !== '' ? trim($this->drawerNotes) : null,
            'default_group_id' => $this->drawerGroupId ?: null,
            'owner_id' => $this->drawerOwnerId ?: null,
        ]);

        AuditLog::record('contact.details_updated', $contact, [
            'default_group_id' => $this->drawerGroupId,
            'owner_id' => $this->drawerOwnerId,
        ]);

        $this->flashMessage = 'Ficha do contato atualizada.';
    }

    public function recordConsentFromDrawer(): void
    {
        if ($this->drawerContactId === null) {
            return;
        }

        $this->validate([
            'drawerManualConsentRef' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'drawerManualConsentRef.required' => 'Informe a referência da prova de autorização (ex: protocolo/ticket/link do aceite).',
        ]);

        $contact = Contact::query()->findOrFail($this->drawerContactId);

        if ($contact->isSuppressed()) {
            $this->flashError = 'Este número está em descadastro prioritário. Só pode ser reativado por solicitação direta do titular na página de captação.';

            return;
        }

        DB::transaction(function () use ($contact): void {
            ConsentEvent::create([
                'contact_id' => $contact->id,
                'status' => ConsentEvent::STATUS_GRANTED,
                'origin' => 'atendimento_manual',
                'scope' => 'convite_grupos_aguia',
                'policy_version' => (string) AppSetting::getValue('consent_policy_version', config('aguia.default_policy_version')),
                'evidence_reference' => trim($this->drawerManualConsentRef),
                'recorded_by' => auth()->id(),
                'occurred_at' => now(),
            ]);

            $contact->update([
                'consent_status' => Contact::CONSENT_AUTORIZADO,
                'pipeline_stage' => $contact->pipeline_stage === Contact::STAGE_NOVO
                    ? Contact::STAGE_AUTORIZADO
                    : $contact->pipeline_stage,
            ]);

            AuditLog::record('contact.consent_granted_manual', $contact, [
                'evidence_reference' => trim($this->drawerManualConsentRef),
            ]);
        });

        $this->drawerManualConsentRef = '';
        $this->flashMessage = 'Autorização registrada com evidência.';
    }

    public function updateContactGroup(int $contactId, $groupId): void
    {
        $contact = Contact::query()->findOrFail($contactId);
        $parsedId = is_numeric($groupId) ? (int) $groupId : null;
        $contact->update(['default_group_id' => $parsedId]);
        AuditLog::record('contact.group_changed', $contact, ['group_id' => $parsedId]);
    }

    public function invite(int $contactId, ContactActionService $actionService): void
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

    public function openResendModal(int $contactId): void
    {
        $this->resendContactId = $contactId;
        $this->resendReason = '';
        $this->showResendModal = true;
    }

    public function confirmResend(ContactActionService $actionService): void
    {
        $this->validate([
            'resendReason' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'resendReason.required' => 'Informe o motivo explícito do reenvio.',
        ]);

        $contact = Contact::query()->with('defaultGroup')->findOrFail($this->resendContactId);

        try {
            $result = $actionService->triggerPrimaryInvite(
                $contact,
                auth()->user(),
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

    public function confirmManualSend(int $invitationId, ContactActionService $actionService): void
    {
        $this->flashMessage = null;
        $this->flashError = null;

        $invitation = Invitation::query()->with('contact')->findOrFail($invitationId);

        try {
            $actionService->confirmManualSend($invitation, auth()->user());
            $this->flashMessage = 'Envio manual confirmado e registrado.';
        } catch (DomainRuleException $e) {
            $this->flashError = $e->getMessage();
        }
    }

    public function markResponded(int $contactId, ContactActionService $actionService): void
    {
        $contact = Contact::query()->findOrFail($contactId);
        $actionService->markResponded($contact, auth()->user());
        $this->flashMessage = "Contato {$contact->name} marcado como Respondeu.";
    }

    public function confirmEntry(int $contactId, ContactActionService $actionService): void
    {
        $contact = Contact::query()->findOrFail($contactId);
        $actionService->confirmGroupEntry($contact, auth()->user());
        $this->flashMessage = "Entrada no grupo confirmada manualmente para {$contact->name}.";
    }

    public function doNotContact(int $contactId, ContactActionService $actionService): void
    {
        $contact = Contact::query()->findOrFail($contactId);
        $actionService->optOut($contact, auth()->user(), 'nao_contatar_operador');
        $this->flashMessage = "Descadastro aplicado para {$contact->name}. Envios pendentes cancelados.";
    }

    public function deleteContactPermanently(int $contactId): void
    {
        Gate::authorize('purge-contacts');

        $contact = Contact::query()->findOrFail($contactId);
        $name = $contact->name;
        AuditLog::record('contact.deleted_permanently', $contact, ['name' => $name]);
        $contact->delete();

        $this->drawerContactId = null;
        $this->flashMessage = "Registro de {$name} excluído permanentemente (bloqueio de descadastro preservado caso existente).";
    }

    public function render(WhatsAppConfigStatus $configStatus)
    {
        $mode = $configStatus->currentMode();
        $primaryLabel = $mode === WhatsAppConfigStatus::MODE_ASSISTED ? 'Abrir WhatsApp' : 'Enviar convite';

        $contacts = Contact::query()
            ->with([
                'defaultGroup',
                'owner',
                'suppressionEntry',
                'invitations' => fn ($q) => $q->latest()->limit(1),
            ])
            ->when(trim($this->search) !== '', function ($query): void {
                $term = '%' . trim($this->search) . '%';
                $digitsOnly = preg_replace('/\D+/', '', $this->search);
                $query->where(function ($sub) use ($term, $digitsOnly): void {
                    $sub->where('name', 'like', $term)
                        ->orWhere('phone_e164', 'like', $term)
                        ->orWhere('interests_text', 'like', $term);
                    if ($digitsOnly !== '') {
                        $sub->orWhere('phone_e164', 'like', '%' . $digitsOnly . '%');
                    }
                });
            })
            ->when($this->stageFilter !== '', fn ($q) => $q->where('pipeline_stage', $this->stageFilter))
            ->when($this->consentFilter !== '', fn ($q) => $q->where('consent_status', $this->consentFilter))
            ->when($this->groupFilter !== '', fn ($q) => $q->where('default_group_id', (int) $this->groupFilter))
            ->when($this->sourceFilter !== '', fn ($q) => $q->where('record_source', $this->sourceFilter))
            ->when($this->onlyEligible && $this->queueView !== 'ready', fn ($q) => $q->eligible())
            ->when($this->queueView === 'not_sent', fn ($q) => $q->notSentYet())
            ->when($this->queueView === 'ready', fn ($q) => $q->readyToInvite())
            ->when($this->queueView === 'pending_confirm', function ($q): void {
                $q->whereHas('invitations', fn ($inv) => $inv->where('status', Invitation::STATUS_ASSISTED_OPENED));
            })
            ->when(in_array($this->queueView, ['sent', 'invited'], true), fn ($q) => $q->alreadySent())
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(15);

        $drawerContact = null;
        $drawerAuditLogs = collect();
        if ($this->drawerContactId !== null) {
            $drawerContact = Contact::query()
                ->with(['defaultGroup', 'owner', 'consentEvents.recorder', 'invitations.group', 'messageEvents', 'suppressionEntry'])
                ->find($this->drawerContactId);

            if ($drawerContact !== null) {
                $drawerAuditLogs = AuditLog::query()
                    ->with('user')
                    ->where('auditable_type', $drawerContact->getMorphClass())
                    ->where('auditable_id', $drawerContact->id)
                    ->orderByDesc('created_at')
                    ->limit(25)
                    ->get();
            }
        }

        $notSentCount = Contact::query()->notSentYet()->count();
        $readyCount = Contact::query()->readyToInvite()->count();
        $pendingConfirmCount = Contact::query()
            ->whereHas('invitations', fn ($inv) => $inv->where('status', Invitation::STATUS_ASSISTED_OPENED))
            ->count();
        $sentCount = Contact::query()->alreadySent()->count();

        return view('livewire.contacts.index', [
            'contacts' => $contacts,
            'groups' => Group::query()->orderBy('name')->get(),
            'activeGroups' => Group::query()->where('is_active', true)->whereNull('revoked_at')->orderBy('name')->get(),
            'templates' => MessageTemplate::query()->orderByDesc('is_default')->orderBy('display_name')->get(),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(),
            'stages' => config('aguia.pipeline_stages'),
            'mode' => $mode,
            'isApiConfigured' => $configStatus->isConfigured(),
            'primaryLabel' => $primaryLabel,
            'defaultTemplate' => MessageTemplate::query()->where('is_default', true)->first() ?? MessageTemplate::query()->first(),
            'queueCounts' => [
                'not_sent' => $notSentCount,
                'ready' => $readyCount,
                'pending_confirm' => $pendingConfirmCount,
                'sent' => $sentCount,
            ],
            'drawerContact' => $drawerContact,
            'drawerAuditLogs' => $drawerAuditLogs,
        ]);
    }
}

