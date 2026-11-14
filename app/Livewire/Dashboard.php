<?php

namespace App\Livewire;

use App\Exceptions\DomainRuleException;
use App\Models\AppSetting;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Invitation;
use App\Models\MessageTemplate;
use App\Services\ContactActionService;
use App\Services\ContactImportService;
use App\Services\WhatsApp\WhatsAppConfigStatus;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class Dashboard extends Component
{
    public string $quickText = '';

    public string $quickDefaultCountry = '55';

    public string|int|null $quickGroupId = '';

    public bool $quickAuthorizeContacts = false;

    public string $quickConsentRef = 'Lista autorizada pelo operador';

    public ?int $selectedTemplateId = null;

    public string $queueTab = 'pending';

    public ?string $flashMessage = null;

    public ?string $flashError = null;

    public function applyOcrText(string $rawText, ContactImportService $importService): void
    {
        $this->flashMessage = null;
        $this->flashError = null;

        $country = trim($this->quickDefaultCountry) !== '' ? trim($this->quickDefaultCountry) : '55';
        $phones = $importService->extractPhoneLinesFromText($rawText, $country);

        if ($phones === []) {
            $this->flashError = 'Nenhum telefone válido foi identificado na imagem. Tente uma imagem mais nítida ou cole os números abaixo.';

            return;
        }

        $existing = array_filter(array_map('trim', explode("\n", $this->quickText)));
        $merged = array_values(array_unique(array_merge($existing, $phones)));
        $this->quickText = implode("\n", $merged);
        $this->resetErrorBag('quickText');
        $this->flashMessage = count($phones) . ' número(s) lido(s) da imagem. Confira abaixo e clique em "Salvar na lista".';
    }

    public function saveQuickContacts(ContactImportService $importService): void
    {
        $this->flashMessage = null;
        $this->flashError = null;

        if (trim($this->quickText) === '') {
            $this->addError('quickText', 'Suba uma imagem ou cole pelo menos um telefone antes de salvar.');

            return;
        }

        $parsed = $importService->parseLines($this->quickText);
        if ($parsed['rows'] === []) {
            $this->addError('quickText', 'Nenhuma linha válida encontrada.');

            return;
        }

        $detected = $importService->detectQuickMapping($parsed['rows']);
        $country = trim($this->quickDefaultCountry) !== '' ? trim($this->quickDefaultCountry) : '55';
        $groupId = is_numeric($this->quickGroupId) ? (int) $this->quickGroupId : null;

        $evidence = $this->quickAuthorizeContacts ? [
            'origin' => 'importacao_autorizada_dashboard',
            'scope' => 'convite_grupos_aguia',
            'policy_version' => (string) AppSetting::getValue('consent_policy_version', config('aguia.default_policy_version')),
            'evidence_reference' => trim($this->quickConsentRef) !== '' ? trim($this->quickConsentRef) : 'Lista autorizada pelo operador',
        ] : null;

        $stats = $importService->commit(
            $parsed['rows'],
            $detected['mapping'],
            $detected['has_header'],
            $country,
            auth()->user(),
            $evidence,
            $groupId,
            true
        );

        $this->quickText = '';
        $this->queueTab = 'pending';
        $this->resetErrorBag('quickText');
        $this->flashMessage = sprintf(
            'Lista atualizada: %d novo(s) salvo(s), %d já existente(s), %d inválido(s) ignorado(s).',
            $stats['created'],
            $stats['updated_existing'],
            $stats['rejected_invalid']
        );
    }

    public function authorizeContact(int $contactId, ContactActionService $actionService): void
    {
        $this->flashMessage = null;
        $this->flashError = null;

        $contact = Contact::query()->find($contactId);
        if ($contact === null) {
            $this->flashError = 'Contato não encontrado.';

            return;
        }

        try {
            $actionService->authorizePendingContact($contact, auth()->user());
            $this->flashMessage = "Contato {$contact->name} autorizado e pronto para envio.";
        } catch (DomainRuleException $e) {
            $this->flashError = $e->getMessage();
        }
    }

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
            $result = $actionService->triggerPrimaryInvite(
                $contact,
                auth()->user(),
                templateId: $this->selectedTemplateId ?: null
            );
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

        $invitation = Invitation::query()->find($invitationId);
        if ($invitation === null) {
            $this->flashError = 'Convite não encontrado.';

            return;
        }

        $actionService->confirmManualSend($invitation, auth()->user());
        $this->flashMessage = 'Envio confirmado! O contato foi movido para "Já enviados".';
    }

    public function confirmEntry(int $contactId, ContactActionService $actionService): void
    {
        $this->flashMessage = null;
        $this->flashError = null;

        $contact = Contact::query()->find($contactId);
        if ($contact === null) {
            $this->flashError = 'Contato não encontrado.';

            return;
        }

        $actionService->confirmGroupEntry($contact, auth()->user());
        $this->flashMessage = 'Entrada no grupo confirmada.';
    }

    public function render(WhatsAppConfigStatus $configStatus)
    {
        $mode = $configStatus->currentMode();
        $primaryButtonLabel = $mode === WhatsAppConfigStatus::MODE_ASSISTED ? 'Abrir WhatsApp' : 'Enviar convite';

        $sentStatuses = [
            Invitation::STATUS_ASSISTED_SENT_MANUAL,
            Invitation::STATUS_SENT,
            Invitation::STATUS_DELIVERED,
            Invitation::STATUS_READ,
        ];

        $sentFilter = static function (Builder $query) use ($sentStatuses): void {
            $query->whereIn('pipeline_stage', [
                Contact::STAGE_CONVITE_ENVIADO,
                Contact::STAGE_RESPONDEU,
                Contact::STAGE_ENTRADA_CONFIRMADA,
            ])->orWhereHas('invitations', fn (Builder $inv) => $inv->whereIn('status', $sentStatuses));
        };

        $pendingFilter = static function (Builder $query) use ($sentStatuses): void {
            $query->where('consent_status', '!=', Contact::CONSENT_REVOGADO)
                ->whereDoesntHave('suppressionEntry')
                ->whereNotIn('pipeline_stage', [
                    Contact::STAGE_CONVITE_ENVIADO,
                    Contact::STAGE_RESPONDEU,
                    Contact::STAGE_ENTRADA_CONFIRMADA,
                    Contact::STAGE_NAO_INTERESSADO,
                ])
                ->whereDoesntHave('invitations', fn (Builder $inv) => $inv->whereIn('status', $sentStatuses));
        };

        $totalContacts = Contact::query()->count();
        $notSentCount = Contact::query()->where($pendingFilter)->count();
        $sentCount = Contact::query()->where($sentFilter)->count();

        $queueContacts = Contact::query()
            ->with(['defaultGroup', 'suppressionEntry', 'invitations' => fn ($q) => $q->latest()->limit(1)])
            ->where($this->queueTab === 'sent' ? $sentFilter : $pendingFilter)
            ->orderByDesc('updated_at')
            ->limit(15)
            ->get();

        return view('livewire.dashboard', [
            'mode' => $mode,
            'primaryButtonLabel' => $primaryButtonLabel,
            'totalContacts' => $totalContacts,
            'notSentCount' => $notSentCount,
            'sentCount' => $sentCount,
            'queueContacts' => $queueContacts,
            'activeGroups' => Group::query()->where('is_active', true)->whereNull('revoked_at')->orderBy('name')->get(),
            'templates' => MessageTemplate::query()->orderByDesc('is_default')->orderBy('display_name')->get(),
        ]);
    }
}
