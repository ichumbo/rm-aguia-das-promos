<?php

namespace App\Services\WhatsApp;

use App\Models\AppSetting;

class WhatsAppConfigStatus
{
    public const MODE_ASSISTED = 'assisted';
    public const MODE_OFFICIAL_API = 'official_api';
    public const MODE_SIMULATED = 'simulated';

    public function currentMode(): string
    {
        $mode = (string) AppSetting::getValue('whatsapp_mode', self::MODE_ASSISTED);

        return in_array($mode, [self::MODE_ASSISTED, self::MODE_OFFICIAL_API, self::MODE_SIMULATED], true)
            ? $mode
            : self::MODE_ASSISTED;
    }

    public function isConfigured(): bool
    {
        return $this->missingCredentials() === [];
    }

    /**
     * Returns only labels/env names of missing credentials (never exposes actual secrets).
     *
     * @return list<array{env: string, label: string}>
     */
    public function missingCredentials(): array
    {
        $required = [
            ['config' => 'services.whatsapp.waba_id', 'env' => 'WHATSAPP_WABA_ID', 'label' => 'WABA ID (WhatsApp Business Account)'],
            ['config' => 'services.whatsapp.phone_number_id', 'env' => 'WHATSAPP_PHONE_NUMBER_ID', 'label' => 'Phone Number ID'],
            ['config' => 'services.whatsapp.access_token', 'env' => 'WHATSAPP_ACCESS_TOKEN', 'label' => 'Token de produção (System User)'],
            ['config' => 'services.whatsapp.app_secret', 'env' => 'WHATSAPP_APP_SECRET', 'label' => 'App Secret (assinatura de webhook)'],
            ['config' => 'services.whatsapp.verify_token', 'env' => 'WHATSAPP_VERIFY_TOKEN', 'label' => 'Verify Token do webhook'],
        ];

        $missing = [];
        foreach ($required as $item) {
            $value = (string) config($item['config'], '');
            if (trim($value) === '') {
                $missing[] = [
                    'env' => $item['env'],
                    'label' => $item['label'],
                ];
            }
        }

        return $missing;
    }

    /**
     * Returns safe boolean status per credential without exposing values.
     *
     * @return list<array{env: string, label: string, configured: bool}>
     */
    public function checklist(): array
    {
        $items = [
            ['config' => 'services.whatsapp.business_portfolio_id', 'env' => 'WHATSAPP_BUSINESS_PORTFOLIO_ID', 'label' => 'Meta Business Portfolio ID (opcional no runtime, recomendado para auditoria)'],
            ['config' => 'services.whatsapp.waba_id', 'env' => 'WHATSAPP_WABA_ID', 'label' => 'WABA ID'],
            ['config' => 'services.whatsapp.phone_number_id', 'env' => 'WHATSAPP_PHONE_NUMBER_ID', 'label' => 'Phone Number ID'],
            ['config' => 'services.whatsapp.access_token', 'env' => 'WHATSAPP_ACCESS_TOKEN', 'label' => 'Access Token (System User)'],
            ['config' => 'services.whatsapp.app_secret', 'env' => 'WHATSAPP_APP_SECRET', 'label' => 'App Secret'],
            ['config' => 'services.whatsapp.verify_token', 'env' => 'WHATSAPP_VERIFY_TOKEN', 'label' => 'Webhook Verify Token'],
        ];

        return array_map(static fn (array $item): array => [
            'env' => $item['env'],
            'label' => $item['label'],
            'configured' => trim((string) config($item['config'], '')) !== '',
        ], $items);
    }

    public function sanitizeError(string $message): string
    {
        $secrets = array_filter([
            (string) config('services.whatsapp.access_token', ''),
            (string) config('services.whatsapp.app_secret', ''),
            (string) config('services.whatsapp.verify_token', ''),
        ], static fn (string $v): bool => trim($v) !== '');

        $sanitized = $message;
        foreach ($secrets as $secret) {
            $sanitized = str_replace($secret, '[REDACTED]', $sanitized);
        }

        return mb_substr($sanitized, 0, 1000);
    }
}
