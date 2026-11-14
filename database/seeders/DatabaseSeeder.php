<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        AppSetting::query()->firstOrCreate(['key' => 'whatsapp_mode'], ['value' => 'assisted']);
        AppSetting::query()->firstOrCreate(['key' => 'queue_paused'], ['value' => '0']);
        AppSetting::query()->firstOrCreate(['key' => 'consent_policy_version'], ['value' => config('aguia.default_policy_version')]);
        AppSetting::query()->firstOrCreate(['key' => 'retention_days_contacts'], ['value' => '365']);
        AppSetting::query()->firstOrCreate(['key' => 'retention_days_suppression'], ['value' => '1825']);

        MessageTemplate::query()->firstOrCreate(
            ['name' => 'convite_grupo_aguia', 'language' => 'pt_BR'],
            [
                'display_name' => 'Convite Padrão Águia das Promos',
                'category' => 'MARKETING',
                'status' => MessageTemplate::STATUS_APPROVED,
                'body_text' => 'Olá, {{nome}}! Aqui é o Águia das Promos. Seu convite para o grupo de {{categoria}} está aqui: {{link_grupo}}. A participação é gratuita. Se não quiser receber novas mensagens, responda SAIR.',
                'variables' => ['nome', 'categoria', 'link_grupo'],
                'is_default' => true,
            ]
        );

        if (app()->environment(['local', 'development'])) {
            $this->call(DevDemoSeeder::class);
        }
    }
}
