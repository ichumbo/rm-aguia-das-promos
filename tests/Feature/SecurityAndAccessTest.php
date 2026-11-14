<?php

namespace Tests\Feature;

use App\Jobs\SendInvitationJob;
use App\Models\AppSetting;
use App\Models\ConsentEvent;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Invitation;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\WhatsApp\InvitationDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecurityAndAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_cannot_access_admin_integration_settings_or_secrets_or_exports(): void
    {
        config()->set('services.whatsapp.access_token', 'EAA-TOP-SECRET-TOKEN-999');
        config()->set('services.whatsapp.app_secret', 'SECRET-APP-KEY-888');
        config()->set('services.whatsapp.verify_token', 'VERIFY-SECRET-777');

        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($operator)
            ->get('/configuracoes')
            ->assertStatus(403);

        $this->actingAs($operator)
            ->get('/contatos/exportar')
            ->assertStatus(403);

        $dashboard = $this->actingAs($operator)->get('/dashboard');
        $dashboard->assertStatus(200);
        $dashboard->assertDontSee('EAA-TOP-SECRET-TOKEN-999');
        $dashboard->assertDontSee('SECRET-APP-KEY-888');
        $dashboard->assertDontSee('VERIFY-SECRET-777');
    }

    public function test_admin_can_access_configuracoes_and_secrets_are_never_exposed_in_html(): void
    {
        config()->set('services.whatsapp.access_token', 'EAA-TOP-SECRET-TOKEN-999');
        config()->set('services.whatsapp.app_secret', 'SECRET-APP-KEY-888');
        config()->set('services.whatsapp.verify_token', 'VERIFY-SECRET-777');
        config()->set('services.whatsapp.phone_number_id', '1092837465');
        config()->set('services.whatsapp.waba_id', '5647382910');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)->get('/configuracoes');
        $response->assertStatus(200);
        $response->assertDontSee('EAA-TOP-SECRET-TOKEN-999');
        $response->assertDontSee('SECRET-APP-KEY-888');
        $response->assertDontSee('VERIFY-SECRET-777');
    }

    public function test_network_error_records_sanitized_useful_message_without_exposing_token(): void
    {
        config()->set('services.whatsapp.access_token', 'EAA-TOP-SECRET-TOKEN-999');
        config()->set('services.whatsapp.phone_number_id', '1092837465');
        config()->set('services.whatsapp.waba_id', '5647382910');
        config()->set('services.whatsapp.app_secret', 'SECRET-APP-KEY-888');
        config()->set('services.whatsapp.verify_token', 'VERIFY-SECRET-777');
        AppSetting::setValue('whatsapp_mode', 'official_api');

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => [
                    'message' => 'Invalid OAuth access token EAA-TOP-SECRET-TOKEN-999',
                    'code' => 190,
                ],
            ], 401),
        ]);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $group = Group::create([
            'name' => 'Grupo Ofertas',
            'category' => 'Ofertas',
            'invite_url' => 'https://chat.whatsapp.com/Ofertas123',
            'is_active' => true,
        ]);
        $template = MessageTemplate::create([
            'name' => 'convite_ofertas',
            'display_name' => 'Convite Ofertas',
            'status' => MessageTemplate::STATUS_APPROVED,
            'body_text' => 'Olá {{nome}}, grupo {{categoria}}: {{link_grupo}}',
        ]);
        $contact = Contact::create([
            'name' => 'Marina Souza',
            'phone_e164' => '+5511966665555',
            'origin' => 'captacao_publica',
            'pipeline_stage' => Contact::STAGE_AUTORIZADO,
            'consent_status' => Contact::CONSENT_AUTORIZADO,
        ]);
        ConsentEvent::create([
            'contact_id' => $contact->id,
            'status' => ConsentEvent::STATUS_GRANTED,
            'origin' => 'captacao_publica',
            'scope' => 'convite_grupos_aguia',
            'policy_version' => 'v1.0-2026-11',
            'occurred_at' => now(),
        ]);

        $invitation = app(InvitationDispatchService::class)->queueOfficialInvite($contact, $group, $template, $admin);
        SendInvitationJob::dispatchSync($invitation->id);

        $invitation->refresh();
        $this->assertSame(Invitation::STATUS_FAILED, $invitation->status);
        $this->assertNotNull($invitation->error_message);
        $this->assertStringContainsString('190', $invitation->error_message);
        $this->assertStringNotContainsString('EAA-TOP-SECRET-TOKEN-999', $invitation->error_message);
    }
}
