<?php

namespace App\Services;

use App\Exceptions\InvalidPhoneException;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\ConsentEvent;
use App\Models\Contact;
use App\Models\SuppressionEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ContactImportService
{
    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer
    ) {}

    /**
     * Parse pasted lines or CSV content into normalized 2D array of columns + delimiter detected.
     *
     * @return array{delimiter: string, rows: list<list<string>>}
     */
    public function parseLines(string $rawContent): array
    {
        $clean = str_replace(["\r\n", "\r"], "\n", trim($rawContent));
        if ($clean === '') {
            return ['delimiter' => ';', 'rows' => []];
        }

        // Remove UTF-8 BOM if present
        $clean = preg_replace('/^\xEF\xBB\xBF/', '', $clean) ?? $clean;

        $lines = array_values(array_filter(
            explode("\n", $clean),
            static fn (string $line): bool => trim($line) !== ''
        ));

        if ($lines === []) {
            return ['delimiter' => ';', 'rows' => []];
        }

        $firstLine = $lines[0];
        $semicolonCount = substr_count($firstLine, ';');
        $commaCount = substr_count($firstLine, ',');
        $delimiter = $semicolonCount >= $commaCount ? ';' : ',';

        $rows = [];
        foreach ($lines as $line) {
            $cols = str_getcsv($line, $delimiter, '"', '\\');
            $rows[] = array_map(static fn (?string $col): string => trim((string) $col), $cols);
        }

        return ['delimiter' => $delimiter, 'rows' => $rows];
    }

    /**
     * Build preview items with column mapping, phone normalization, duplicate and suppression detection.
     *
     * @param list<list<string>> $rows
     * @param array{name: int|null, phone: int|null, origin: int|null, interests: int|null} $mapping
     * @return array{
     *     items: list<array<string, mixed>>,
     *     summary: array{total: int, valid_new: int, existing: int, suppressed: int, duplicate_in_batch: int, invalid: int}
     * }
     */
    public function buildPreview(
        array $rows,
        array $mapping,
        bool $hasHeader = true,
        ?string $defaultCountryCode = null
    ): array {
        $dataRows = $hasHeader ? array_slice($rows, 1) : $rows;
        $seenPhones = [];
        $items = [];

        $summary = [
            'total' => 0,
            'valid_new' => 0,
            'existing' => 0,
            'suppressed' => 0,
            'duplicate_in_batch' => 0,
            'invalid' => 0,
        ];

        // Preload existing phones and suppressed phones for efficient preview
        $normalizedCandidates = [];
        foreach ($dataRows as $row) {
            $rawPhone = $mapping['phone'] !== null ? ($row[$mapping['phone']] ?? '') : '';
            try {
                $normalizedCandidates[] = $this->phoneNormalizer->normalize($rawPhone, $defaultCountryCode);
            } catch (InvalidPhoneException) {
                // Handled row-by-row below
            }
        }

        $existingMap = Contact::query()
            ->whereIn('phone_e164', $normalizedCandidates)
            ->pluck('id', 'phone_e164')
            ->all();

        $suppressedMap = SuppressionEntry::query()
            ->whereIn('phone_e164', $normalizedCandidates)
            ->pluck('reason', 'phone_e164')
            ->all();

        foreach ($dataRows as $index => $row) {
            $summary['total']++;
            $name = trim((string) ($mapping['name'] !== null ? ($row[$mapping['name']] ?? '') : ''));
            $rawPhone = trim((string) ($mapping['phone'] !== null ? ($row[$mapping['phone']] ?? '') : ''));
            $origin = trim((string) ($mapping['origin'] !== null ? ($row[$mapping['origin']] ?? '') : ''));
            $interestsRaw = trim((string) ($mapping['interests'] !== null ? ($row[$mapping['interests']] ?? '') : ''));

            if ($name === '') {
                $summary['invalid']++;
                $items[] = [
                    'line' => $index + ($hasHeader ? 2 : 1),
                    'name' => $name,
                    'raw_phone' => $rawPhone,
                    'phone_e164' => null,
                    'origin' => $origin ?: 'importacao_csv',
                    'interests' => $this->parseInterests($interestsRaw),
                    'status' => 'invalid',
                    'message' => 'Nome obrigatório.',
                ];

                continue;
            }

            try {
                $phoneE164 = $this->phoneNormalizer->normalize($rawPhone, $defaultCountryCode);
            } catch (InvalidPhoneException $e) {
                $summary['invalid']++;
                $items[] = [
                    'line' => $index + ($hasHeader ? 2 : 1),
                    'name' => $name,
                    'raw_phone' => $rawPhone,
                    'phone_e164' => null,
                    'origin' => $origin ?: 'importacao_csv',
                    'interests' => $this->parseInterests($interestsRaw),
                    'status' => 'invalid',
                    'message' => $e->getMessage(),
                ];

                continue;
            }

            if (isset($seenPhones[$phoneE164])) {
                $summary['duplicate_in_batch']++;
                $items[] = [
                    'line' => $index + ($hasHeader ? 2 : 1),
                    'name' => $name,
                    'raw_phone' => $rawPhone,
                    'phone_e164' => $phoneE164,
                    'origin' => $origin ?: 'importacao_csv',
                    'interests' => $this->parseInterests($interestsRaw),
                    'status' => 'duplicate_in_batch',
                    'message' => 'Duplicado dentro do mesmo lote.',
                ];

                continue;
            }

            $seenPhones[$phoneE164] = true;

            if (isset($suppressedMap[$phoneE164])) {
                $summary['suppressed']++;
                $items[] = [
                    'line' => $index + ($hasHeader ? 2 : 1),
                    'name' => $name,
                    'raw_phone' => $rawPhone,
                    'phone_e164' => $phoneE164,
                    'origin' => $origin ?: 'importacao_csv',
                    'interests' => $this->parseInterests($interestsRaw),
                    'status' => 'suppressed',
                    'message' => 'Descadastrado (' . $suppressedMap[$phoneE164] . ') — não será reativado.',
                ];

                continue;
            }

            if (isset($existingMap[$phoneE164])) {
                $summary['existing']++;
                $items[] = [
                    'line' => $index + ($hasHeader ? 2 : 1),
                    'name' => $name,
                    'raw_phone' => $rawPhone,
                    'phone_e164' => $phoneE164,
                    'origin' => $origin ?: 'importacao_csv',
                    'interests' => $this->parseInterests($interestsRaw),
                    'status' => 'existing',
                    'message' => 'Já cadastrado (será atualizado sem duplicar).',
                ];

                continue;
            }

            $summary['valid_new']++;
            $items[] = [
                'line' => $index + ($hasHeader ? 2 : 1),
                'name' => $name,
                'raw_phone' => $rawPhone,
                'phone_e164' => $phoneE164,
                'origin' => $origin ?: 'importacao_csv',
                'interests' => $this->parseInterests($interestsRaw),
                'status' => 'valid_new',
                'message' => 'Pronto para importar.',
            ];
        }

        return ['items' => $items, 'summary' => $summary];
    }

    /**
     * Persist validated import items. Never duplicates phones, never removes suppression,
     * and never marks consent unless explicit evidence is provided.
     *
     * @param list<list<string>> $rows
     * @param array{name: int|null, phone: int|null, origin: int|null, interests: int|null} $mapping
     * @param array{origin?: string, scope: string, policy_version: string, evidence_reference: string}|null $consentEvidence
     * @return array{created: int, updated_existing: int, skipped_suppressed: int, skipped_duplicate: int, rejected_invalid: int}
     */
    public function commit(
        array $rows,
        array $mapping,
        bool $hasHeader,
        ?string $defaultCountryCode,
        User $actor,
        ?array $consentEvidence = null,
        ?int $defaultGroupId = null
    ): array {
        $preview = $this->buildPreview($rows, $mapping, $hasHeader, $defaultCountryCode);

        $stats = [
            'created' => 0,
            'updated_existing' => 0,
            'skipped_suppressed' => 0,
            'skipped_duplicate' => 0,
            'rejected_invalid' => 0,
        ];

        $hasValidConsentEvidence = is_array($consentEvidence)
            && trim((string) ($consentEvidence['evidence_reference'] ?? '')) !== '';

        DB::transaction(function () use ($preview, $actor, $consentEvidence, $hasValidConsentEvidence, $defaultGroupId, &$stats): void {
            foreach ($preview['items'] as $item) {
                if ($item['status'] === 'invalid') {
                    $stats['rejected_invalid']++;
                    continue;
                }

                if ($item['status'] === 'duplicate_in_batch') {
                    $stats['skipped_duplicate']++;
                    continue;
                }

                if ($item['status'] === 'suppressed') {
                    // Never overwrite or reactivate suppression entries
                    $stats['skipped_suppressed']++;
                    continue;
                }

                $phoneE164 = $item['phone_e164'];
                $interests = $item['interests'];
                $interestsText = implode(', ', $interests);

                $existing = Contact::query()->where('phone_e164', $phoneE164)->first();

                if ($existing !== null) {
                    // Double-check suppression directly before touching existing contact
                    if ($existing->isSuppressed() || $existing->consent_status === Contact::CONSENT_REVOGADO) {
                        $stats['skipped_suppressed']++;
                        continue;
                    }

                    $mergedInterests = array_values(array_unique(array_merge($existing->interests ?? [], $interests)));
                    $existing->update([
                        'name' => $existing->name ?: $item['name'],
                        'interests' => $mergedInterests,
                        'interests_text' => implode(', ', $mergedInterests),
                        'default_group_id' => $existing->default_group_id ?? $defaultGroupId,
                    ]);

                    if ($hasValidConsentEvidence && $existing->consent_status !== Contact::CONSENT_AUTORIZADO) {
                        $this->grantConsent($existing, $actor, $consentEvidence);
                    }

                    $stats['updated_existing']++;
                    continue;
                }

                $contact = Contact::create([
                    'name' => $item['name'],
                    'phone_e164' => $phoneE164,
                    'origin' => $item['origin'] ?: 'importacao_csv',
                    'interests' => $interests,
                    'interests_text' => $interestsText,
                    'owner_id' => $actor->id,
                    'default_group_id' => $defaultGroupId,
                    'pipeline_stage' => $hasValidConsentEvidence ? Contact::STAGE_AUTORIZADO : Contact::STAGE_NOVO,
                    'consent_status' => $hasValidConsentEvidence ? Contact::CONSENT_AUTORIZADO : Contact::CONSENT_PENDENTE,
                    'record_source' => Contact::SOURCE_REAL,
                ]);

                if ($hasValidConsentEvidence) {
                    $this->grantConsent($contact, $actor, $consentEvidence);
                }

                $stats['created']++;
            }

            AuditLog::record('contact.imported', null, [
                'stats' => $stats,
                'has_consent_evidence' => $hasValidConsentEvidence,
            ], $actor->id);
        });

        return $stats;
    }

    /**
     * @return list<string>
     */
    private function parseInterests(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $parts = preg_split('/[|,\/]+/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn (string $v): bool => $v !== ''));
    }

    /**
     * @param array{origin?: string, scope?: string, policy_version?: string, evidence_reference: string} $evidence
     */
    private function grantConsent(Contact $contact, User $actor, array $evidence): void
    {
        $policyVersion = $evidence['policy_version']
            ?? AppSetting::getValue('consent_policy_version', config('aguia.default_policy_version'));

        ConsentEvent::create([
            'contact_id' => $contact->id,
            'status' => ConsentEvent::STATUS_GRANTED,
            'origin' => $evidence['origin'] ?? 'importacao_com_evidencia',
            'scope' => $evidence['scope'] ?? 'convite_grupos_aguia',
            'policy_version' => (string) $policyVersion,
            'evidence_reference' => $evidence['evidence_reference'],
            'recorded_by' => $actor->id,
            'occurred_at' => now(),
        ]);

        $contact->update([
            'consent_status' => Contact::CONSENT_AUTORIZADO,
            'pipeline_stage' => $contact->pipeline_stage === Contact::STAGE_NOVO
                ? Contact::STAGE_AUTORIZADO
                : $contact->pipeline_stage,
        ]);
    }
}
