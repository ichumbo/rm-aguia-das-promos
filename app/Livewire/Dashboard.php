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
use Illuminate\Http\UploadedFile;
use Livewire\Component;
use Livewire\WithFileUploads;

class Dashboard extends Component
{
    use WithFileUploads;

    public string $imageTab = 'ocr';

    public mixed $quickTemplateImage = null;

    public string $quickText = '';

    public string $quickDefaultCountry = '55';

    public string|int|null $quickGroupId = '';

    public bool $quickAuthorizeContacts = false;

    public string $quickConsentRef = 'Lista autorizada pelo operador';

    public ?int $selectedTemplateId = null;

    public string $queueTab = 'pending';

    public ?string $flashMessage = null;

    public ?string $flashError = null;

    /** @var list<int|string> */
    public array $selectedContactIds = [];

    /** @var list<array{invitation_id: int, contact_name: string, phone: string, url: string, message?: string, image_url?: ?string}> */
    public array $batchWaLinks = [];

    public function saveQuickTemplateImage(): void
    {
        $this->flashMessage = null;
        $this->flashError = null;

        $this->validate([
            'quickTemplateImage' => ['required', 'image', 'max:5120'],
        ], [
            'quickTemplateImage.required' => 'Escolha uma imagem (PNG, JPG ou WebP) para anexar à mensagem.',
            'quickTemplateImage.image' => 'O arquivo enviado precisa ser uma imagem válida.',
            'quickTemplateImage.max' => 'A imagem deve ter no máximo 5 MB.',
        ]);

        if (! $this->quickTemplateImage instanceof UploadedFile) {
            return;
        }

        $template = $this->resolveSelectedOrDefaultTemplate();
        if ($template === null) {
            $template = MessageTemplate::create([
                'name' => 'convite_padrao_aguia',
                'display_name' => 'Mensagem Automática Padrão',
                'category' => 'MARKETING',
                'language' => 'pt_BR',
                'status' => MessageTemplate::STATUS_APPROVED,
                'body_text' => 'Olá, {{nome}}! Aqui é o Águia das Promos. Seu convite para o grupo de {{categoria}} está aqui: {{link_grupo}}.',
                'is_default' => true,
            ]);
            $this->selectedTemplateId = $template->id;
        }

        $template->attachMedia($this->quickTemplateImage);
        $this->quickTemplateImage = null;
        $this->imageTab = 'media';
        $this->flashMessage = 'Imagem anexada! Ao abrir o WhatsApp, ela é copiada automaticamente junto com o texto — basta colar (Cmd+V / Ctrl+V) na conversa.';
    }

    public function removeQuickTemplateImage(): void
    {
        $this->flashMessage = null;
        $this->flashError = null;

        $template = $this->resolveSelectedOrDefaultTemplate();
        if ($template !== null) {
            $template->removeMedia();
        }

        $this->quickTemplateImage = null;
        $this->flashMessage = 'Imagem removida da mensagem automática.';
    }

    private function resolveSelectedOrDefaultTemplate(): ?MessageTemplate
    {
        if ($this->selectedTemplateId) {
            $found = MessageTemplate::query()->find($this->selectedTemplateId);
            if ($found !== null) {
                return $found;
            }
        }

        return MessageTemplate::query()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

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
                $invitation = $result['invitation']->loadMissing('template');
                $this->dispatch(
                    'open-wa-me',
                    url: $result['wa_me_url'],
                    message: (string) $invitation->rendered_message,
                    imageUrl: $invitation->template?->mediaUrl()
                );
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

    public function bulkInviteSelected(ContactActionService $actionService): void
    {
        $this->flashMessage = null;
        $this->flashError = null;

        $ids = $this->selectedContactIds;

        if ($ids === []) {
            $ids = Contact::query()
                ->readyToInvite()
                ->whereDoesntHave('invitations', fn (Builder $q) => $q->whereNotIn('status', ['failed', 'cancelled']))
                ->orderByDesc('updated_at')
                ->limit(ContactActionService::MAX_BULK_BATCH)
                ->pluck('id')
                ->all();
        }

        try {
            $result = $actionService->triggerBulkInvites(
                $ids,
                auth()->user(),
                templateId: $this->selectedTemplateId ?: null
            );

            $this->selectedContactIds = [];
            $this->batchWaLinks = $result['wa_links'];

            if ($result['wa_links'] !== []) {
                $firstImageUrl = collect($result['wa_links'])->pluck('image_url')->filter()->first();
                $this->dispatch(
                    'open-wa-me-batch',
                    urls: array_column($result['wa_links'], 'url'),
                    imageUrl: $firstImageUrl
                );
                $this->flashMessage = $result['count'] . ' conversa(s) aberta(s) no lote. Se o navegador bloquear alguma aba, use os links abaixo e depois clique em confirmar.';
            } else {
                $this->flashMessage = $result['count'] . ' convite(s) enviado(s) em massa para a fila.';
            }
        } catch (DomainRuleException $e) {
            $this->flashError = $e->getMessage();
        }
    }

    public function confirmBatchManualSends(ContactActionService $actionService): void
    {
        $this->flashMessage = null;
        $this->flashError = null;

        $ids = array_column($this->batchWaLinks, 'invitation_id');
        if ($ids === []) {
            return;
        }

        $confirmed = $actionService->confirmBulkManualSends($ids, auth()->user());
        $this->batchWaLinks = [];
        $this->flashMessage = $confirmed . ' envio(s) confirmado(s)! Os contatos foram movidos para "Já enviados".';
    }

    public function dismissBatchPanel(): void
    {
        $this->batchWaLinks = [];
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
            'activeTemplate' => $this->resolveSelectedOrDefaultTemplate(),
        ]);
    }
}
