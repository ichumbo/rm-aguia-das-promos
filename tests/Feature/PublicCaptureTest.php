<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\ConsentEvent;
use App\Models\Contact;
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
}
