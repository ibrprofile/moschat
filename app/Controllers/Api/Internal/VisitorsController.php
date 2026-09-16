<?php

declare(strict_types=1);

namespace MosChat\Controllers\Api\Internal;

use MosChat\Core\Auth;
use MosChat\Core\Database;
use MosChat\Core\Permission;
use MosChat\Core\Request;
use MosChat\Services\ConversationService;

final class VisitorsController
{
    public function index(Request $request): void
    {
        Permission::require('visitors.read');
        $companyId = (int) Auth::companyId();
        $since = gmdate('Y-m-d H:i:s', time() - 300);

        $items = Database::fetchAll(
            'SELECT v.*, s.name AS site_name, s.domain AS site_domain,
                    cl.name AS client_name, cl.email AS client_email
             FROM visitors v
             LEFT JOIN sites s ON s.id = v.site_id
             LEFT JOIN clients cl ON cl.id = v.client_id
             WHERE v.company_id = ? AND v.last_seen_at >= ?
             ORDER BY v.last_seen_at DESC
             LIMIT 100',
            [$companyId, $since]
        );

        json_ok($items, ['online_window_seconds' => 300]);
    }

    public function startChat(Request $request, string $id): void
    {
        Permission::require('visitors.write');
        $companyId = (int) Auth::companyId();
        $visitorId = (int) $id;

        $visitor = Database::fetch(
            'SELECT * FROM visitors WHERE id = ? AND company_id = ?',
            [$visitorId, $companyId]
        );
        if (!$visitor) {
            json_error('NOT_FOUND', 'Посетитель не найден', 404);
        }

        $existing = Database::fetch(
            "SELECT * FROM conversations
             WHERE company_id = ? AND visitor_id = ? AND status IN ('new','open','pending')
             ORDER BY id DESC LIMIT 1",
            [$companyId, $visitorId]
        );

        if ($existing) {
            json_ok(['conversation' => $existing, 'created' => false]);
        }

        $conversation = ConversationService::createFromVisitor(
            $companyId,
            (int) $visitor['site_id'],
            $visitorId,
            $visitor['client_id'] !== null ? (int) $visitor['client_id'] : null
        );

        json_ok(['conversation' => $conversation, 'created' => true], [], 201);
    }
}
