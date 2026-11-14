<?php

namespace App\Livewire\Capture;

use App\Exceptions\InvalidPhoneException;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Services\PhoneNormalizer;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Index extends Component
{
    public string $headline = '';

    public string $description = '';

    public string $policyVersion = '';

    public string $categoriesRaw = '';

    public string $supportWhatsappCountryCode = '55';

    public string $supportWhatsapp = '';

    public bool $hasWebhookToken = false;

    public ?string $generatedWebhookToken = null;

    public ?string $flashMessage = null;

    public function mount(): void
    {
        $this->headline = (string) AppSetting::getValue('capture_headline', 'Entre para os grupos oficiais de ofertas do Águia das Promos');
        $this->description = (string) AppSetting::getValue('capture_description', 'Receba apenas convites das categorias que você escolher. Entrada voluntária pelo link oficial e descadastro imediato a qualquer momento respondendo SAIR.');
        $this->policyVersion = (string) AppSetting::getValue('consent_policy_version', config('aguia.default_policy_version'));

        $categoriesJson = AppSetting::getValue('capture_categories');
        $categories = is_string($categoriesJson) ? json_decode($categoriesJson, true) : null;
        if (! is_array($categories) || $categories === []) {
            $categories = config('aguia.default_categories');
        }
        $this->categoriesRaw = implode("\n", $categories);

        $savedSupport = trim((string) AppSetting::getValue('capture_support_whatsapp', ''));
        if ($savedSupport !== '') {
            foreach (['351', '55', '1'] as $cc) {
                if (str_starts_with($savedSupport, '+' . $cc)) {
                    $this->supportWhatsappCountryCode = $cc;
                    $this->supportWhatsapp = substr($savedSupport, strlen('+' . $cc));
                    break;
                }
            }
            if ($this->supportWhatsapp === '') {
                $this->supportWhatsapp = $savedSupport;
            }
        }

        $this->hasWebhookToken = trim((string) config('services.capture.webhook_token', '')) !== ''
            || trim((string) AppSetting::getValue('capture_webhook_token_hash', '')) !== '';
    }

    public function saveCaptureConfig(PhoneNormalizer $phoneNormalizer): void
    {
        Gate::authorize('admin-only');

        $this->validate([
            'headline' => ['required', 'string', 'min:5', 'max:200'],
            'description' => ['required', 'string', 'min:10', 'max:500'],
            'policyVersion' => ['required', 'string', 'min:2', 'max:64'],
            'categoriesRaw' => ['required', 'string'],
            'supportWhatsappCountryCode' => ['required', 'string', 'regex:/^\d{1,3}$/'],
            'supportWhatsapp' => ['nullable', 'string', 'max:30'],
        ]);

        $normalizedSupport = '';
        if (trim($this->supportWhatsapp) !== '') {
            try {
                $normalizedSupport = $phoneNormalizer->normalize($this->supportWhatsapp, $this->supportWhatsappCountryCode);
            } catch (InvalidPhoneException $e) {
                $this->addError('supportWhatsapp', $e->getMessage());

                return;
            }
        }

        $categories = array_values(array_unique(array_filter(array_map(
            static fn (string $line): string => trim($line),
            explode("\n", str_replace("\r", '', $this->categoriesRaw))
        ))));

        AppSetting::setValue('capture_headline', trim($this->headline));
        AppSetting::setValue('capture_description', trim($this->description));
        AppSetting::setValue('consent_policy_version', trim($this->policyVersion));
        AppSetting::setValue('capture_categories', $categories);
        AppSetting::setValue('capture_support_whatsapp', $normalizedSupport);

        AuditLog::record('capture.config_updated', null, [
            'policy_version' => trim($this->policyVersion),
            'categories_count' => count($categories),
            'support_whatsapp_configured' => $normalizedSupport !== '',
        ]);

        $this->flashMessage = 'Configurações da página de captação atualizadas.';
    }

    public function generateWebhookToken(): void
    {
        Gate::authorize('admin-only');

        $token = 'aguia_' . bin2hex(random_bytes(20));

        AppSetting::setValue('capture_webhook_token_hash', hash('sha256', $token));
        AuditLog::record('capture.webhook_token_rotated');

        $this->generatedWebhookToken = $token;
        $this->hasWebhookToken = true;
        $this->flashMessage = 'Novo token de webhook gerado. Copie-o agora: ele não será exibido novamente.';
    }

    public function render()
    {
        $publicUrl = route('public.capture');
        $webhookUrl = route('webhooks.capture.handle');
        $recentCaptured = Contact::query()
            ->where('record_source', Contact::SOURCE_REAL)
            ->where('consent_status', Contact::CONSENT_AUTORIZADO)
            ->latest()
            ->limit(10)
            ->get();

        return view('livewire.capture.index', [
            'publicUrl' => $publicUrl,
            'webhookUrl' => $webhookUrl,
            'totalCaptured' => Contact::query()->where('origin', 'captacao_publica')->count(),
            'recentCaptured' => $recentCaptured,
        ]);
    }
}
