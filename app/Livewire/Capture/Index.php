<?php

namespace App\Livewire\Capture;

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\Contact;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Index extends Component
{
    public string $headline = '';

    public string $description = '';

    public string $policyVersion = '';

    public string $categoriesRaw = '';

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
    }

    public function saveCaptureConfig(): void
    {
        Gate::authorize('admin-only');

        $this->validate([
            'headline' => ['required', 'string', 'min:5', 'max:200'],
            'description' => ['required', 'string', 'min:10', 'max:500'],
            'policyVersion' => ['required', 'string', 'min:2', 'max:64'],
            'categoriesRaw' => ['required', 'string'],
        ]);

        $categories = array_values(array_unique(array_filter(array_map(
            static fn (string $line): string => trim($line),
            explode("\n", str_replace("\r", '', $this->categoriesRaw))
        ))));

        AppSetting::setValue('capture_headline', trim($this->headline));
        AppSetting::setValue('capture_description', trim($this->description));
        AppSetting::setValue('consent_policy_version', trim($this->policyVersion));
        AppSetting::setValue('capture_categories', $categories);

        AuditLog::record('capture.config_updated', null, [
            'policy_version' => trim($this->policyVersion),
            'categories_count' => count($categories),
        ]);

        $this->flashMessage = 'Configurações da página de captação atualizadas.';
    }

    public function render()
    {
        $publicUrl = route('public.capture');
        $recentCaptured = Contact::query()
            ->where('origin', 'captacao_publica')
            ->latest()
            ->limit(10)
            ->get();

        return view('livewire.capture.index', [
            'publicUrl' => $publicUrl,
            'totalCaptured' => Contact::query()->where('origin', 'captacao_publica')->count(),
            'recentCaptured' => $recentCaptured,
        ]);
    }
}
