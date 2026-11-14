<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\AuditLog;
use App\Models\MessageTemplate;
use App\Services\WhatsApp\WhatsAppConfigStatus;
use Illuminate\Support\Facades\Http;

class MetaTemplateSyncService
{
    public function __construct(
        private readonly WhatsAppConfigStatus $configStatus
    ) {}

    /**
     * @return array{synced: int}
     */
    public function syncFromMeta(): array
    {
        if (! $this->configStatus->isConfigured()) {
            throw new DomainRuleException('API não configurada. Preencha as credenciais da Meta antes de sincronizar modelos.');
        }

        $version = (string) config('services.whatsapp.api_version', 'v21.0');
        $baseUrl = rtrim((string) config('services.whatsapp.base_url', 'https://graph.facebook.com'), '/');
        $wabaId = (string) config('services.whatsapp.waba_id');
        $token = (string) config('services.whatsapp.access_token');

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout((int) config('services.whatsapp.timeout_seconds', 10))
            ->get("{$baseUrl}/{$version}/{$wabaId}/message_templates", [
                'fields' => 'id,name,status,category,language,components',
                'limit' => 100,
            ]);

        if (! $response->successful()) {
            $sanitized = $this->configStatus->sanitizeError($response->body());
            throw new DomainRuleException('Falha ao sincronizar modelos com a Meta: HTTP ' . $response->status() . ' — ' . $sanitized);
        }

        $items = $response->json('data', []);
        $synced = 0;

        foreach ($items as $item) {
            if (! is_array($item) || empty($item['name'])) {
                continue;
            }

            $bodyText = '';
            foreach (($item['components'] ?? []) as $component) {
                if (($component['type'] ?? '') === 'BODY') {
                    $bodyText = (string) ($component['text'] ?? '');
                    break;
                }
            }

            $existing = MessageTemplate::query()
                ->where('name', (string) $item['name'])
                ->where('language', (string) ($item['language'] ?? 'pt_BR'))
                ->first();

            $template = MessageTemplate::query()->updateOrCreate(
                [
                    'name' => (string) $item['name'],
                    'language' => (string) ($item['language'] ?? 'pt_BR'),
                ],
                [
                    'display_name' => $existing?->display_name ?? ucwords(str_replace('_', ' ', (string) $item['name'])),
                    'category' => strtoupper((string) ($item['category'] ?? 'MARKETING')),
                    'status' => strtoupper((string) ($item['status'] ?? 'PENDING')),
                    'body_text' => $bodyText !== '' ? $bodyText : ($existing?->body_text ?? ''),
                    'meta_template_id' => isset($item['id']) ? (string) $item['id'] : null,
                    'last_synced_at' => now(),
                ]
            );

            $template->update([
                'variables' => $template->extractPlaceholders(),
            ]);

            $synced++;
        }

        AuditLog::record('templates.synced_meta', null, ['synced_count' => $synced]);

        return ['synced' => $synced];
    }
}
