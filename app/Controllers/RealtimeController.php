<?php

declare(strict_types=1);

namespace MosChat\Controllers;

use MosChat\Core\Auth;
use MosChat\Core\Database;
use MosChat\Core\Permission;
use MosChat\Core\Request;
use MosChat\Services\SiteService;

final class RealtimeController
{
    public function app(Request $request): void
    {
        if (!Auth::check()) {
            json_error('UNAUTHORIZED', 'Требуется вход', 401);
        }
        if (!Auth::companyId()) {
            json_error('FORBIDDEN', 'Компания не выбрана', 403);
        }
        Permission::require('inbox.read');

        $companyId = (int) Auth::companyId();
        $channel = 'company.' . $companyId;
        $lastId = max(0, (int) $request->input('last_id', 0));
        $this->stream($channel, $lastId);
    }

    public function widget(Request $request): void
    {
        $publicKey = $request->header('X-Site-Key')
            ?: (string) $request->input('site_key', '');
        $visitorToken = $request->header('X-Visitor-Token')
            ?: (string) $request->input('visitor_token', '');

        $site = SiteService::findByPublicKey(trim($publicKey));
        if (!$site) {
            json_error('UNAUTHORIZED', 'Неверный site key', 401);
        }

        $visitor = Database::fetch(
            'SELECT * FROM visitors WHERE company_id = ? AND site_id = ? AND visitor_key = ?',
            [(int) $site['company_id'], (int) $site['id'], trim($visitorToken)]
        );
        if (!$visitor) {
            json_error('UNAUTHORIZED', 'Неверный visitor token', 401);
        }

        Database::update('visitors', ['last_seen_at' => now()], 'id = ?', [(int) $visitor['id']]);

        $channel = 'widget.' . (int) $site['id'] . '.visitor.' . (int) $visitor['id'];
        $lastId = max(0, (int) $request->input('last_id', 0));
        $this->stream($channel, $lastId);
    }

    private function stream(string $channel, int $lastId): never
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

        echo ": connected channel={$channel}\n\n";
        flush();

        $started = time();
        $maxSeconds = 55;

        while (!connection_aborted() && (time() - $started) < $maxSeconds) {
            $events = Database::fetchAll(
                'SELECT * FROM realtime_events
                 WHERE channel = ? AND id > ?
                 ORDER BY id ASC
                 LIMIT 50',
                [$channel, $lastId]
            );

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
                flush();
            }

            if ($events === []) {
                echo ": ping\n\n";
                flush();
                usleep(800000);
            } else {
                usleep(150000);
            }
        }

        exit;
    }
}
