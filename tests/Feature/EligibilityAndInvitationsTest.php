<?php

namespace Tests\Feature;

use App\Exceptions\DomainRuleException;
use App\Jobs\SendInvitationJob;
use App\Models\AppSetting;
use App\Models\ConsentEvent;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Invitation;
use App\Models\MessageTemplate;
use App\Models\SuppressionEntry;
use App\Models\User;
use App\Services\WhatsApp\InvitationDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EligibilityAndInvitationsTest extends TestCase
{
    use RefreshDatabase;

    private function createAuthorizedContact(array $overrides = []): Contact
    {
        $contact = Contact::create(array_merge([
            'name' => 'João Mendes',
            'phone_e164' => '+5511998887766',
            'origin' => 'captacao_publica',
            'interests' => ['Eletrônicos'],
            'interests_text' => 'Eletrônicos',
            'pipeline_stage' => Contact::STAGE_AUTORIZADO,
            'consent_status' => Contact::CONSENT_AUTORIZADO,
            'record_source' => Contact::SOURCE_REAL,
        ], $overrides));

        if ($contact->consent_status === Contact::CONSENT_AUTORIZADO) {
            ConsentEvent::create([
                'contact_id' => $contact->id,
                'status' => ConsentEvent::STATUS_GRANTED,
                'origin' => 'captacao_publica',
                'scope' => 'convite_grupos_aguia',
                'policy_version' => 'v1.0-2026-11',
                'evidence_reference' => 'form_publico_checkbox',
                'occurred_at' => now(),
            ]);
        }

        return $contact;
    }

    private function createActiveGroup(array $overrides = []): Group
    {
        return Group::create(array_merge([
            'name' => 'Águia Tech Ofertas',
            'category' => 'Eletrônicos & Promoções',
            'description' => 'Ofertas de tecnologia',
            'invite_url' => 'https://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrStUv',
            'is_active' => true,
        ], $overrides));
    }

    private function createTemplate(array $overrides = []): MessageTemplate
    {
        return MessageTemplate::create(array_merge([
            'name' => 'convite_grupo_aguia',
            'display_name' => 'Convite Padrão Águia',
            'category' => 'MARKETING',
            'language' => 'pt_BR',
            'status' => MessageTemplate::STATUS_APPROVED,
            'body_text' => 'Olá, {{nome}}! Aqui é o Águia das Promos. Seu convite para o grupo de {{categoria}} está aqui: {{link_grupo}}. A participação é gratuita. Se não quiser receber novas mensagens, responda SAIR.',
            'variables' => ['nome', 'categoria', 'link_grupo'],
            'is_default' => true,
        ], $overrides));
    }

    public function test_unauthorized_contact_is_blocked_from_assisted_and_official_invites(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $group = $this->createActiveGroup();
        $template = $this->createTemplate();
        $contact = Contact::create([
            'name' => 'Sem Autorização',
            'phone_e164' => '+5511977776666',
            'origin' => 'importacao_csv',
            'pipeline_stage' => Contact::STAGE_NOVO,
            'consent_status' => Contact::CONSENT_PENDENTE,
            'record_source' => Contact::SOURCE_REAL,
        ]);

        $service = app(InvitationDispatchService::class);

        try {
            $service->openAssistedInvite($contact, $group, $template, $user);
            $this->fail('Expected DomainRuleException for unauthorized contact in assisted mode.');
        } catch (DomainRuleException $e) {
            $this->assertStringContainsString('autorização', mb_strtolower($e->getMessage()));
        }

        config()->set('services.whatsapp.access_token', 'test-token');
        config()->set('services.whatsapp.phone_number_id', '123456789');
        config()->set('services.whatsapp.waba_id', '987654321');
        config()->set('services.whatsapp.app_secret', 'secret-123');
        config()->set('services.whatsapp.verify_token', 'verify-123');

        try {
            $service->queueOfficialInvite($contact, $group, $template, $user);
            $this->fail('Expected DomainRuleException for unauthorized contact in official mode.');
        } catch (DomainRuleException $e) {
            $this->assertStringContainsString('autorização', mb_strtolower($e->getMessage()));
        }

        $this->assertSame(0, Invitation::count());
    }

    public function test_assisted_wa_me_preserves_accents_and_group_link_and_opening_does_not_mark_sent(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $group = $this->createActiveGroup();
        $template = $this->createTemplate();
        $contact = $this->createAuthorizedContact(['name' => 'José Conceição']);

        $service = app(InvitationDispatchService::class);
        $invitation = $service->openAssistedInvite($contact, $group, $template, $user);

        $this->assertSame(Invitation::MODE_ASSISTED, $invitation->mode);
        $this->assertSame(Invitation::STATUS_ASSISTED_OPENED, $invitation->status);
        $this->assertNull($invitation->sent_at);

        // Contact stage must NOT advance to convite_enviado just by opening WhatsApp
        $contact->refresh();
        $this->assertSame(Contact::STAGE_AUTORIZADO, $contact->pipeline_stage);

        $this->assertStringStartsWith('https://wa.me/5511998887766?text=', $invitation->wa_me_url);

        $query = parse_url($invitation->wa_me_url, PHP_URL_QUERY);
        parse_str((string) $query, $params);

        $this->assertArrayHasKey('text', $params);
        $this->assertStringContainsString('Olá, José Conceição!', $params['text']);
        $this->assertStringContainsString('Eletrônicos & Promoções', $params['text']);
        $this->assertStringContainsString($group->invite_url, $params['text']);
        $this->assertStringContainsString('participação é gratuita', $params['text']);

        // Confirm manual send explicitly
        $confirmed = $service->confirmManualSend($invitation, $user);
        $this->assertSame(Invitation::STATUS_ASSISTED_SENT_MANUAL, $confirmed->status);
        $this->assertSame('manual', $confirmed->status_origin);
        $this->assertNotNull($confirmed->sent_at);
        $contact->refresh();
        $this->assertSame(Contact::STAGE_CONVITE_ENVIADO, $contact->pipeline_stage);
    }

    public function test_double_click_creates_only_one_invitation_operation(): void
    {
        Queue::fake();

        config()->set('services.whatsapp.access_token', 'test-token');
        config()->set('services.whatsapp.phone_number_id', '123456789');
        config()->set('services.whatsapp.waba_id', '987654321');
        config()->set('services.whatsapp.app_secret', 'secret-123');
        config()->set('services.whatsapp.verify_token', 'verify-123');
        AppSetting::setValue('whatsapp_mode', 'official_api');

        $user = User::factory()->create(['role' => 'operator']);
        $group = $this->createActiveGroup();
        $template = $this->createTemplate();
        $contact = $this->createAuthorizedContact();

        $service = app(InvitationDispatchService::class);

        $first = $service->queueOfficialInvite($contact, $group, $template, $user);
        $second = $service->queueOfficialInvite($contact, $group, $template, $user);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Invitation::count());
        Queue::assertPushed(SendInvitationJob::class, 1);
    }

    public function test_rejected_or_paused_template_blocks_sending(): void
    {
        config()->set('services.whatsapp.access_token', 'test-token');
        config()->set('services.whatsapp.phone_number_id', '123456789');
        config()->set('services.whatsapp.waba_id', '987654321');
        config()->set('services.whatsapp.app_secret', 'secret-123');
        config()->set('services.whatsapp.verify_token', 'verify-123');

        $user = User::factory()->create(['role' => 'operator']);
        $group = $this->createActiveGroup();
        $rejectedTemplate = $this->createTemplate(['status' => MessageTemplate::STATUS_REJECTED]);
        $contact = $this->createAuthorizedContact();

        $service = app(InvitationDispatchService::class);

        $this->expectException(DomainRuleException::class);
        $service->queueOfficialInvite($contact, $group, $rejectedTemplate, $user);
    }

    public function test_api_timeout_marks_invitation_uncertain_and_does_not_blindly_retry(): void
    {
        config()->set('services.whatsapp.access_token', 'super-secret-token-value');
        config()->set('services.whatsapp.phone_number_id', '123456789');
        config()->set('services.whatsapp.waba_id', '987654321');
        config()->set('services.whatsapp.app_secret', 'secret-123');
        config()->set('services.whatsapp.verify_token', 'verify-123');
        AppSetting::setValue('whatsapp_mode', 'official_api');

        Http::fake([
            'graph.facebook.com/*' => static function (): void {
                throw new ConnectionException('cURL error 28: Operation timed out after 10000 milliseconds');
            },
        ]);

        $user = User::factory()->create(['role' => 'operator']);
        $group = $this->createActiveGroup();
        $template = $this->createTemplate();
        $contact = $this->createAuthorizedContact();

        $service = app(InvitationDispatchService::class);
        $invitation = $service->queueOfficialInvite($contact, $group, $template, $user);

        // Execute worker job synchronously
        SendInvitationJob::dispatchSync($invitation->id);

        $invitation->refresh();
        $this->assertSame(Invitation::STATUS_UNCERTAIN, $invitation->status);
        $this->assertNotNull($invitation->uncertain_at);
        $this->assertStringNotContainsString('super-secret-token-value', (string) $invitation->error_message);

        // Running the job a second time must NOT re-send an uncertain invitation
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.should.not.run']]], 200),
        ]);

        SendInvitationJob::dispatchSync($invitation->id);
        $invitation->refresh();
        $this->assertSame(Invitation::STATUS_UNCERTAIN, $invitation->status);
        $this->assertNull($invitation->provider_message_id);
    }

    public function test_worker_cancels_queued_invitation_if_contact_was_suppressed_before_execution(): void
    {
        Queue::fake();

        config()->set('services.whatsapp.access_token', 'test-token');
        config()->set('services.whatsapp.phone_number_id', '123456789');
        config()->set('services.whatsapp.waba_id', '987654321');
        config()->set('services.whatsapp.app_secret', 'secret-123');
        config()->set('services.whatsapp.verify_token', 'verify-123');
        AppSetting::setValue('whatsapp_mode', 'official_api');

        $user = User::factory()->create(['role' => 'operator']);
        $group = $this->createActiveGroup();
        $template = $this->createTemplate();
        $contact = $this->createAuthorizedContact();

        $service = app(InvitationDispatchService::class);
        $invitation = $service->queueOfficialInvite($contact, $group, $template, $user);

        // Contact opts out while job is waiting in queue
        SuppressionEntry::create([
            'contact_id' => $contact->id,
            'phone_e164' => $contact->phone_e164,
            'reason' => 'sair_whatsapp',
            'source' => 'webhook',
        ]);
        $contact->update(['consent_status' => Contact::CONSENT_REVOGADO]);

        Http::fake();

        (new SendInvitationJob($invitation->id))->handle(
            app(\App\Services\EligibilityService::class),
            app(\App\Services\WhatsApp\WhatsAppTransportResolver::class)
        );

        Http::assertNothingSent();
        $invitation->refresh();
        $this->assertSame(Invitation::STATUS_CANCELLED, $invitation->status);
    }
}
