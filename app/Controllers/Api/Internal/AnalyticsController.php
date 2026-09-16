<?php

declare(strict_types=1);

namespace MosChat\Controllers\Api\Internal;

use MosChat\Core\Auth;
use MosChat\Core\Database;
use MosChat\Core\Permission;
use MosChat\Core\Request;

final class AnalyticsController
{
    public function summary(Request $request): void
    {
        Permission::require('analytics.read');
        $companyId = (int) Auth::companyId();

        $conversations = (int) (Database::fetch(
            'SELECT COUNT(*) AS c FROM conversations WHERE company_id = ?',
            [$companyId]
        )['c'] ?? 0);

        $open = (int) (Database::fetch(
            "SELECT COUNT(*) AS c FROM conversations WHERE company_id = ? AND status IN ('new','open','pending')",
            [$companyId]
        )['c'] ?? 0);

        $closed = (int) (Database::fetch(
            "SELECT COUNT(*) AS c FROM conversations WHERE company_id = ? AND status = 'closed'",
            [$companyId]
        )['c'] ?? 0);

        $clients = (int) (Database::fetch(
            'SELECT COUNT(*) AS c FROM clients WHERE company_id = ?',
            [$companyId]
        )['c'] ?? 0);

        $clientsMonth = (int) (Database::fetch(
            'SELECT COUNT(*) AS c FROM clients WHERE company_id = ? AND created_at >= ?',
            [$companyId, gmdate('Y-m-01 00:00:00')]
        )['c'] ?? 0);

        $messagesToday = (int) (Database::fetch(
            'SELECT COUNT(*) AS c FROM messages WHERE company_id = ? AND created_at >= ?',
            [$companyId, gmdate('Y-m-d 00:00:00')]
        )['c'] ?? 0);

        $onlineVisitors = (int) (Database::fetch(
            'SELECT COUNT(*) AS c FROM visitors WHERE company_id = ? AND last_seen_at >= ?',
            [$companyId, gmdate('Y-m-d H:i:s', time() - 300)]
        )['c'] ?? 0);

        // Rough avg first response: mean minutes between conversation create and first agent message
        $avgRow = Database::fetch(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, c.created_at, m.created_at)) AS avg_sec
             FROM conversations c
             JOIN messages m ON m.id = (
               SELECT MIN(m2.id) FROM messages m2
               WHERE m2.conversation_id = c.id AND m2.sender_type = 'agent' AND m2.message_type <> 'note'
             )
             WHERE c.company_id = ? AND c.created_at >= ?",
            [$companyId, gmdate('Y-m-d H:i:s', time() - 30 * 86400)]
        );
        $avgSec = isset($avgRow['avg_sec']) && $avgRow['avg_sec'] !== null
            ? (int) round((float) $avgRow['avg_sec'])
            : null;

        $labels = [];
        $values = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = gmdate('Y-m-d', time() - $i * 86400);
            $labels[] = $day;
            $values[] = (int) (Database::fetch(
                'SELECT COUNT(*) AS c FROM conversations WHERE company_id = ? AND DATE(created_at) = ?',
                [$companyId, $day]
            )['c'] ?? 0);
        }

        json_ok([
            'conversations' => $conversations,
            'open' => $open,
            'closed' => $closed,
            'clients' => $clients,
            'clients_month' => $clientsMonth,
            'messages_today' => $messagesToday,
            'online_visitors' => $onlineVisitors,
            'avg_response_seconds' => $avgSec,
            'avg_response' => $avgSec !== null ? $this->formatDuration($avgSec) : '—',
            'series' => ['labels' => $labels, 'values' => $values],
        ]);
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 'с';
        }
        if ($seconds < 3600) {
            return (int) round($seconds / 60) . 'м';
        }
        return round($seconds / 3600, 1) . 'ч';
    }
}
