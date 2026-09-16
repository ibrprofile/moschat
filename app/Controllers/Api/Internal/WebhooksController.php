<?php

declare(strict_types=1);

namespace MosChat\Controllers\Api\Internal;

use MosChat\Core\Auth;
use MosChat\Core\Database;
use MosChat\Core\Permission;
use MosChat\Core\Request;
use MosChat\Services\AuditLog;
use MosChat\Services\FeatureGate;

final class WebhooksController
{
    public function index(Request $request): void
    {
        Permission::require('api.manage');
        FeatureGate::assertCan('webhooks');
        $companyId = (int) Auth::companyId();

        $items = Database::fetchAll(
            'SELECT id, company_id, url, secret_prefix, events, is_active, created_at, updated_at
             FROM webhooks WHERE company_id = ? ORDER BY id DESC',
            [$companyId]
        );
        foreach ($items as &$item) {
            $item['events'] = json_decode((string) $item['events'], true) ?: [];
        }
        unset($item);

        json_ok($items);
    }

    public function store(Request $request): void
    {
        Permission::require('api.manage');
        FeatureGate::assertCan('webhooks');
        $companyId = (int) Auth::companyId();

        $url = trim((string) $request->input('url', ''));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            json_error('VALIDATION_ERROR', 'Укажите корректный URL', 422);
        }

        $events = $request->input('events', ['*']);
        if (!is_array($events) || $events === []) {
            $events = ['*'];
        }

        $secret = 'whsec_' . random_token(24);
        $prefix = substr($secret, 0, 12);

        $id = Database::insert('webhooks', [
            'company_id' => $companyId,
            'url' => $url,
            'secret_hash' => password_hash($secret, PASSWORD_DEFAULT),
            'secret_prefix' => $prefix,
            'events' => json_encode(array_values($events), JSON_UNESCAPED_UNICODE),
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditLog::log('webhook.created', 'webhook', $id);
        $webhook = Database::fetch(
            'SELECT id, company_id, url, secret_prefix, events, is_active, created_at, updated_at
             FROM webhooks WHERE id = ?',
            [$id]
        );
        if ($webhook) {
            $webhook['events'] = json_decode((string) $webhook['events'], true) ?: [];
        }

        json_ok([
            'webhook' => $webhook,
            'secret' => $secret,
        ], [], 201);
    }

    public function update(Request $request, string $id): void
    {
        Permission::require('api.manage');
        FeatureGate::assertCan('webhooks');
        $companyId = (int) Auth::companyId();
        $webhookId = (int) $id;

        $webhook = Database::fetch(
            'SELECT * FROM webhooks WHERE id = ? AND company_id = ?',
            [$webhookId, $companyId]
        );
        if (!$webhook) {
            json_error('NOT_FOUND', 'Webhook не найден', 404);
        }

        $updates = ['updated_at' => now()];
        if ($request->input('url') !== null) {
            $url = trim((string) $request->input('url'));
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                json_error('VALIDATION_ERROR', 'Укажите корректный URL', 422);
            }
            $updates['url'] = $url;
        }
        if ($request->input('events') !== null) {
            $events = $request->input('events');
            if (!is_array($events)) {
                json_error('VALIDATION_ERROR', 'events должен быть массивом', 422);
            }
            $updates['events'] = json_encode(array_values($events), JSON_UNESCAPED_UNICODE);
        }
        if ($request->input('is_active') !== null) {
            $updates['is_active'] = $this->boolInput($request, 'is_active') ? 1 : 0;
        }

        Database::update('webhooks', $updates, 'id = ? AND company_id = ?', [$webhookId, $companyId]);
        AuditLog::log('webhook.updated', 'webhook', $webhookId);

        $updated = Database::fetch(
            'SELECT id, company_id, url, secret_prefix, events, is_active, created_at, updated_at
             FROM webhooks WHERE id = ?',
            [$webhookId]
        );
        if ($updated) {
            $updated['events'] = json_decode((string) $updated['events'], true) ?: [];
        }

        json_ok(['webhook' => $updated]);
    }

    public function destroy(Request $request, string $id): void
    {
        Permission::require('api.manage');
        FeatureGate::assertCan('webhooks');
        $companyId = (int) Auth::companyId();
        $webhookId = (int) $id;

        $webhook = Database::fetch(
            'SELECT id FROM webhooks WHERE id = ? AND company_id = ?',
            [$webhookId, $companyId]
        );
        if (!$webhook) {
            json_error('NOT_FOUND', 'Webhook не найден', 404);
        }

        Database::query('DELETE FROM webhooks WHERE id = ? AND company_id = ?', [$webhookId, $companyId]);
        AuditLog::log('webhook.deleted', 'webhook', $webhookId);
        json_ok(['deleted' => true]);
    }

    public function deliveries(Request $request, string $id): void
    {
        Permission::require('api.manage');
        FeatureGate::assertCan('webhooks');
        $companyId = (int) Auth::companyId();
        $webhookId = (int) $id;

        $webhook = Database::fetch(
            'SELECT id FROM webhooks WHERE id = ? AND company_id = ?',
            [$webhookId, $companyId]
        );
        if (!$webhook) {
            json_error('NOT_FOUND', 'Webhook не найден', 404);
        }

        $items = Database::fetchAll(
            'SELECT id, webhook_id, event, status, attempts, response_code, next_retry_at, created_at, updated_at
             FROM webhook_deliveries
             WHERE webhook_id = ?
             ORDER BY id DESC
             LIMIT 50',
            [$webhookId]
        );

        json_ok($items);
    }

    private function boolInput(Request $request, string $key): bool
    {
        $value = $request->input($key);
        if (is_bool($value)) {
            return $value;
        }
        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }
}
