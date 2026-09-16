<?php

declare(strict_types=1);

namespace MosChat\Services;

use MosChat\Core\Database;

final class NotificationService
{
    public static function create(
        int $companyId,
        int $userId,
        string $type,
        string $title,
        ?string $body = null,
        array $data = []
    ): array {
        $id = Database::insert('notifications', [
            'company_id' => $companyId,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data === [] ? null : json_encode($data, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);

        $notification = Database::fetch('SELECT * FROM notifications WHERE id = ?', [$id]) ?? [];

        RealtimePublisher::publish('user.' . $userId, 'notification.created', [
            'notification' => $notification,
        ]);

        return $notification;
    }
}
