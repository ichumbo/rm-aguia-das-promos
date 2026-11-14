<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainRuleException;
use App\Exceptions\InvalidPhoneException;
use App\Models\AppSetting;
use App\Services\LeadCaptureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadCaptureWebhookController extends Controller
{
    public function handle(Request $request, LeadCaptureService $captureService): JsonResponse
    {
        if (! $this->hasValidToken($request)) {
            return response()->json([
                'message' => 'Token de captação inválido ou ausente.',
            ], 401);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'min:8', 'max:30'],
            'country_code' => ['nullable', 'string', 'regex:/^\d{1,3}$/'],
            'interests' => ['nullable'],
            'origin' => ['nullable', 'string', 'max:80'],
            'evidence_reference' => ['nullable', 'string', 'max:120'],
            'consent_accepted' => ['required', 'accepted'],
        ]);

        $interests = $this->parseInterests($validated['interests'] ?? null);

        try {
            $result = $captureService->capture(
                name: $validated['name'],
                rawPhone: $validated['phone'],
                countryCode: $validated['country_code'] ?? null,
                interests: $interests,
                origin: (string) ($validated['origin'] ?? 'webhook_captacao'),
                evidenceReference: (string) ($validated['evidence_reference'] ?? 'webhook_json_consent'),
                allowReoptIn: false,
                auditAction: 'contact.captured_webhook'
            );
        } catch (InvalidPhoneException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['phone' => [$e->getMessage()]],
            ], 422);
        } catch (DomainRuleException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'status' => $result['created'] ? 'created' : 'updated',
            'contact_id' => $result['contact']->id,
            'phone_e164' => $result['contact']->phone_e164,
            'group_name' => $result['matched_group']?->name,
            'group_invite_url' => $result['group_invite_url'],
        ], $result['created'] ? 201 : 200);
    }

    private function hasValidToken(Request $request): bool
    {
        $providedToken = (string) ($request->bearerToken() ?? $request->header('X-Capture-Token', ''));
        if ($providedToken === '') {
            return false;
        }

        $envToken = trim((string) config('services.capture.webhook_token', ''));
        if ($envToken !== '' && hash_equals($envToken, $providedToken)) {
            return true;
        }

        $storedHash = trim((string) AppSetting::getValue('capture_webhook_token_hash', ''));
        if ($storedHash !== '' && hash_equals($storedHash, hash('sha256', $providedToken))) {
            return true;
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function parseInterests(mixed $rawInterests): array
    {
        if (is_array($rawInterests)) {
            return array_values(array_filter(array_map(
                static fn (mixed $item): string => is_string($item) ? trim($item) : '',
                $rawInterests
            )));
        }

        if (is_string($rawInterests)) {
            $parts = preg_split('/[,;]+/', $rawInterests) ?: [];

            return array_values(array_filter(array_map('trim', $parts)));
        }

        return [];
    }
}
