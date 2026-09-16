<?php

declare(strict_types=1);

namespace MosChat\Services;

use MosChat\Core\Database;

final class WebhookDispatcher
{
    public static function dispatch(int $companyId, string $event, array $data = []): void
    {
        if (!FeatureGate::can('webhooks', $companyId)) {
            return;
        }

        $webhooks = Database::fetchAll(
            'SELECT id, events FROM webhooks WHERE company_id = ? AND is_active = 1',
            [$companyId]
        );

        if ($webhooks === []) {
            return;
        }

        $payload = json_encode([
            'event' => $event,
            'created_at' => now(),
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE);

        foreach ($webhooks as $webhook) {
            $events = json_decode((string) $webhook['events'], true);
            if (!is_array($events)) {
                continue;
            }
            if (!in_array($event, $events, true) && !in_array('*', $events, true)) {
                continue;
            }

            Database::insert('webhook_deliveries', [
                'webhook_id' => (int) $webhook['id'],
                'event' => $event,
                'payload' => $payload,
                'status' => 'pending',
                'attempts' => 0,
                'next_retry_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
