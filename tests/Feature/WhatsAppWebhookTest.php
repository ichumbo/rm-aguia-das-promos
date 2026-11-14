<?php

namespace Tests\Feature;

use App\Models\ConsentEvent;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Invitation;
use App\Models\MessageEvent;
use App\Models\MessageTemplate;
use App\Models\SuppressionEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function signPayload(string $rawBody, string $secret = 'app-secret-test'): string
    {
        return 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
    }

    private function configureWebhookSecrets(): void
    {
        config()->set('services.whatsapp.app_secret', 'app-secret-test');
        config()->set('services.whatsapp.verify_token', 'verify-token-test');
    }

    public function test_webhook_challenge_verifies_token(): void
    {
        $this->configureWebhookSecrets();

        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=verify-token-test&hub_challenge=challenge-999')
            ->assertStatus(200)
            ->assertSeeText('challenge-999');

        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong-token&hub_challenge=challenge-999')
            ->assertStatus(403);
    }

    public function test_invalid_webhook_signature_is_rejected(): void
    {
        $this->configureWebhookSecrets();

        $payload = json_encode(['object' => 'whatsapp_business_account', 'entry' => []], JSON_THROW_ON_ERROR);

        $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalidsignature000',
        ], $payload)->assertStatus(403);

        $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(403);

        $this->assertSame(0, MessageEvent::count());
    }

    public function test_repeated_webhook_does_not_duplicate_event_and_out_of_order_does_not_regress_status(): void
    {
        $this->configureWebhookSecrets();

        $group = Group::create([
            'name' => 'Grupo Tech',
            'category' => 'Tech',
            'invite_url' => 'https://chat.whatsapp.com/Tech123456',
            'is_active' => true,
        ]);
        $template = MessageTemplate::create([
            'name' => 'convite_tech',
            'display_name' => 'Convite Tech',
            'status' => MessageTemplate::STATUS_APPROVED,
            'body_text' => 'Olá {{nome}} {{categoria}} {{link_grupo}}',
        ]);
        $contact = Contact::create([
            'name' => 'Lucas Teste',
            'phone_e164' => '+5511991112222',
            'origin' => 'captacao_publica',
            'pipeline_stage' => Contact::STAGE_CONVITE_ENVIADO,
            'consent_status' => Contact::CONSENT_AUTORIZADO,
        ]);
        $invitation = Invitation::create([
            'contact_id' => $contact->id,
            'group_id' => $group->id,
            'message_template_id' => $template->id,
            'mode' => Invitation::MODE_OFFICIAL_API,
            'idempotency_key' => 'test-idemp-1',
            'provider_message_id' => 'wamid.HBgMNTUxMTk5MTExMjIyMhUC',
            'status' => Invitation::STATUS_SENT,
            'rendered_message' => 'msg',
            'sent_at' => now(),
        ]);

        // First webhook arrives OUT OF ORDER: 'read' before 'delivered'
        $readPayload = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.HBgMNTUxMTk5MTExMjIyMhUC',
                            'status' => 'read',
                            'timestamp' => '1763100200',
                        ]],
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $this->signPayload($readPayload),
        ], $readPayload)->assertStatus(200);

        // Repeat exact same 'read' webhook -> must deduplicate
        $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $this->signPayload($readPayload),
        ], $readPayload)->assertStatus(200);

        $this->assertSame(1, MessageEvent::count());

        $invitation->refresh();
        $this->assertSame(Invitation::STATUS_READ, $invitation->status);

        // Reading message must NEVER auto-confirm group entry
        $contact->refresh();
        $this->assertNotSame(Contact::STAGE_ENTRADA_CONFIRMADA, $contact->pipeline_stage);

        // Delayed 'delivered' webhook arrives AFTER 'read' -> logs event, does NOT regress status from 'read'
        $deliveredPayload = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.HBgMNTUxMTk5MTExMjIyMhUC',
                            'status' => 'delivered',
                            'timestamp' => '1763100100',
                        ]],
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $this->signPayload($deliveredPayload),
        ], $deliveredPayload)->assertStatus(200);

        $this->assertSame(2, MessageEvent::count());
        $invitation->refresh();
        $this->assertSame(Invitation::STATUS_READ, $invitation->status);
        $this->assertNotNull($invitation->delivered_at);
    }

    public function test_opt_out_keyword_via_webhook_suppresses_contact_and_cancels_queued_invitations(): void
    {
        $this->configureWebhookSecrets();

        $group1 = Group::create([
            'name' => 'Grupo 1',
            'category' => 'Tech',
            'invite_url' => 'https://chat.whatsapp.com/G1',
            'is_active' => true,
        ]);
        $group2 = Group::create([
            'name' => 'Grupo 2',
            'category' => 'Casa',
            'invite_url' => 'https://chat.whatsapp.com/G2',
            'is_active' => true,
        ]);
        $contact = Contact::create([
            'name' => 'Fernanda Lima',
            'phone_e164' => '+5511988887777',
            'origin' => 'captacao_publica',
            'pipeline_stage' => Contact::STAGE_CONVITE_ENVIADO,
            'consent_status' => Contact::CONSENT_AUTORIZADO,
        ]);
        ConsentEvent::create([
            'contact_id' => $contact->id,
            'status' => ConsentEvent::STATUS_GRANTED,
            'origin' => 'captacao_publica',
            'scope' => 'convite_grupos_aguia',
            'policy_version' => 'v1.0-2026-11',
            'occurred_at' => now()->subDay(),
        ]);

        $queuedInvite = Invitation::create([
            'contact_id' => $contact->id,
            'group_id' => $group2->id,
            'mode' => Invitation::MODE_OFFICIAL_API,
            'idempotency_key' => 'queued-cancel-1',
            'active_dedup_key' => "{$contact->id}:{$group2->id}",
            'status' => Invitation::STATUS_QUEUED,
            'rendered_message' => 'msg pendente',
            'queued_at' => now(),
        ]);

        $inboundPayload = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '5511988887777',
                            'id' => 'wamid.inbound.sair.1',
                            'timestamp' => '1763100500',
                            'type' => 'text',
                            'text' => ['body' => '  SAIR  '],
                        ]],
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $this->signPayload($inboundPayload),
        ], $inboundPayload)->assertStatus(200);

        $contact->refresh();
        $this->assertSame(Contact::CONSENT_REVOGADO, $contact->consent_status);
        $this->assertSame(Contact::STAGE_NAO_INTERESSADO, $contact->pipeline_stage);

        $this->assertDatabaseHas('suppression_entries', [
            'phone_e164' => '+5511988887777',
            'source' => 'webhook',
        ]);
        $this->assertDatabaseHas('consent_events', [
            'contact_id' => $contact->id,
            'status' => ConsentEvent::STATUS_REVOKED,
        ]);

        $queuedInvite->refresh();
        $this->assertSame(Invitation::STATUS_CANCELLED, $queuedInvite->status);
        $this->assertNotNull($queuedInvite->cancelled_at);
        $this->assertNull($queuedInvite->active_dedup_key);
    }
}
