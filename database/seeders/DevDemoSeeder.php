<?php

namespace Database\Seeders;

use App\Models\ConsentEvent;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Invitation;
use App\Models\MessageTemplate;
use App\Models\SuppressionEntry;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevDemoSeeder extends Seeder
{
    /**
     * Fictional development-only seed data. All demo contacts and invitations
     * are tagged with record_source = 'demo' so the Dashboard separates them
     * from real production and manual records.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            return;
        }

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@aguiadaspromos.local'],
            [
                'name' => 'Administrador Demo',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $operator = User::query()->firstOrCreate(
            ['email' => 'operador@aguiadaspromos.local'],
            [
                'name' => 'Operador Demo',
                'password' => Hash::make('password'),
                'role' => User::ROLE_OPERATOR,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $groupTech = Group::query()->firstOrCreate(
            ['name' => 'Águia Tech & Eletrônicos'],
            [
                'category' => 'Eletrônicos & Tech',
                'description' => 'Ofertas de smartphones, notebooks e periféricos.',
                'invite_url' => 'https://chat.whatsapp.com/ExemploGrupoTech01',
                'is_active' => true,
                'capacity_note' => 'Grupo de demonstração para ambiente local',
                'created_by' => $admin->id,
            ]
        );

        $groupCasa = Group::query()->firstOrCreate(
            ['name' => 'Águia Casa & Cozinha'],
            [
                'category' => 'Casa & Cozinha',
                'description' => 'Utilidades domésticas, eletroportáteis e casa.',
                'invite_url' => 'https://chat.whatsapp.com/ExemploGrupoCasa02',
                'is_active' => true,
                'capacity_note' => 'Grupo de demonstração para ambiente local',
                'created_by' => $admin->id,
            ]
        );

        $template = MessageTemplate::query()->where('is_default', true)->first();

        // 1. Authorized demo contact ready for invite
        $c1 = Contact::query()->firstOrCreate(
            ['phone_e164' => '+5511990000001'],
            [
                'name' => 'Mariana Demo (Autorizada)',
                'origin' => 'captacao_publica',
                'interests' => ['Eletrônicos & Tech'],
                'interests_text' => 'Eletrônicos & Tech',
                'owner_id' => $operator->id,
                'default_group_id' => $groupTech->id,
                'pipeline_stage' => Contact::STAGE_AUTORIZADO,
                'consent_status' => Contact::CONSENT_AUTORIZADO,
                'record_source' => Contact::SOURCE_DEMO,
            ]
        );
        ConsentEvent::query()->firstOrCreate(
            ['contact_id' => $c1->id, 'status' => ConsentEvent::STATUS_GRANTED],
            [
                'origin' => 'captacao_publica',
                'scope' => 'convite_grupos_aguia',
                'policy_version' => 'v1.0-2026-11',
                'evidence_reference' => 'demo_seed_form',
                'occurred_at' => now()->subHours(6),
            ]
        );

        // 2. Contact without consent (must remain blocked)
        Contact::query()->firstOrCreate(
            ['phone_e164' => '+5511990000002'],
            [
                'name' => 'Roberto Demo (Sem Autorização)',
                'origin' => 'importacao_csv',
                'interests' => ['Casa & Cozinha'],
                'interests_text' => 'Casa & Cozinha',
                'owner_id' => $operator->id,
                'default_group_id' => $groupCasa->id,
                'pipeline_stage' => Contact::STAGE_NOVO,
                'consent_status' => Contact::CONSENT_PENDENTE,
                'record_source' => Contact::SOURCE_DEMO,
            ]
        );

        // 3. Opted-out / suppressed demo contact
        $c3 = Contact::query()->firstOrCreate(
            ['phone_e164' => '+5511990000003'],
            [
                'name' => 'Juliana Demo (Descadastrada)',
                'origin' => 'captacao_publica',
                'interests' => ['Eletrônicos & Tech'],
                'interests_text' => 'Eletrônicos & Tech',
                'owner_id' => $operator->id,
                'default_group_id' => $groupTech->id,
                'pipeline_stage' => Contact::STAGE_NAO_INTERESSADO,
                'consent_status' => Contact::CONSENT_REVOGADO,
                'record_source' => Contact::SOURCE_DEMO,
            ]
        );
        SuppressionEntry::query()->firstOrCreate(
            ['phone_e164' => $c3->phone_e164],
            [
                'contact_id' => $c3->id,
                'reason' => 'sair_whatsapp',
                'source' => 'webhook',
                'retain_until' => now()->addDays(1825),
            ]
        );

        // 4. Demo contact with simulated invite record
        $c4 = Contact::query()->firstOrCreate(
            ['phone_e164' => '+5511990000004'],
            [
                'name' => 'Felipe Demo (Entrada Confirmada)',
                'origin' => 'captacao_publica',
                'interests' => ['Casa & Cozinha'],
                'interests_text' => 'Casa & Cozinha',
                'owner_id' => $operator->id,
                'default_group_id' => $groupCasa->id,
                'pipeline_stage' => Contact::STAGE_ENTRADA_CONFIRMADA,
                'consent_status' => Contact::CONSENT_AUTORIZADO,
                'last_invited_at' => now()->subDays(1),
                'joined_confirmed_at' => now()->subHours(12),
                'joined_confirmed_by' => $operator->id,
                'record_source' => Contact::SOURCE_DEMO,
            ]
        );
        ConsentEvent::query()->firstOrCreate(
            ['contact_id' => $c4->id, 'status' => ConsentEvent::STATUS_GRANTED],
            [
                'origin' => 'captacao_publica',
                'scope' => 'convite_grupos_aguia',
                'policy_version' => 'v1.0-2026-11',
                'evidence_reference' => 'demo_seed_form',
                'occurred_at' => now()->subDays(2),
            ]
        );

        if ($template !== null) {
            Invitation::query()->firstOrCreate(
                ['idempotency_key' => "demo:seed:{$c4->id}:{$groupCasa->id}"],
                [
                    'contact_id' => $c4->id,
                    'group_id' => $groupCasa->id,
                    'message_template_id' => $template->id,
                    'user_id' => $operator->id,
                    'mode' => Invitation::MODE_SIMULATED,
                    'active_dedup_key' => "{$c4->id}:{$groupCasa->id}",
                    'status' => Invitation::STATUS_SENT,
                    'status_origin' => 'simulated',
                    'record_source' => Contact::SOURCE_DEMO,
                    'rendered_message' => $template->render([
                        'nome' => $c4->name,
                        'categoria' => $groupCasa->category,
                        'grupo' => $groupCasa->name,
                        'link_grupo' => $groupCasa->invite_url,
                    ]),
                    'sent_at' => now()->subDays(1),
                ]
            );
        }
    }
}
