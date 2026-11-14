<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidPhoneException;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\ConsentEvent;
use App\Models\Contact;
use App\Models\Group;
use App\Models\SuppressionEntry;
use App\Services\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function store(Request $request, PhoneNormalizer $phoneNormalizer): RedirectResponse
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
            $phoneE164 = $phoneNormalizer->normalize($validated['phone'], $validated['country_code']);
        } catch (InvalidPhoneException $e) {
            return back()
                ->withInput()
                ->withErrors(['phone' => $e->getMessage()]);
        }

        $interests = array_values(array_unique(array_filter(array_map('trim', $validated['interests'] ?? []))));
        $policyVersion = (string) AppSetting::getValue('consent_policy_version', config('aguia.default_policy_version'));

        $matchedGroup = null;
        if ($interests !== []) {
            $matchedGroup = Group::query()
                ->where('is_active', true)
                ->whereNull('revoked_at')
                ->whereIn('category', $interests)
                ->first();
        }

        DB::transaction(function () use ($validated, $phoneE164, $interests, $policyVersion, $matchedGroup, $phoneNormalizer): void {
            $contact = Contact::query()->where('phone_e164', $phoneE164)->first();

            // Self-service explicit re-opt-in removes prior suppression entry only when submitted directly by the user
            $hadSuppression = SuppressionEntry::query()->where('phone_e164', $phoneE164)->delete() > 0;

            if ($contact === null) {
                $contact = Contact::create([
                    'name' => trim($validated['name']),
                    'phone_e164' => $phoneE164,
                    'origin' => 'captacao_publica',
                    'interests' => $interests,
                    'interests_text' => implode(', ', $interests),
                    'default_group_id' => $matchedGroup?->id,
                    'pipeline_stage' => Contact::STAGE_AUTORIZADO,
                    'consent_status' => Contact::CONSENT_AUTORIZADO,
                    'record_source' => Contact::SOURCE_REAL,
                ]);
            } else {
                $mergedInterests = array_values(array_unique(array_merge($contact->interests ?? [], $interests)));
                $contact->update([
                    'name' => trim($validated['name']),
                    'interests' => $mergedInterests,
                    'interests_text' => implode(', ', $mergedInterests),
                    'default_group_id' => $contact->default_group_id ?? $matchedGroup?->id,
                    'consent_status' => Contact::CONSENT_AUTORIZADO,
                    'pipeline_stage' => in_array($contact->pipeline_stage, [Contact::STAGE_NOVO, Contact::STAGE_NAO_INTERESSADO], true)
                        ? Contact::STAGE_AUTORIZADO
                        : $contact->pipeline_stage,
                ]);
            }

            ConsentEvent::create([
                'contact_id' => $contact->id,
                'status' => ConsentEvent::STATUS_GRANTED,
                'origin' => 'captacao_publica',
                'scope' => 'convite_grupos_aguia',
                'policy_version' => $policyVersion,
                'evidence_reference' => 'form_publico_checkbox',
                'occurred_at' => now(),
            ]);

            AuditLog::record($hadSuppression ? 'contact.reopted_in_public' : 'contact.captured_public', $contact, [
                'phone_masked' => $phoneNormalizer->mask($phoneE164),
                'policy_version' => $policyVersion,
                'interests' => $interests,
            ]);
        });

        return redirect()
            ->route('public.capture')
            ->with('capture_success', 'Cadastro confirmado! Sua autorização foi registrada e nossa equipe enviará o convite oficial do grupo em breve pelo WhatsApp.');
    }
}
