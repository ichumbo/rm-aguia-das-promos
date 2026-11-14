<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\ConsentEvent;
use App\Models\Contact;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_capture_page_renders_with_unchecked_consent_checkbox_and_privacy_notice(): void
    {
        $response = $this->get('/inscrever');

        $response->assertStatus(200);
        $response->assertSee('Águia das Promos');
        $response->assertSee('privacidade', false);
        $response->assertDontSee('checked="checked"', false);
    }

    public function test_public_capture_rejects_submission_without_explicit_consent(): void
    {
        $response = $this->post('/inscrever', [
            'name' => 'Rafael Dias',
            'country_code' => '55',
            'phone' => '(11) 99555-4433',
            'interests' => ['Eletrônicos & Tech'],
        ]);

        $response->assertSessionHasErrors('consent_accepted');
        $this->assertSame(0, Contact::count());
        $this->assertSame(0, ConsentEvent::count());
    }

    public function test_public_capture_normalizes_phone_and_records_consent_policy_version(): void
    {
        AppSetting::setValue('consent_policy_version', 'v2.1-2026-11');

        $response = $this->post('/inscrever', [
            'name' => 'Beatriz Almeida',
            'country_code' => '55',
            'phone' => '(11) 98888-1122',
            'interests' => ['Eletrônicos & Tech', 'Supermercado'],
            'consent_accepted' => '1',
        ]);

        $response->assertRedirect('/inscrever');
        $response->assertSessionHas('capture_success');

        $contact = Contact::query()->where('phone_e164', '+5511988881122')->firstOrFail();
        $this->assertSame('Beatriz Almeida', $contact->name);
        $this->assertSame(Contact::CONSENT_AUTORIZADO, $contact->consent_status);
        $this->assertSame(Contact::STAGE_AUTORIZADO, $contact->pipeline_stage);

        $event = ConsentEvent::query()->where('contact_id', $contact->id)->firstOrFail();
        $this->assertSame(ConsentEvent::STATUS_GRANTED, $event->status);
        $this->assertSame('captacao_publica', $event->origin);
        $this->assertSame('v2.1-2026-11', $event->policy_version);
        $this->assertSame('form_publico_checkbox', $event->evidence_reference);
    }

    public function test_public_capture_shows_direct_group_link_and_whatsapp_button_after_submission(): void
    {
        Group::create([
            'name' => 'Águia Tech & Eletrônicos',
            'category' => 'Eletrônicos & Tech',
            'invite_url' => 'https://chat.whatsapp.com/GrupoTechOficial123',
            'is_active' => true,
        ]);

        AppSetting::setValue('capture_support_whatsapp', '+5511999990000');

        $response = $this->followingRedirects()->post('/inscrever', [
            'name' => 'Carlos Eduardo',
            'country_code' => '55',
            'phone' => '(11) 98765-4321',
            'interests' => ['Eletrônicos & Tech'],
            'consent_accepted' => '1',
        ]);

        $response->assertStatus(200);
        $response->assertSee('https://chat.whatsapp.com/GrupoTechOficial123', false);
        $response->assertSee('Entrar no grupo oficial agora');
        $response->assertSee('https://api.whatsapp.com/send?phone=5511999990000&amp;text=', false);
        $response->assertSee('Carlos%20Eduardo', false);
        $response->assertSee('Falar no WhatsApp com mensagem pronta');
    }

    public function test_public_capture_does_not_expose_revoked_group_link_after_submission(): void
    {
        Group::create([
            'name' => 'Grupo Revogado',
            'category' => 'Eletrônicos & Tech',
            'invite_url' => 'https://chat.whatsapp.com/RevogadoLink999',
            'is_active' => true,
            'revoked_at' => now(),
        ]);

        $response = $this->followingRedirects()->post('/inscrever', [
            'name' => 'Ana Paula',
            'country_code' => '55',
            'phone' => '(11) 98111-2233',
            'interests' => ['Eletrônicos & Tech'],
            'consent_accepted' => '1',
        ]);

        $response->assertStatus(200);
        $response->assertDontSee('https://chat.whatsapp.com/RevogadoLink999', false);
        $response->assertDontSee('Entrar no grupo oficial agora');
        $response->assertDontSee('https://api.whatsapp.com/send', false);
    }
}

