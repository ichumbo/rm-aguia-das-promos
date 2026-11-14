<?php

namespace App\Livewire\Templates;

use App\Exceptions\DomainRuleException;
use App\Models\AuditLog;
use App\Models\MessageTemplate;
use App\Services\MetaTemplateSyncService;
use App\Services\WhatsApp\WhatsAppConfigStatus;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Index extends Component
{
    public bool $showFormModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $display_name = '';

    public string $category = 'MARKETING';

    public string $language = 'pt_BR';

    public string $status = MessageTemplate::STATUS_APPROVED;

    public string $body_text = '';

    public bool $is_default = false;

    public ?string $flashMessage = null;

    public ?string $flashError = null;

    public ?int $quickTemplateId = null;

    public string $quickBodyText = '';

    public function mount(): void
    {
        $default = $this->resolveDefaultTemplate();

        if ($default !== null) {
            $this->quickTemplateId = $default->id;
            $this->quickBodyText = $default->body_text;
        } else {
            $this->quickBodyText = 'Olá, {{nome}}! Aqui é o Águia das Promos. Seu convite para o grupo de {{categoria}} está aqui: {{link_grupo}}. A participação é gratuita. Se não quiser receber novas mensagens, responda SAIR.';
        }
    }

    public function appendQuickPlaceholder(string $placeholder): void
    {
        $allowed = ['{{nome}}', '{{categoria}}', '{{grupo}}', '{{link_grupo}}'];
        if (! in_array($placeholder, $allowed, true)) {
            return;
        }

        $spacer = $this->quickBodyText !== '' && ! str_ends_with($this->quickBodyText, ' ') ? ' ' : '';
        $this->quickBodyText .= $spacer . $placeholder;
    }

    public function saveQuickDefaultMessage(): void
    {
        $validated = $this->validate([
            'quickBodyText' => ['required', 'string', 'min:10', 'max:1024'],
        ], [
            'quickBodyText.required' => 'Digite o texto da mensagem automática padrão.',
            'quickBodyText.min' => 'A mensagem deve ter pelo menos 10 caracteres.',
        ]);

        $saved = DB::transaction(function () use ($validated): MessageTemplate {
            $targetId = $this->quickTemplateId ?? $this->resolveDefaultTemplate()?->id;

            MessageTemplate::query()->update(['is_default' => false]);

            $target = $targetId !== null ? MessageTemplate::query()->find($targetId) : null;

            if ($target !== null) {
                $target->update([
                    'body_text' => $validated['quickBodyText'],
                    'is_default' => true,
                ]);
            } else {
                $target = MessageTemplate::create([
                    'name' => 'convite_padrao_aguia',
                    'display_name' => 'Mensagem Automática Padrão',
                    'category' => 'MARKETING',
                    'language' => 'pt_BR',
                    'status' => MessageTemplate::STATUS_APPROVED,
                    'body_text' => $validated['quickBodyText'],
                    'is_default' => true,
                ]);
            }

            $target->update([
                'variables' => $target->extractPlaceholders(),
            ]);

            AuditLog::record('template.default_updated', $target, [
                'template_id' => $target->id,
            ]);

            return $target;
        });

        $this->quickTemplateId = $saved->id;
        $this->flashMessage = 'Mensagem automática padrão salva com sucesso. Ela já está pronta para os próximos envios.';
        $this->flashError = null;
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->body_text = 'Olá, {{nome}}! Aqui é o Águia das Promos. Seu convite para o grupo de {{categoria}} está aqui: {{link_grupo}}. A participação é gratuita. Se não quiser receber novas mensagens, responda SAIR.';
        $this->showFormModal = true;
    }

    public function openEdit(int $templateId): void
    {
        $template = MessageTemplate::query()->findOrFail($templateId);
        $this->editingId = $template->id;
        $this->name = $template->name;
        $this->display_name = $template->display_name;
        $this->category = $template->category;
        $this->language = $template->language;
        $this->status = $template->status;
        $this->body_text = $template->body_text;
        $this->is_default = $template->is_default;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'regex:/^[a-z0-9_]+$/', 'max:128'],
            'display_name' => ['required', 'string', 'min:2', 'max:150'],
            'category' => ['required', 'in:MARKETING,UTILITY'],
            'language' => ['required', 'string', 'max:16'],
            'status' => ['required', 'in:APPROVED,PENDING,PAUSED,REJECTED,DISABLED'],
            'body_text' => ['required', 'string', 'min:10', 'max:1024'],
            'is_default' => ['boolean'],
        ], [
            'name.regex' => 'O nome técnico deve usar apenas letras minúsculas, números e sublinhado (_).',
        ]);

        DB::transaction(function () use ($validated): void {
            if ($validated['is_default']) {
                MessageTemplate::query()->update(['is_default' => false]);
            }

            if ($this->editingId !== null) {
                $template = MessageTemplate::query()->findOrFail($this->editingId);
                $template->update($validated);
            } else {
                $template = MessageTemplate::create($validated);
            }

            $template->update([
                'variables' => $template->extractPlaceholders(),
            ]);

            AuditLog::record($this->editingId ? 'template.updated' : 'template.created', $template, [
                'status' => $template->status,
            ]);
        });

        $this->flashMessage = 'Modelo salvo com sucesso.';
        $this->flashError = null;
        $this->showFormModal = false;
        $this->resetForm();
    }

    public function setDefault(int $templateId): void
    {
        DB::transaction(function () use ($templateId): void {
            MessageTemplate::query()->update(['is_default' => false]);
            $template = MessageTemplate::query()->findOrFail($templateId);
            $template->update(['is_default' => true]);
            AuditLog::record('template.set_default', $template);
        });

        $template = $this->resolveDefaultTemplate();
        if ($template !== null) {
            $this->quickTemplateId = $template->id;
            $this->quickBodyText = $template->body_text;
        }

        $this->flashMessage = 'Modelo padrão atualizado.';
    }

    private function resolveDefaultTemplate(): ?MessageTemplate
    {
        return MessageTemplate::query()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    public function syncMeta(MetaTemplateSyncService $syncService): void
    {
        $this->flashMessage = null;
        $this->flashError = null;

        try {
            $result = $syncService->syncFromMeta();
            $this->flashMessage = "Sincronização concluída: {$result['synced']} modelo(s) atualizados.";
        } catch (DomainRuleException $e) {
            $this->flashError = $e->getMessage();
        }
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->display_name = '';
        $this->category = 'MARKETING';
        $this->language = 'pt_BR';
        $this->status = MessageTemplate::STATUS_APPROVED;
        $this->body_text = '';
        $this->is_default = false;
    }

    public function render(WhatsAppConfigStatus $configStatus)
    {
        return view('livewire.templates.index', [
            'templates' => MessageTemplate::query()->orderByDesc('is_default')->orderBy('display_name')->get(),
            'isApiConfigured' => $configStatus->isConfigured(),
        ]);
    }
}

