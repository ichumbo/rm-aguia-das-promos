<?php

namespace Tests\Feature;

use App\Livewire\Contacts\Index as ContactsIndex;
use App\Livewire\Groups\Index as GroupsIndex;
use App\Livewire\Templates\Index as TemplatesIndex;
use App\Models\AppSetting;
use App\Models\ConsentEvent;
use App\Models\Contact;
use App\Models\Group;
use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireCrmScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_authenticated_crm_pages_and_qr_code_render_cleanly(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get('/dashboard')->assertStatus(200)->assertSee('Central de Contatos &amp; Envios', false);
        $this->actingAs($admin)->get('/contatos')->assertStatus(200)->assertSee('Contatos & Pipeline de Convites', false);
        $this->actingAs($admin)->get('/grupos')->assertStatus(200)->assertSee('Grupos de Destino');
        $this->actingAs($admin)->get('/convites')->assertStatus(200)->assertSee('Convites, Fila & Falhas', false);
        $this->actingAs($admin)->get('/modelos')->assertStatus(200)->assertSee('Modelos de Mensagem');
        $this->actingAs($admin)->get('/captacao')->assertStatus(200)->assertSee('Captação de Interessados');
        $this->actingAs($admin)->get('/configuracoes')->assertStatus(200)->assertSee('Configurações & Integração', false);

        $qrResponse = $this->actingAs($admin)->get('/captacao/qrcode.svg');
        $qrResponse->assertStatus(200);
        $this->assertStringContainsString('<svg', $qrResponse->getContent());
    }

    public function test_operator_can_use_contacts_assisted_invite_and_drawer_and_mark_entry(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $group = Group::create([
            'name' => 'Águia Tech',
            'category' => 'Eletrônicos',
            'invite_url' => 'https://chat.whatsapp.com/AguiaTech123',
            'is_active' => true,
        ]);
        MessageTemplate::create([
            'name' => 'convite_grupo_aguia',
            'display_name' => 'Convite Padrão',
            'status' => MessageTemplate::STATUS_APPROVED,
            'body_text' => 'Olá, {{nome}}! Grupo de {{categoria}}: {{link_grupo}}',
            'is_default' => true,
        ]);
        $contact = Contact::create([
            'name' => 'Patrícia Gomes',
            'phone_e164' => '+5511993332211',
            'origin' => 'captacao_publica',
            'default_group_id' => $group->id,
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

        AppSetting::setValue('whatsapp_mode', 'assisted');

        $component = Livewire::actingAs($operator)
            ->test(ContactsIndex::class)
            ->call('invite', $contact->id)
            ->assertDispatched('open-wa-me');

        $invitation = $contact->invitations()->firstOrFail();
        $this->assertSame('assisted_opened', $invitation->status);

        $component->call('confirmManualSend', $invitation->id)
            ->call('confirmEntry', $contact->id)
            ->call('openDrawer', $contact->id)
            ->assertSee('Prova de Autorização');

        $contact->refresh();
        $this->assertSame(Contact::STAGE_ENTRADA_CONFIRMADA, $contact->pipeline_stage);
        $this->assertNotNull($contact->joined_confirmed_at);
    }

    public function test_groups_crud_and_revoked_link_replacement_work_with_audit(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        Livewire::actingAs($admin)
            ->test(GroupsIndex::class)
            ->call('openCreate')
            ->set('name', 'Águia Casa')
            ->set('category', 'Casa & Cozinha')
            ->set('invite_url', 'https://chat.whatsapp.com/OldLink123')
            ->call('save')
            ->assertHasNoErrors();

        $group = Group::query()->where('name', 'Águia Casa')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(GroupsIndex::class)
            ->call('markLinkRevoked', $group->id)
            ->call('openReplaceLink', $group->id)
            ->set('newInviteUrl', 'https://chat.whatsapp.com/NewLink456')
            ->set('replaceReason', 'Link anterior revogado')
            ->call('replaceRevokedLink')
            ->assertHasNoErrors();

        $group->refresh();
        $this->assertTrue($group->is_active);
        $this->assertNull($group->revoked_at);
        $this->assertSame('https://chat.whatsapp.com/NewLink456', $group->invite_url);
        $this->assertDatabaseHas('audit_logs', ['action' => 'group.link_replaced']);
    }

    public function test_group_can_be_deleted_and_unlinks_associated_contacts(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $group = Group::create([
            'name' => 'Grupo Temporário',
            'category' => 'Geral',
            'invite_url' => 'https://chat.whatsapp.com/TempGroup999',
            'is_active' => true,
        ]);

        $contact = Contact::create([
            'name' => 'Contato Vinculado',
            'phone_e164' => '+5511988887777',
            'origin' => 'cadastro_manual',
            'default_group_id' => $group->id,
            'pipeline_stage' => Contact::STAGE_NOVO,
            'consent_status' => Contact::CONSENT_PENDENTE,
        ]);

        Livewire::actingAs($admin)
            ->test(GroupsIndex::class)
            ->assertSee('Excluir')
            ->call('deleteGroup', $group->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('groups', ['id' => $group->id]);
        $contact->refresh();
        $this->assertNull($contact->default_group_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'group.deleted']);
    }

    public function test_templates_sync_from_meta_updates_template_status(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        config()->set('services.whatsapp.access_token', 'token-sync-test');
        config()->set('services.whatsapp.phone_number_id', '12345');
        config()->set('services.whatsapp.waba_id', '67890');
        config()->set('services.whatsapp.app_secret', 'secret');
        config()->set('services.whatsapp.verify_token', 'verify');

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'data' => [[
                    'id' => 'tpl_999',
                    'name' => 'convite_grupo_aguia',
                    'status' => 'PAUSED',
                    'category' => 'MARKETING',
                    'language' => 'pt_BR',
                    'components' => [[
                        'type' => 'BODY',
                        'text' => 'Olá, {{nome}}! Ofertas de {{categoria}}: {{link_grupo}}',
                    ]],
                ]],
            ], 200),
        ]);

        Livewire::actingAs($admin)
            ->test(TemplatesIndex::class)
            ->call('syncMeta')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('message_templates', [
            'name' => 'convite_grupo_aguia',
            'status' => 'PAUSED',
            'meta_template_id' => 'tpl_999',
        ]);
    }

    public function test_admin_can_export_contacts_csv_with_audit_log(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Contact::create([
            'name' => 'Export Teste',
            'phone_e164' => '+5511990001122',
            'origin' => 'captacao_publica',
        ]);

        $response = $this->actingAs($admin)->get('/contatos/exportar');
        $response->assertStatus(200);
        $content = $response->streamedContent();
        $this->assertStringContainsString('+5511990001122', $content);
        $this->assertDatabaseHas('audit_logs', ['action' => 'contacts.exported']);
    }
}
