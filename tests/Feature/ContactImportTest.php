<?php

namespace Tests\Feature;

use App\Models\ConsentEvent;
use App\Models\Contact;
use App\Models\SuppressionEntry;
use App\Models\User;
use App\Services\ContactImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_parses_semicolon_lines_and_identifies_invalid_and_duplicate_phones(): void
    {
        $service = app(ContactImportService::class);

        $raw = implode("\n", [
            'nome;telefone;origem;interesse',
            'Maria Silva;+55 11 99876-5432;indicacao;Eletrônicos',
            'Maria Duplicada;+55 (11) 99876-5432;indicacao;Casa',
            'João Sem DDD;99876-5432;planilha;Moda',
        ]);

        $parsed = $service->parseLines($raw);
        $preview = $service->buildPreview(
            $parsed['rows'],
            ['name' => 0, 'phone' => 1, 'origin' => 2, 'interests' => 3],
            hasHeader: true,
            defaultCountryCode: null
        );

        $this->assertSame(1, $preview['summary']['valid_new']);
        $this->assertSame(1, $preview['summary']['duplicate_in_batch']);
        $this->assertSame(1, $preview['summary']['invalid']);
        $this->assertSame('+5511998765432', $preview['items'][0]['phone_e164']);
        $this->assertSame('duplicate_in_batch', $preview['items'][1]['status']);
        $this->assertSame('invalid', $preview['items'][2]['status']);
    }

    public function test_duplicate_import_does_not_duplicate_contact_and_invalid_phone_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $service = app(ContactImportService::class);

        $raw = implode("\n", [
            'Ana Costa;+55 21 98765-4321;evento;Supermercado',
            'Ana Duplicada;+55 21 98765-4321;evento;Viagens',
            'Pedro Inválido;1234;evento;Moda',
        ]);

        $parsed = $service->parseLines($raw);
        $result = $service->commit(
            $parsed['rows'],
            ['name' => 0, 'phone' => 1, 'origin' => 2, 'interests' => 3],
            hasHeader: false,
            defaultCountryCode: null,
            actor: $user,
            consentEvidence: null
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['rejected_invalid']);
        $this->assertSame(1, Contact::query()->where('phone_e164', '+5521987654321')->count());

        $contact = Contact::query()->where('phone_e164', '+5521987654321')->firstOrFail();
        // Importação sem evidência NÃO concede autorização
        $this->assertSame(Contact::CONSENT_PENDENTE, $contact->consent_status);
        $this->assertSame(Contact::STAGE_NOVO, $contact->pipeline_stage);

        // Reimportar o mesmo telefone não cria um segundo contato
        $result2 = $service->commit(
            [['Ana Costa Atualizada', '+55 21 98765-4321', 'planilha_2', 'Eletrônicos']],
            ['name' => 0, 'phone' => 1, 'origin' => 2, 'interests' => 3],
            hasHeader: false,
            defaultCountryCode: null,
            actor: $user,
            consentEvidence: null
        );

        $this->assertSame(0, $result2['created']);
        $this->assertSame(1, $result2['updated_existing']);
        $this->assertSame(1, Contact::query()->where('phone_e164', '+5521987654321')->count());
    }

    public function test_reimport_never_removes_suppression_or_overrides_opt_out(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $contact = Contact::create([
            'name' => 'Carlos Optout',
            'phone_e164' => '+5531991234567',
            'origin' => 'captacao_publica',
            'pipeline_stage' => Contact::STAGE_NAO_INTERESSADO,
            'consent_status' => Contact::CONSENT_REVOGADO,
            'record_source' => Contact::SOURCE_REAL,
        ]);

        SuppressionEntry::create([
            'contact_id' => $contact->id,
            'phone_e164' => $contact->phone_e164,
            'reason' => 'sair_whatsapp',
            'source' => 'webhook',
            'retain_until' => now()->addDays(365),
        ]);

        ConsentEvent::create([
            'contact_id' => $contact->id,
            'status' => ConsentEvent::STATUS_REVOKED,
            'origin' => 'webhook_sair',
            'scope' => 'convite_grupos_aguia',
            'policy_version' => 'v1.0-2026-11',
            'evidence_reference' => 'wamid.optout.1',
            'occurred_at' => now(),
        ]);

        $service = app(ContactImportService::class);

        $result = $service->commit(
            [['Carlos Reimportado', '+55 31 99123-4567', 'lista_vip', 'Eletrônicos']],
            ['name' => 0, 'phone' => 1, 'origin' => 2, 'interests' => 3],
            hasHeader: false,
            defaultCountryCode: null,
            actor: $user,
            consentEvidence: [
                'scope' => 'convite_grupos_aguia',
                'policy_version' => 'v1.0-2026-11',
                'evidence_reference' => 'planilha-assinada-2026',
                'origin' => 'importacao_com_evidencia',
            ]
        );

        $this->assertSame(1, $result['skipped_suppressed']);
        $this->assertDatabaseHas('suppression_entries', [
            'phone_e164' => '+5531991234567',
        ]);

        $contact->refresh();
        $this->assertSame(Contact::CONSENT_REVOGADO, $contact->consent_status);
        $this->assertSame(Contact::STAGE_NAO_INTERESSADO, $contact->pipeline_stage);
    }

    public function test_import_accepts_phone_only_lines_when_allow_blank_name_is_enabled(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $service = app(ContactImportService::class);

        $raw = implode("\n", [
            '+55 11 98888-7766',
            '+55 21 97777-6655',
        ]);

        $parsed = $service->parseLines($raw);
        $preview = $service->buildPreview(
            $parsed['rows'],
            ['name' => null, 'phone' => 0, 'origin' => null, 'interests' => null],
            hasHeader: false,
            defaultCountryCode: '55',
            allowBlankName: true
        );

        $this->assertSame(2, $preview['summary']['valid_new']);

        $result = $service->commit(
            $parsed['rows'],
            ['name' => null, 'phone' => 0, 'origin' => null, 'interests' => null],
            hasHeader: false,
            defaultCountryCode: '55',
            actor: $user,
            consentEvidence: null,
            allowBlankName: true
        );

        $this->assertSame(2, $result['created']);
        $this->assertDatabaseHas('contacts', ['phone_e164' => '+5511988887766']);
        $this->assertDatabaseHas('contacts', ['phone_e164' => '+5521977776655']);
    }

    public function test_extract_phone_lines_from_noisy_ocr_text_normalizes_and_deduplicates(): void
    {
        $service = app(ContactImportService::class);

        $ocrText = <<<'TXT'
Lista de Contatos 2026
182 pessoas
~ Ana
+55 11 96068-3139
(21) 98765-4321
+5511960683139
codigo 12345
11 99123-4567
TXT;

        $phones = $service->extractPhoneLinesFromText($ocrText, '55');

        $this->assertSame([
            '+5511960683139',
            '+5521987654321',
            '+5511991234567',
        ], $phones);
    }
}

