<?php

declare(strict_types=1);

namespace MosChat\Services;

use MosChat\Core\Database;

final class SiteService
{
    public static function createSite(int $companyId, string $name, ?string $domain = null, array $widget = []): array
    {
        return self::create($companyId, $name, $domain, $widget);
    }

    public static function create(int $companyId, string $name, ?string $domain = null, array $widget = []): array
    {
        FeatureGate::assertWithinLimit(
            'sites',
            (int) (Database::fetch('SELECT COUNT(*) AS c FROM sites WHERE company_id = ?', [$companyId])['c'] ?? 0),
            $companyId
        );

        $publicKey = 'pk_' . random_token(16);
        $secret = 'sk_live_' . random_token(24);
        $prefix = substr($secret, 0, 12);

        $siteId = Database::insert('sites', [
            'company_id' => $companyId,
            'name' => $name,
            'domain' => $domain,
            'public_key' => $publicKey,
            'secret_key_hash' => password_hash($secret, PASSWORD_DEFAULT),
            'secret_key_prefix' => $prefix,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $displayName = (string) ($widget['company_display_name'] ?? $name);
        $color = (string) ($widget['primary_color'] ?? '#2563EB');
        $welcome = (string) ($widget['welcome_message'] ?? 'Здравствуйте! Чем можем помочь?');
        $offline = (string) ($widget['offline_message'] ?? 'Сейчас мы офлайн. Оставьте сообщение — ответим позже.');
        $position = (string) ($widget['position'] ?? 'bottom-right');

        Database::insert('site_widget_settings', [
            'site_id' => $siteId,
            'primary_color' => $color,
            'position' => in_array($position, ['bottom-right', 'bottom-left'], true) ? $position : 'bottom-right',
            'launcher_style' => 'circle',
            'company_display_name' => $displayName,
            'welcome_message' => $welcome,
            'offline_message' => $offline,
            'collect_name' => 1,
            'collect_email' => 1,
            'collect_phone' => 0,
            'show_departments' => 0,
            'privacy_consent_required' => 1,
            'privacy_text' => 'Отправляя сообщение, вы соглашаетесь на обработку персональных данных.',
            'show_branding' => FeatureGate::can('remove_branding', $companyId) ? 0 : 1,
            'updated_at' => now(),
        ]);

        AuditLog::log('site.created', 'site', $siteId);

        $site = Database::fetch('SELECT * FROM sites WHERE id = ?', [$siteId]) ?? [];
        $site['secret_key_once'] = $secret;
        return $site;
    }

    public static function findByPublicKey(string $publicKey): ?array
    {
        return Database::fetch(
            'SELECT s.*, c.id AS company_id_ref
             FROM sites s
             JOIN companies c ON c.id = s.company_id
             WHERE s.public_key = ? AND s.is_active = 1',
            [$publicKey]
        );
    }

    public static function widgetSettings(int $siteId): ?array
    {
        return Database::fetch('SELECT * FROM site_widget_settings WHERE site_id = ?', [$siteId]);
    }
}
