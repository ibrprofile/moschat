<?php

declare(strict_types=1);

namespace MosChat\Services;

use MosChat\Core\Database;

final class MessageService
{
    /**
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public static function list(int $companyId, int $conversationId, int $page = 1, int $perPage = 50): array
    {
        $conversation = ConversationService::get($companyId, $conversationId);
        if (!$conversation) {
            throw new \RuntimeException('Диалог не найден');
        }

        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        $total = (int) (Database::fetch(
            'SELECT COUNT(*) AS c FROM messages WHERE conversation_id = ? AND company_id = ?',
            [$conversationId, $companyId]
        )['c'] ?? 0);

        $items = Database::fetchAll(
            'SELECT m.*, u.name AS sender_name
             FROM messages m
             LEFT JOIN users u ON u.id = m.sender_id AND m.sender_type = \'agent\'
             WHERE m.conversation_id = ? AND m.company_id = ?
             ORDER BY m.id ASC
             LIMIT ' . $perPage . ' OFFSET ' . $offset,
            [$conversationId, $companyId]
        );

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * @param 'agent'|'visitor'|'note'|'system' $kind
     * @param array<string, mixed> $meta
     */
    public static function send(
        int $companyId,
        int $conversationId,
        string $kind,
        string $body,
        ?int $senderId = null,
        array $meta = []
    ): array {
        $conversation = ConversationService::get($companyId, $conversationId);
        if (!$conversation) {
            throw new \RuntimeException('Диалог не найден');
        }

        $body = trim($body);
        if ($body === '') {
            throw new \InvalidArgumentException('Пустое сообщение');
        }

        $kind = strtolower($kind);
        [$senderType, $messageType] = match ($kind) {
            'agent' => ['agent', 'text'],
            'visitor' => ['visitor', 'text'],
            'note' => ['agent', 'note'],
            'system' => ['system', 'system'],
            default => throw new \InvalidArgumentException('Неизвестный тип сообщения'),
        };

        $messageId = Database::insert('messages', [
            'conversation_id' => $conversationId,
            'company_id' => $companyId,
            'channel' => (string) ($conversation['channel'] ?? 'website'),
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'message_type' => $messageType,
            'body' => $body,
            'metadata' => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
            'status' => 'sent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $preview = mb_substr($body, 0, 240);
        $convUpdate = [
            'last_message_at' => now(),
            'last_message_preview' => $preview,
            'updated_at' => now(),
        ];

        if ($kind === 'visitor') {
            $convUpdate['unread_agent_count'] = (int) $conversation['unread_agent_count'] + 1;
            if ($conversation['status'] === 'closed') {
                $convUpdate['status'] = 'open';
                $convUpdate['closed_at'] = null;
            } elseif ($conversation['status'] === 'pending') {
                $convUpdate['status'] = 'open';
            }
        } elseif ($kind === 'agent') {
            $convUpdate['unread_visitor_count'] = (int) $conversation['unread_visitor_count'] + 1;
            if ($conversation['status'] === 'new') {
                $convUpdate['status'] = 'open';
            }
            if ($senderId !== null && empty($conversation['assigned_user_id'])) {
                $convUpdate['assigned_user_id'] = $senderId;
            }
        }
        // notes / system: update preview only, no unread counters for visitors

        Database::update('conversations', $convUpdate, 'id = ? AND company_id = ?', [$conversationId, $companyId]);

        if (!empty($conversation['client_id']) && in_array($kind, ['agent', 'visitor'], true)) {
            Database::update('clients', [
                'last_contact_at' => now(),
                'updated_at' => now(),
            ], 'id = ?', [(int) $conversation['client_id']]);
        }

        $message = Database::fetch('SELECT * FROM messages WHERE id = ?', [$messageId]) ?? [];
        $updatedConversation = ConversationService::get($companyId, $conversationId);

        $payload = [
            'message' => $message,
            'conversation_id' => $conversationId,
            'conversation' => $updatedConversation,
        ];

        RealtimePublisher::publish('company.' . $companyId, 'message.created', $payload);
        RealtimePublisher::publish('conversation.' . $conversationId, 'message.created', $payload);

        if (!empty($conversation['site_id']) && $kind === 'agent') {
            RealtimePublisher::publish(
                'widget.' . (int) $conversation['site_id'] . '.visitor.' . (int) ($conversation['visitor_id'] ?? 0),
                'message.created',
                $payload
            );
        }

        if ($kind !== 'note') {
            WebhookDispatcher::dispatch($companyId, 'message.created', $payload);
            WebhookDispatcher::dispatch($companyId, 'conversation.updated', [
                'conversation' => $updatedConversation,
            ]);
        }

        if ($kind === 'visitor' && !empty($conversation['assigned_user_id'])) {
            NotificationService::create(
                $companyId,
                (int) $conversation['assigned_user_id'],
                'message.created',
                'Новое сообщение',
                $preview,
                ['conversation_id' => $conversationId, 'message_id' => $messageId]
            );
        }

        return $message;
    }
}
