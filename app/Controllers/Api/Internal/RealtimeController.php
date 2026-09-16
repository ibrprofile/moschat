<?php

declare(strict_types=1);

namespace MosChat\Controllers\Api\Internal;

use MosChat\Core\Auth;
use MosChat\Core\Database;
use MosChat\Core\Permission;
use MosChat\Core\Request;

final class RealtimeController
{
    public function stream(Request $request): void
    {
        Permission::require('inbox.read');
        $companyId = (int) Auth::companyId();
        $channel = 'company.' . $companyId;
        $lastId = max(0, (int) $request->input('last_id', 0));

        $this->sseHeaders();
        echo ": connected\n\n";
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        flush();

        $started = time();
        $maxSeconds = 55;

        while (!connection_aborted() && (time() - $started) < $maxSeconds) {
            $events = $this->fetchEvents($channel, $lastId, 50);
            foreach ($events as $event) {
                $lastId = (int) $event['id'];
                $payload = $event['payload'];
                if (is_string($payload)) {
                    $decoded = json_decode($payload, true);
                    $payload = is_array($decoded) ? $decoded : ['raw' => $payload];
                }
                $data = json_encode([
                    'id' => $lastId,
                    'event' => $event['event'],
                    'channel' => $event['channel'],
                    'payload' => $payload,
                    'created_at' => $event['created_at'],
                ], JSON_UNESCAPED_UNICODE);
                echo 'id: ' . $lastId . "\n";
                echo 'event: ' . $event['event'] . "\n";
                echo 'data: ' . $data . "\n\n";
                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                flush();
            }

            if ($events === []) {
                echo ": ping\n\n";
                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                flush();
                usleep(800000);
            } else {
                usleep(150000);
            }
        }

        exit;
    }

    public function poll(Request $request): void
    {
        Permission::require('inbox.read');
        $companyId = (int) Auth::companyId();
        $channel = 'company.' . $companyId;
        $lastId = max(0, (int) $request->input('last_id', 0));

        $events = $this->fetchEvents($channel, $lastId, 100);
        $mapped = [];
        $maxId = $lastId;
        foreach ($events as $event) {
            $maxId = max($maxId, (int) $event['id']);
            $payload = $event['payload'];
            if (is_string($payload)) {
                $decoded = json_decode($payload, true);
                $payload = is_array($decoded) ? $decoded : ['raw' => $payload];
            }
            $mapped[] = [
                'id' => (int) $event['id'],
                'event' => $event['event'],
                'channel' => $event['channel'],
                'payload' => $payload,
                'created_at' => $event['created_at'],
            ];
        }

        json_ok(['events' => $mapped, 'last_id' => $maxId]);
    }

    private function sseHeaders(): void
    {
        @ini_set('zlib.output_compression', '0');
        @ini_set('implicit_flush', '1');
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        ignore_user_abort(true);
        set_time_limit(0);
    }

    /** @return list<array<string, mixed>> */
    private function fetchEvents(string $channel, int $lastId, int $limit): array
    {
        return Database::fetchAll(
            'SELECT * FROM realtime_events
             WHERE channel = ? AND id > ?
             ORDER BY id ASC
             LIMIT ' . (int) $limit,
            [$channel, $lastId]
        );
    }
}
