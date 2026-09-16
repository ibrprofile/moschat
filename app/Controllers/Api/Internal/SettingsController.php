<?php

declare(strict_types=1);

namespace MosChat\Controllers\Api\Internal;

use MosChat\Core\Auth;
use MosChat\Core\Database;
use MosChat\Core\Permission;
use MosChat\Core\Request;
use MosChat\Services\ApiTokenService;
use MosChat\Services\AuditLog;
use MosChat\Services\FeatureGate;
use MosChat\Services\InviteService;

final class SettingsController
{
    public function general(Request $request): void
    {
        Permission::require('settings.manage');
        $companyId = (int) Auth::companyId();
        Database::update('companies', [
            'name' => trim((string) $request->input('name', '')),
            'website' => trim((string) $request->input('website', '')),
            'timezone' => trim((string) $request->input('timezone', 'UTC')),
            'updated_at' => now(),
        ], 'id = ?', [$companyId]);
        AuditLog::log('settings.general', 'company', $companyId);
        json_ok(['ok' => true]);
    }

    public function chat(Request $request): void
    {
        Permission::require('settings.manage');
        $companyId = (int) Auth::companyId();
        $key = 'chat.auto_assign_on_reply';
        $value = json_encode(['value' => (int) $request->input('auto_assign_on_reply', 1)]);
        $exists = Database::fetch('SELECT id FROM company_settings WHERE company_id = ? AND `key` = ?', [$companyId, $key]);
        if ($exists) {
            Database::update('company_settings', ['value' => $value], 'id = ?', [(int) $exists['id']]);
        } else {
            Database::insert('company_settings', [
                'company_id' => $companyId,
                'key' => $key,
                'value' => $value,
            ]);
        }
        json_ok(['ok' => true]);
    }

    public function widget(Request $request): void
    {
        Permission::require('settings.manage');
        $companyId = (int) Auth::companyId();
        $site = Database::fetch('SELECT * FROM sites WHERE company_id = ? ORDER BY id ASC LIMIT 1', [$companyId]);
        if (!$site) {
            json_error('NOT_FOUND', 'Сайт не найден', 404);
        }
        Database::update('site_widget_settings', [
            'company_display_name' => (string) $request->input('company_display_name', ''),
            'welcome_message' => (string) $request->input('welcome_message', ''),
            'offline_message' => (string) $request->input('offline_message', ''),
            'primary_color' => (string) $request->input('primary_color', '#2563EB'),
            'position' => (string) $request->input('position', 'bottom-right'),
            'collect_name' => (int) $request->input('collect_name', 0),
            'collect_email' => (int) $request->input('collect_email', 0),
            'collect_phone' => (int) $request->input('collect_phone', 0),
            'privacy_consent_required' => (int) $request->input('privacy_consent_required', 0),
            'updated_at' => now(),
        ], 'site_id = ?', [(int) $site['id']]);
        AuditLog::log('settings.widget', 'site', (int) $site['id']);
        json_ok(['ok' => true]);
    }

    public function privacy(Request $request): void
    {
        Permission::require('settings.manage');
        $companyId = (int) Auth::companyId();
        $site = Database::fetch('SELECT id FROM sites WHERE company_id = ? ORDER BY id ASC LIMIT 1', [$companyId]);
        if ($site) {
            Database::update('site_widget_settings', [
                'privacy_text' => (string) $request->input('privacy_text', ''),
                'updated_at' => now(),
            ], 'site_id = ?', [(int) $site['id']]);
        }
        json_ok(['ok' => true]);
    }

    public function password(Request $request): void
    {
        $user = Auth::user();
        $current = (string) $request->input('current_password', '');
        $password = (string) $request->input('password', '');
        $confirm = (string) $request->input('password_confirmation', '');
        if (!$user || !password_verify($current, $user['password_hash'])) {
            json_error('VALIDATION_ERROR', 'Неверный текущий пароль', 422);
        }
        if (strlen($password) < 8 || $password !== $confirm) {
            json_error('VALIDATION_ERROR', 'Новый пароль некорректен', 422);
        }
        Database::update('users', [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'updated_at' => now(),
        ], 'id = ?', [(int) $user['id']]);
        json_ok(['ok' => true]);
    }

    public function plan(Request $request): void
    {
        $this->billingPlan($request);
    }

    public function billingPlan(Request $request): void
    {
        Permission::require('billing.manage');
        $companyId = (int) Auth::companyId();
        $key = (string) $request->input('plan', 'free');
        if (!in_array($key, ['free', 'pro'], true)) {
            json_error('VALIDATION_ERROR', 'Неизвестный тариф', 422);
        }
        $plan = Database::fetch('SELECT id FROM plans WHERE `key` = ?', [$key]);
        if (!$plan) {
            json_error('NOT_FOUND', 'Тариф не найден', 404);
        }
        $sub = Database::fetch('SELECT id FROM subscriptions WHERE company_id = ? ORDER BY id DESC LIMIT 1', [$companyId]);
        if ($sub) {
            Database::update('subscriptions', [
                'plan_id' => (int) $plan['id'],
                'status' => 'active',
                'updated_at' => now(),
            ], 'id = ?', [(int) $sub['id']]);
        } else {
            Database::insert('subscriptions', [
                'company_id' => $companyId,
                'plan_id' => (int) $plan['id'],
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        AuditLog::log('subscription.changed', 'subscription', null, ['plan' => $key]);
        json_ok(['plan' => $key]);
    }

    public function createToken(Request $request): void
    {
        Permission::require('api.manage');
        try {
            $result = ApiTokenService::create((int) Auth::companyId(), (string) $request->input('name', 'API'));
            json_ok(['token' => $result['plaintext'], 'meta' => $result['token']], [], 201);
        } catch (\Throwable $e) {
            json_error('PLAN_LIMIT', $e->getMessage(), 402);
        }
    }

    public function createWebhook(Request $request): void
    {
        Permission::require('api.manage');
        FeatureGate::assertCan('webhooks');
        $companyId = (int) Auth::companyId();
        $url = trim((string) $request->input('url', ''));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            json_error('VALIDATION_ERROR', 'Некорректный URL', 422);
        }
        $secret = 'whsec_' . bin2hex(random_bytes(16));
        $id = Database::insert('webhooks', [
            'company_id' => $companyId,
            'url' => $url,
            'secret_hash' => password_hash($secret, PASSWORD_DEFAULT),
            'secret_prefix' => substr($secret, 0, 12),
            'events' => json_encode($request->input('events') ?: ['message.created'], JSON_UNESCAPED_UNICODE),
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        AuditLog::log('webhook.created', 'webhook', $id);
        json_ok(['id' => $id, 'secret' => $secret], [], 201);
    }
}
