<?php

declare(strict_types=1);

namespace MosChat\Services;

use MosChat\Core\Auth;
use MosChat\Core\Database;

final class ConversationService
{
    /**
     * @param array{
     *   status?: string|null,
     *   assigned?: int|null,
     *   mine?: bool,
     *   unassigned?: bool,
     *   q?: string|null,
     *   page?: int,
     *   per_page?: int
     * } $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public static function list(int $companyId, array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;

        $where = ['c.company_id = ?'];
        $params = [$companyId];

        if (!empty($filters['status'])) {
            $where[] = 'c.status = ?';
            $params[] = (string) $filters['status'];
        }

        if (!empty($filters['mine'])) {
            $userId = Auth::id();
            if ($userId) {
                $where[] = 'c.assigned_user_id = ?';
                $params[] = $userId;
            }
        } elseif (!empty($filters['unassigned'])) {
            $where[] = 'c.assigned_user_id IS NULL';
        } elseif (isset($filters['assigned']) && $filters['assigned'] !== null && $filters['assigned'] !== '') {
            $where[] = 'c.assigned_user_id = ?';
            $params[] = (int) $filters['assigned'];
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(c.last_message_preview LIKE ? OR cl.name LIKE ? OR cl.email LIKE ? OR cl.phone LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like);
        }

        $whereSql = implode(' AND ', $where);

        $total = (int) (Database::fetch(
            "SELECT COUNT(*) AS c
             FROM conversations c
             LEFT JOIN clients cl ON cl.id = c.client_id
             WHERE {$whereSql}",
            $params
        )['c'] ?? 0);

        $items = Database::fetchAll(
            "SELECT c.*,
                    cl.name AS client_name, cl.email AS client_email, cl.phone AS client_phone,
                    u.name AS assigned_user_name
             FROM conversations c
             LEFT JOIN clients cl ON cl.id = c.client_id
             LEFT JOIN users u ON u.id = c.assigned_user_id
             WHERE {$whereSql}
             ORDER BY c.last_message_at DESC, c.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public static function get(int $companyId, int $conversationId): ?array
    {
        return Database::fetch(
            'SELECT c.*,
                    cl.name AS client_name, cl.email AS client_email, cl.phone AS client_phone,
                    u.name AS assigned_user_name,
                    d.name AS assigned_department_name
             FROM conversations c
             LEFT JOIN clients cl ON cl.id = c.client_id
             LEFT JOIN users u ON u.id = c.assigned_user_id
             LEFT JOIN departments d ON d.id = c.assigned_department_id
             WHERE c.company_id = ? AND c.id = ?',
            [$companyId, $conversationId]
        );
    }

    public static function createFromVisitor(
        int $companyId,
        int $siteId,
        int $visitorId,
        ?int $clientId = null,
        ?int $departmentId = null
    ): array {
        $monthStart = gmdate('Y-m-01 00:00:00');
        FeatureGate::assertWithinLimit(
            'conversations_month',
            (int) (Database::fetch(
                'SELECT COUNT(*) AS c FROM conversations
                 WHERE company_id = ? AND created_at >= ?',
                [$companyId, $monthStart]
            )['c'] ?? 0),
            $companyId
        );

        if ($departmentId === null) {
            $dept = Database::fetch(
                'SELECT id FROM departments WHERE company_id = ? AND is_default = 1 LIMIT 1',
                [$companyId]
            );
            $departmentId = $dept ? (int) $dept['id'] : null;
        }

        $conversationId = Database::insert('conversations', [
            'company_id' => $companyId,
            'site_id' => $siteId,
            'client_id' => $clientId,
            'visitor_id' => $visitorId,
            'channel' => 'website',
            'status' => 'new',
            'assigned_department_id' => $departmentId,
            'unread_agent_count' => 0,
            'unread_visitor_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Database::insert('conversation_participants', [
            'conversation_id' => $conversationId,
            'participant_type' => 'visitor',
            'participant_id' => $visitorId,
            'last_read_at' => null,
        ]);

        if ($clientId) {
            Database::insert('conversation_participants', [
                'conversation_id' => $conversationId,
                'participant_type' => 'client',
                'participant_id' => $clientId,
                'last_read_at' => null,
            ]);
            Database::query(
                'UPDATE clients SET conversations_count = conversations_count + 1, last_contact_at = ?, updated_at = ? WHERE id = ?',
                [now(), now(), $clientId]
            );
        }

        $conversation = self::get($companyId, $conversationId) ?? [];
        AuditLog::log('conversation.created', 'conversation', $conversationId);
        RealtimePublisher::publish('company.' . $companyId, 'conversation.created', [
            'conversation' => $conversation,
        ]);
        WebhookDispatcher::dispatch($companyId, 'conversation.created', [
            'conversation' => $conversation,
        ]);

        return $conversation;
    }

    public static function assign(int $companyId, int $conversationId, ?int $userId, ?int $departmentId = null): array
    {
        $conversation = self::get($companyId, $conversationId);
        if (!$conversation) {
            throw new \RuntimeException('Диалог не найден');
        }

        if ($userId !== null) {
            $member = Database::fetch(
                'SELECT id FROM company_members WHERE company_id = ? AND user_id = ? AND status = ?',
                [$companyId, $userId, 'active']
            );
            if (!$member) {
                throw new \InvalidArgumentException('Пользователь не состоит в компании');
            }
        }

        $data = [
            'assigned_user_id' => $userId,
            'updated_at' => now(),
        ];
        if ($departmentId !== null) {
            $data['assigned_department_id'] = $departmentId;
        }
        if ($userId !== null && $conversation['status'] === 'new') {
            $data['status'] = 'open';
        }

        Database::update('conversations', $data, 'id = ? AND company_id = ?', [$conversationId, $companyId]);

        if ($userId !== null) {
            $exists = Database::fetch(
                'SELECT id FROM conversation_participants
                 WHERE conversation_id = ? AND participant_type = ? AND participant_id = ?',
                [$conversationId, 'user', $userId]
            );
            if (!$exists) {
                Database::insert('conversation_participants', [
                    'conversation_id' => $conversationId,
                    'participant_type' => 'user',
                    'participant_id' => $userId,
                    'last_read_at' => null,
                ]);
            }

            NotificationService::create(
                $companyId,
                $userId,
                'conversation.assigned',
                'Вам назначен диалог',
                'Диалог #' . $conversationId . ' назначен вам',
                ['conversation_id' => $conversationId]
            );
        }

        $updated = self::get($companyId, $conversationId) ?? [];
        AuditLog::log('conversation.assigned', 'conversation', $conversationId, [
            'assigned_user_id' => $userId,
            'assigned_department_id' => $departmentId,
        ]);
        RealtimePublisher::publish('company.' . $companyId, 'conversation.updated', [
            'conversation' => $updated,
            'reason' => 'assignment',
        ]);
        WebhookDispatcher::dispatch($companyId, 'conversation.updated', [
            'conversation' => $updated,
        ]);

        return $updated;
    }

    public static function setStatus(int $companyId, int $conversationId, string $status): array
    {
        $allowed = ['new', 'open', 'pending', 'closed'];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Недопустимый статус');
        }

        $conversation = self::get($companyId, $conversationId);
        if (!$conversation) {
            throw new \RuntimeException('Диалог не найден');
        }

        $data = [
            'status' => $status,
            'updated_at' => now(),
            'closed_at' => $status === 'closed' ? now() : null,
        ];
        Database::update('conversations', $data, 'id = ? AND company_id = ?', [$conversationId, $companyId]);

        $updated = self::get($companyId, $conversationId) ?? [];
        $event = $status === 'closed' ? 'conversation.closed' : 'conversation.updated';

        AuditLog::log('conversation.status', 'conversation', $conversationId, ['status' => $status]);
        RealtimePublisher::publish('company.' . $companyId, $event, [
            'conversation' => $updated,
        ]);
        WebhookDispatcher::dispatch($companyId, $event, [
            'conversation' => $updated,
        ]);

        return $updated;
    }

    public static function addTag(int $companyId, int $conversationId, int $tagId): array
    {
        $conversation = self::get($companyId, $conversationId);
        if (!$conversation) {
            throw new \RuntimeException('Диалог не найден');
        }

        $tag = Database::fetch(
            'SELECT * FROM tags WHERE id = ? AND company_id = ?',
            [$tagId, $companyId]
        );
        if (!$tag) {
            throw new \InvalidArgumentException('Тег не найден');
        }

        $exists = Database::fetch(
            'SELECT conversation_id FROM conversation_tags WHERE conversation_id = ? AND tag_id = ?',
            [$conversationId, $tagId]
        );
        if (!$exists) {
            Database::query(
                'INSERT INTO conversation_tags (conversation_id, tag_id) VALUES (?, ?)',
                [$conversationId, $tagId]
            );
        }

        $updated = self::get($companyId, $conversationId) ?? [];
        $updated['tags'] = Database::fetchAll(
            'SELECT t.* FROM tags t
             JOIN conversation_tags ct ON ct.tag_id = t.id
             WHERE ct.conversation_id = ?',
            [$conversationId]
        );

        RealtimePublisher::publish('company.' . $companyId, 'conversation.updated', [
            'conversation' => $updated,
            'reason' => 'tag',
        ]);

        return $updated;
    }
}
