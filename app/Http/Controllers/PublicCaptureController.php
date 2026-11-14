<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidPhoneException;
use App\Models\AppSetting;
use App\Services\LeadCaptureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicCaptureController extends Controller
{
    public function show(): View
    {
        $categoriesJson = AppSetting::getValue('capture_categories');
        $categories = is_string($categoriesJson) ? json_decode($categoriesJson, true) : null;
        if (! is_array($categories) || $categories === []) {
            $categories = config('aguia.default_categories');
        }

        $policyVersion = (string) AppSetting::getValue('consent_policy_version', config('aguia.default_policy_version'));
        $headline = (string) AppSetting::getValue('capture_headline', 'Entre para os grupos oficiais de ofertas do Águia das Promos');
        $description = (string) AppSetting::getValue('capture_description', 'Receba apenas convites das categorias que você escolher. Entrada voluntária pelo link oficial e descadastro imediato a qualquer momento respondendo SAIR.');

        return view('public.capture', [
            'categories' => $categories,
            'policyVersion' => $policyVersion,
            'headline' => $headline,
            'description' => $description,
        ]);
    }

    public function store(Request $request, LeadCaptureService $captureService): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'country_code' => ['required', 'string', 'regex:/^\d{1,3}$/'],
            'phone' => ['required', 'string', 'min:8', 'max:30'],
            'interests' => ['nullable', 'array'],
            'interests.*' => ['string', 'max:80'],
            'consent_accepted' => ['accepted'],
        ], [
            'name.required' => 'Informe seu nome.',
            'country_code.required' => 'Informe o DDI do país.',
            'phone.required' => 'Informe seu WhatsApp com DDD.',
            'consent_accepted.accepted' => 'Você precisa marcar a caixa de autorização explícita para receber o convite pelo WhatsApp.',
        ]);

        try {
            $result = $captureService->capture(
                name: $validated['name'],
                rawPhone: $validated['phone'],
                countryCode: $validated['country_code'],
                interests: $validated['interests'] ?? [],
                origin: 'captacao_publica',
                evidenceReference: 'form_publico_checkbox',
                allowReoptIn: true,
                auditAction: 'contact.captured_public'
            );
        } catch (InvalidPhoneException $e) {
            return back()
                ->withInput()
                ->withErrors(['phone' => $e->getMessage()]);
        }

        return redirect()
            ->route('public.capture')
            ->with('capture_success', 'Cadastro confirmado! Sua autorização foi registrada e nossa equipe enviará o convite oficial do grupo em breve pelo WhatsApp.')
            ->with('capture_group_name', $result['matched_group']?->name)
            ->with('capture_group_invite_url', $result['group_invite_url'])
            ->with('capture_support_whatsapp_url', $result['support_whatsapp_url']);
    }
}
