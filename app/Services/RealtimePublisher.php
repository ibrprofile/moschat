<?php

declare(strict_types=1);

namespace MosChat\Services;

use MosChat\Core\Database;

final class RealtimePublisher
{
    public static function publish(string $channel, string $event, array $payload = []): int
    {
        return Database::insert('realtime_events', [
            'channel' => $channel,
            'event' => $event,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);
    }
}
