<?php

namespace Tests\Feature;

use App\Livewire\Contacts\Index as ContactsIndex;
use App\Livewire\Dashboard as DashboardComponent;
use App\Livewire\Invitations\Index as InvitationsIndex;
use App\Livewire\Templates\Index as TemplatesIndex;
use App\Models\AppSetting;
use App\Models\ConsentEvent;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Invitation;
use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TeamEntryAndInvitationsUxTest extends TestCase
{
    use RefreshDatabase;

    private function seedBasicData(): array
    {
        $group = Group::create([
            'name' => 'Águia Tech',
            'category' => 'Eletrônicos & Tech',
            'invite_url' => 'https://chat.whatsapp.com/AguiaTech123',
            'is_active' => true,
        ]);

        $template = MessageTemplate::create([
            'name' => 'convite_padrao',
            'display_name' => 'Convite Padrão',
            'status' => MessageTemplate::STATUS_APPROVED,
            'body_text' => 'Oi {{nome}}! Entre em {{categoria}}: {{link_grupo}}',
            'is_default' => true,
        ]);

        $altTemplate = MessageTemplate::create([
            'name' => 'convite_vip',
            'display_name' => 'Convite VIP Curto',
            'status' => MessageTemplate::STATUS_APPROVED,
            'body_text' => '{{nome}}, seu link VIP de {{categoria}}: {{link_grupo}}',
            'is_default' => false,
        ]);

        $readyContact = Contact::create([
            'name' => 'Lucas Ready',
            'phone_e164' => '+5511991112233',
            'origin' => 'captacao_publica',
            'interests' => ['Eletrônicos & Tech'],
            'interests_text' => 'Eletrônicos & Tech',
            'default_group_id' => $group->id,
            'pipeline_stage' => Contact::STAGE_AUTORIZADO,
            'consent_status' => Contact::CONSENT_AUTORIZADO,
            'record_source' => Contact::SOURCE_REAL,
        ]);

        ConsentEvent::create([
            'contact_id' => $readyContact->id,
            'status' => ConsentEvent::STATUS_GRANTED,
            'origin' => 'captacao_publica',
            'scope' => 'convite_grupos_aguia',
            'policy_version' => 'v1.0-2026-11',
            'occurred_at' => now(),
        ]);

        return [$group, $template, $altTemplate, $readyContact];
    }

    public function test_login_and_register_pages_use_portuguese_team_branding_and_light_mode(): void
    {
        $login = $this->get('/login');
        $login->assertStatus(200);
        $login->assertSee('Acesso da Equipe');
        $login->assertDontSee('Log in to your account');
        $login->assertDontSee('class="dark"', false);

        $register = $this->get('/register');
        $register->assertStatus(200);
        $register->assertSee('Cadastro da Equipe');
        $register->assertDontSee('Create an account');
        $register->assertDontSee('class="dark"', false);
    }

    public function test_dashboard_sidebar_forces_light_mode_and_shows_visible_navigation_items(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $response = $this->actingAs($operator)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Contatos &amp; Envios', false);
        $response->assertSee('Convites');
        $response->assertSee('Captação');
        $response->assertSee('Grupos');
        $response->assertSee('Mensagem Automática');
        $response->assertDontSee('window.Flux.applyAppearance', false);
    }

    public function test_invitations_page_ready_tab_can_invite_with_selected_template_and_switch_to_pending_confirm(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        [$group, $template, $altTemplate, $readyContact] = $this->seedBasicData();
        AppSetting::setValue('whatsapp_mode', 'assisted');

        $component = Livewire::actingAs($operator)
            ->test(InvitationsIndex::class)
            ->set('activeTab', 'ready')
            ->assertSee('Lucas Ready')
            ->set('selectedTemplateId', $altTemplate->id)
            ->call('inviteContact', $readyContact->id)
            ->assertDispatched('open-wa-me');

        $invitation = Invitation::query()->where('contact_id', $readyContact->id)->firstOrFail();
        $this->assertSame($altTemplate->id, $invitation->message_template_id);
        $this->assertStringContainsString('seu link VIP de', $invitation->rendered_message);

        $component->set('activeTab', 'pending_confirm')
            ->assertSee('Lucas Ready')
            ->assertSee($invitation->wa_me_url)
            ->assertSee('Confirmar envio manual');
    }

    public function test_contacts_page_supports_queue_tabs_and_template_selection(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        [$group, $template, $altTemplate, $readyContact] = $this->seedBasicData();
        AppSetting::setValue('whatsapp_mode', 'assisted');

        $otherContact = Contact::create([
            'name' => 'Sem Consentimento',
            'phone_e164' => '+5511980001122',
            'origin' => 'cadastro_manual',
            'pipeline_stage' => Contact::STAGE_NOVO,
            'consent_status' => Contact::CONSENT_PENDENTE,
            'record_source' => Contact::SOURCE_MANUAL,
        ]);

        Livewire::actingAs($operator)
            ->test(ContactsIndex::class)
            ->set('queueView', 'ready')
            ->assertSee('Lucas Ready')
            ->assertDontSee('Sem Consentimento')
            ->set('selectedTemplateId', $altTemplate->id)
            ->call('invite', $readyContact->id)
            ->set('queueView', 'pending_confirm')
            ->assertSee('Lucas Ready')
            ->assertSee('Reabrir WhatsApp');
    }

    public function test_contacts_page_separates_not_sent_and_already_sent_contacts(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        [$group, $template, $altTemplate, $readyContact] = $this->seedBasicData();
        AppSetting::setValue('whatsapp_mode', 'assisted');

        $notSentContact = Contact::create([
            'name' => 'Ainda Nao Enviado',
            'phone_e164' => '+5511970001122',
            'origin' => 'captacao_publica',
            'default_group_id' => $group->id,
            'pipeline_stage' => Contact::STAGE_AUTORIZADO,
            'consent_status' => Contact::CONSENT_AUTORIZADO,
            'record_source' => Contact::SOURCE_REAL,
        ]);

        $component = Livewire::actingAs($operator)
            ->test(ContactsIndex::class)
            ->call('invite', $readyContact->id);

        $invitation = $readyContact->invitations()->firstOrFail();

        // While only opened (assisted_opened), Lucas Ready is in pending_confirm and NOT in sent
        $component->set('queueView', 'sent')
            ->assertDontSee('Lucas Ready')
            ->assertDontSee('Ainda Nao Enviado');

        $component->set('queueView', 'not_sent')
            ->assertSee('Ainda Nao Enviado')
            ->assertDontSee('Lucas Ready');

        // After confirming manual send, Lucas Ready moves to 'sent' and leaves 'pending_confirm'
        $component->call('confirmManualSend', $invitation->id)
            ->set('queueView', 'sent')
            ->assertSee('Lucas Ready')
            ->assertDontSee('Ainda Nao Enviado');

        $component->set('queueView', 'pending_confirm')
            ->assertDontSee('Lucas Ready');
    }

    public function test_templates_page_allows_saving_default_automatic_message_directly(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        [$group, $template] = $this->seedBasicData();

        $this->actingAs($operator)
            ->get('/modelos')
            ->assertOk()
            ->assertDontSee('echo e(');

        Livewire::actingAs($operator)
            ->test(TemplatesIndex::class)
            ->set('quickBodyText', 'Oi {{nome}}! Nova mensagem automática para {{categoria}}: {{link_grupo}}')
            ->call('saveQuickDefaultMessage')
            ->assertHasNoErrors();

        $template->refresh();
        $this->assertSame('Oi {{nome}}! Nova mensagem automática para {{categoria}}: {{link_grupo}}', $template->body_text);
        $this->assertTrue($template->is_default);
    }

    public function test_contacts_quick_text_import_saves_phone_only_lines_into_list(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        Livewire::actingAs($operator)
            ->test(ContactsIndex::class)
            ->call('openImportModal')
            ->set('importRawText', "+55 11 99123-4567\n+55 21 98123-4567")
            ->call('quickImportText')
            ->assertHasNoErrors()
            ->assertSee('+5511991234567')
            ->assertSee('+5521981234567');

        $this->assertDatabaseHas('contacts', ['phone_e164' => '+5511991234567']);
        $this->assertDatabaseHas('contacts', ['phone_e164' => '+5521981234567']);
    }

    public function test_dashboard_replaces_api_metrics_with_image_and_text_import_and_direct_send_queue(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        [$group, $template] = $this->seedBasicData();
        AppSetting::setValue('whatsapp_mode', 'assisted');

        $page = $this->actingAs($operator)->get('/dashboard');
        $page->assertStatus(200)
            ->assertSee('Central de Contatos &amp; Envios', false)
            ->assertSee('Subir por Imagem (Print / Lista)')
            ->assertDontSee('MÉTRICAS REAIS (OPERAÇÃO & API OFICIAL)')
            ->assertDontSee('DADOS DE DEMONSTRAÇÃO & SIMULAÇÃO');

        $component = Livewire::actingAs($operator)
            ->test(DashboardComponent::class)
            ->call('applyOcrText', "Contato A\n+55 11 96111-2233\nContato B\n(21) 97222-3344")
            ->set('quickGroupId', $group->id)
            ->call('saveQuickContacts')
            ->assertHasNoErrors();

        $contactA = Contact::query()->where('phone_e164', '+5511961112233')->firstOrFail();
        $this->assertDatabaseHas('contacts', ['phone_e164' => '+5521972223344']);

        // Authorize pending contact directly from Dashboard and trigger WhatsApp invite
        $component->call('authorizeContact', $contactA->id)
            ->call('inviteNext', $contactA->id)
            ->assertDispatched('open-wa-me');

        $contactA->refresh();
        $this->assertSame(Contact::CONSENT_AUTORIZADO, $contactA->consent_status);
    }

    public function test_dashboard_bulk_send_invites_up_to_three_contacts_and_enforces_limit_of_three(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        [$group, $template] = $this->seedBasicData();
        AppSetting::setValue('whatsapp_mode', 'assisted');

        $extraIds = [];
        for ($i = 1; $i <= 4; $i++) {
            $contact = Contact::create([
                'name' => "Contato Massa {$i}",
                'phone_e164' => "+551199000000{$i}",
                'origin' => 'importacao_autorizada_dashboard',
                'default_group_id' => $group->id,
                'pipeline_stage' => Contact::STAGE_AUTORIZADO,
                'consent_status' => Contact::CONSENT_AUTORIZADO,
                'record_source' => Contact::SOURCE_REAL,
            ]);
            ConsentEvent::create([
                'contact_id' => $contact->id,
                'status' => ConsentEvent::STATUS_GRANTED,
                'origin' => 'importacao_autorizada_dashboard',
                'scope' => 'convite_grupos_aguia',
                'policy_version' => 'v1.0-2026-11',
                'occurred_at' => now(),
            ]);
            $extraIds[] = $contact->id;
        }

        $component = Livewire::actingAs($operator)->test(DashboardComponent::class);

        // Selecting 4 contacts must be blocked (max 3) and send zero invitations
        $component->set('selectedContactIds', $extraIds)
            ->call('bulkInviteSelected')
            ->assertSee('no máximo 3');
        $this->assertSame(0, Invitation::query()->count());

        // Clearing manual selection sends up to 3 next ready contacts automatically
        $component->set('selectedContactIds', [])
            ->call('bulkInviteSelected')
            ->assertDispatched('open-wa-me-batch')
            ->assertCount('batchWaLinks', 3);

        $this->assertSame(3, Invitation::query()->where('status', Invitation::STATUS_ASSISTED_OPENED)->count());

        // Confirming the 3 opened invitations in batch moves all 3 to confirmed sent
        $component->call('confirmBatchManualSends')
            ->assertCount('batchWaLinks', 0);

        $this->assertSame(3, Invitation::query()->where('status', Invitation::STATUS_ASSISTED_SENT_MANUAL)->count());
    }

    public function test_dashboard_and_templates_can_attach_serve_dispatch_and_remove_message_image(): void
    {
        Storage::fake('local');
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        [$group, $template] = $this->seedBasicData();
        AppSetting::setValue('whatsapp_mode', 'assisted');

        $contact = Contact::create([
            'name' => 'Contato Imagem',
            'phone_e164' => '+5511991230001',
            'origin' => 'importacao_autorizada_dashboard',
            'default_group_id' => $group->id,
            'pipeline_stage' => Contact::STAGE_AUTORIZADO,
            'consent_status' => Contact::CONSENT_AUTORIZADO,
            'record_source' => Contact::SOURCE_REAL,
        ]);
        ConsentEvent::create([
            'contact_id' => $contact->id,
            'status' => ConsentEvent::STATUS_GRANTED,
            'origin' => 'importacao_autorizada_dashboard',
            'scope' => 'convite_grupos_aguia',
            'policy_version' => 'v1.0-2026-11',
            'occurred_at' => now(),
        ]);

        // Attach image directly from Dashboard's image tab
        $image = UploadedFile::fake()->image('banner-ofertas.png', 240, 240);
        $dashboard = Livewire::actingAs($operator)
            ->test(DashboardComponent::class)
            ->set('quickTemplateImage', $image)
            ->call('saveQuickTemplateImage')
            ->assertHasNoErrors();

        $template->refresh();
        $this->assertNotNull($template->media_path);
        Storage::disk('local')->assertExists($template->media_path);

        // Authenticated media endpoint serves the image bytes
        $mediaUrl = route('templates.media', $template);
        $this->actingAs($operator)
            ->get($mediaUrl)
            ->assertOk()
            ->assertHeader('content-type', 'image/png');

        // Dispatching in assisted mode passes imageUrl for clipboard copy alongside WhatsApp text URL
        $dashboard->call('inviteNext', $contact->id)
            ->assertDispatched('open-wa-me', function (string $name, array $params) use ($mediaUrl): bool {
                return ($params['imageUrl'] ?? '') === $mediaUrl
                    && str_starts_with((string) ($params['url'] ?? ''), 'https://api.whatsapp.com/send?');
            });

        // Bulk invite also passes imageUrl and includes image_url in batchWaLinks
        $contact2 = Contact::create([
            'name' => 'Contato Imagem 2',
            'phone_e164' => '+5511991230002',
            'origin' => 'importacao_autorizada_dashboard',
            'default_group_id' => $group->id,
            'pipeline_stage' => Contact::STAGE_AUTORIZADO,
            'consent_status' => Contact::CONSENT_AUTORIZADO,
            'record_source' => Contact::SOURCE_REAL,
        ]);
        ConsentEvent::create([
            'contact_id' => $contact2->id,
            'status' => ConsentEvent::STATUS_GRANTED,
            'origin' => 'importacao_autorizada_dashboard',
            'scope' => 'convite_grupos_aguia',
            'policy_version' => 'v1.0-2026-11',
            'occurred_at' => now(),
        ]);

        $dashboard->set('selectedContactIds', [$contact2->id])
            ->call('bulkInviteSelected')
            ->assertDispatched('open-wa-me-batch', function (string $name, array $params) use ($mediaUrl): bool {
                return ($params['imageUrl'] ?? '') === $mediaUrl;
            });

        // Removing the image deletes file and clears media_path; uploading via Templates\Index works too
        $storedPath = $template->media_path;
        $dashboard->call('removeQuickTemplateImage');
        $template->refresh();
        $this->assertNull($template->media_path);
        Storage::disk('local')->assertMissing($storedPath);

        $secondImage = UploadedFile::fake()->image('template-banner.jpg', 200, 200);
        $this->actingAs($operator)
            ->get('/modelos')
            ->assertOk()
            ->assertDontSee('echo e(');

        Livewire::actingAs($operator)
            ->test(TemplatesIndex::class)
            ->set('quickBodyText', 'Olá {{nome}}! Confira as ofertas em {{grupo}}: {{link_grupo}}')
            ->set('quickImage', $secondImage)
            ->call('saveQuickDefaultMessage')
            ->assertHasNoErrors();

        $template->refresh();
        $this->assertNotNull($template->media_path);
        Storage::disk('local')->assertExists($template->media_path);
    }

    public function test_meta_cloud_transport_includes_header_image_when_template_has_media(): void
    {
        Storage::fake('local');
        config([
            'services.whatsapp.waba_id' => '12345',
            'services.whatsapp.phone_number_id' => '67890',
            'services.whatsapp.access_token' => 'secret-token',
            'services.whatsapp.app_secret' => 'app-secret',
            'services.whatsapp.verify_token' => 'verify-token',
        ]);

        [$group, $template] = $this->seedBasicData();
        $path = UploadedFile::fake()->image('oferta.png', 100, 100)->store('template-media', 'local');
        $template->update(['media_path' => $path]);

        $contact = Contact::create([
            'name' => 'Ana Meta',
            'phone_e164' => '+5511991230099',
            'origin' => 'captacao_publica',
            'default_group_id' => $group->id,
            'pipeline_stage' => Contact::STAGE_AUTORIZADO,
            'consent_status' => Contact::CONSENT_AUTORIZADO,
            'record_source' => Contact::SOURCE_REAL,
        ]);

        $invitation = Invitation::create([
            'contact_id' => $contact->id,
            'group_id' => $group->id,
            'message_template_id' => $template->id,
            'rendered_message' => 'Teste',
            'mode' => 'official_api',
            'status' => Invitation::STATUS_QUEUED,
            'idempotency_key' => 'test-img-meta-1',
        ]);

        Http::fake([
            'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.img.1']]], 200),
        ]);

        $transport = app(\App\Services\WhatsApp\MetaCloudWhatsAppTransport::class);
        $result = $transport->sendTemplateInvitation($invitation);

        $this->assertTrue($result->accepted);

        Http::assertSent(function ($request) {
            $components = data_get($request->data(), 'template.components', []);
            $header = collect($components)->firstWhere('type', 'header');

            return $header !== null
                && data_get($header, 'parameters.0.type') === 'image'
                && str_contains((string) data_get($header, 'parameters.0.image.link'), 'signature=');
        });
    }
}

