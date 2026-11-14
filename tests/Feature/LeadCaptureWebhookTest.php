<?php

namespace Tests\Feature;

use App\Livewire\Capture\Index as CaptureIndex;
use App\Models\AppSetting;
use App\Models\ConsentEvent;
use App\Models\Contact;
use App\Models\Group;
use App\Models\SuppressionEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeadCaptureWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_rejects_missing_or_invalid_token_with_401(): void
    {
        config()->set('services.capture.webhook_token', 'secret-capture-token-123');

        $this->postJson('/webhooks/captacao', [
            'name' => 'Lead Sem Token',
            'phone' => '+5511988887777',
            'consent_accepted' => true,
        ])->assertStatus(401);

        $this->withHeader('Authorization', 'Bearer wrong-token')
            ->postJson('/webhooks/captacao', [
                'name' => 'Lead Token Errado',
                'phone' => '+5511988887777',
                'consent_accepted' => true,
            ])->assertStatus(401);

        $this->assertSame(0, Contact::count());
    }

    public function test_webhook_creates_contact_consent_event_and_returns_group_invite_url(): void
    {
        config()->set('services.capture.webhook_token', 'secret-capture-token-123');
        AppSetting::setValue('consent_policy_version', 'v3.0-2026-11');

        Group::create([
            'name' => 'Águia Casa & Cozinha',
            'category' => 'Casa & Cozinha',
            'invite_url' => 'https://chat.whatsapp.com/CasaCozinhaOficial123',
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer secret-capture-token-123')
            ->postJson('/webhooks/captacao', [
                'name' => 'Juliana Rocha',
                'phone' => '+55 (11) 97777-6655',
                'interests' => 'Casa & Cozinha, Ofertas Relâmpago',
                'origin' => 'google_forms',
                'evidence_reference' => 'gform_resp_987',
                'consent_accepted' => true,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'created',
                'phone_e164' => '+5511977776655',
                'group_name' => 'Águia Casa & Cozinha',
                'group_invite_url' => 'https://chat.whatsapp.com/CasaCozinhaOficial123',
            ]);

        $contact = Contact::query()->where('phone_e164', '+5511977776655')->firstOrFail();
        $this->assertSame('Juliana Rocha', $contact->name);
        $this->assertSame('google_forms', $contact->origin);
        $this->assertSame(['Casa & Cozinha', 'Ofertas Relâmpago'], $contact->interests);
        $this->assertSame(Contact::CONSENT_AUTORIZADO, $contact->consent_status);
        $this->assertSame(Contact::STAGE_AUTORIZADO, $contact->pipeline_stage);

        $event = ConsentEvent::query()->where('contact_id', $contact->id)->firstOrFail();
        $this->assertSame('google_forms', $event->origin);
        $this->assertSame('v3.0-2026-11', $event->policy_version);
        $this->assertSame('gform_resp_987', $event->evidence_reference);
    }

    public function test_webhook_requires_explicit_consent_and_valid_phone(): void
    {
        config()->set('services.capture.webhook_token', 'secret-capture-token-123');

        $this->withHeader('Authorization', 'Bearer secret-capture-token-123')
            ->postJson('/webhooks/captacao', [
                'name' => 'Lead Sem Consentimento',
                'phone' => '+5511977776655',
                'consent_accepted' => false,
            ])->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer secret-capture-token-123')
            ->postJson('/webhooks/captacao', [
                'name' => 'Lead Sem DDI',
                'phone' => '11977776655',
                'consent_accepted' => true,
            ])->assertStatus(422)
            ->assertJsonValidationErrors('phone');

        $this->assertSame(0, Contact::count());
    }

    public function test_webhook_updates_existing_contact_and_merges_interests_with_200(): void
    {
        config()->set('services.capture.webhook_token', 'secret-capture-token-123');

        Contact::create([
            'name' => 'Marcos Lima',
            'phone_e164' => '+5511966665544',
            'origin' => 'captacao_publica',
            'interests' => ['Supermercado'],
            'interests_text' => 'Supermercado',
            'pipeline_stage' => Contact::STAGE_NOVO,
            'consent_status' => Contact::CONSENT_PENDENTE,
            'record_source' => Contact::SOURCE_REAL,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer secret-capture-token-123')
            ->postJson('/webhooks/captacao', [
                'name' => 'Marcos Lima Atualizado',
                'phone' => '11966665544',
                'country_code' => '55',
                'interests' => ['Supermercado', 'Eletrônicos & Tech'],
                'origin' => 'n8n_landing',
                'consent_accepted' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'updated',
                'phone_e164' => '+5511966665544',
            ]);

        $contact = Contact::query()->where('phone_e164', '+5511966665544')->firstOrFail();
        $this->assertSame('Marcos Lima Atualizado', $contact->name);
        $this->assertSame(['Supermercado', 'Eletrônicos & Tech'], $contact->interests);
        $this->assertSame(Contact::CONSENT_AUTORIZADO, $contact->consent_status);
        $this->assertSame(Contact::STAGE_AUTORIZADO, $contact->pipeline_stage);
    }

    public function test_webhook_refuses_suppressed_contact_with_409_and_preserves_suppression(): void
    {
        config()->set('services.capture.webhook_token', 'secret-capture-token-123');

        SuppressionEntry::create([
            'phone_e164' => '+5511955554444',
            'reason' => 'Pediu SAIR',
            'source' => 'whatsapp_inbound',
        ]);

        $this->withHeader('Authorization', 'Bearer secret-capture-token-123')
            ->postJson('/webhooks/captacao', [
                'name' => 'Contato Bloqueado',
                'phone' => '+5511955554444',
                'consent_accepted' => true,
            ])->assertStatus(409);

        $this->assertSame(1, SuppressionEntry::where('phone_e164', '+5511955554444')->count());
        $this->assertSame(0, Contact::count());
    }

    public function test_admin_can_generate_webhook_token_and_save_support_whatsapp_in_capture_settings(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $component = Livewire::actingAs($admin)
            ->test(CaptureIndex::class)
            ->set('supportWhatsapp', '(11) 99999-0000')
            ->set('supportWhatsappCountryCode', '55')
            ->call('saveCaptureConfig')
            ->assertHasNoErrors();

        $this->assertSame('+5511999990000', AppSetting::getValue('capture_support_whatsapp'));

        $component->call('generateWebhookToken');
        $generatedToken = $component->get('generatedWebhookToken');

        $this->assertIsString($generatedToken);
        $this->assertNotEmpty($generatedToken);

        $storedHash = AppSetting::getValue('capture_webhook_token_hash');
        $this->assertSame(hash('sha256', $generatedToken), $storedHash);
        $this->assertNotSame($generatedToken, $storedHash);

        $this->withHeader('Authorization', 'Bearer '.$generatedToken)
            ->postJson('/webhooks/captacao', [
                'name' => 'Lead Token UI',
                'phone' => '+5511944443333',
                'consent_accepted' => true,
            ])->assertStatus(201);
    }

    public function test_non_admin_cannot_generate_webhook_token_or_save_capture_config(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        Livewire::actingAs($operator)
            ->test(CaptureIndex::class)
            ->call('generateWebhookToken')
            ->assertStatus(403);
    }
}
