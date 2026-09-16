<?php

declare(strict_types=1);

namespace MosChat\Channels;

use MosChat\Services\ClientService;
use MosChat\Services\ConversationService;
use MosChat\Services\MessageService;

final class WebsiteChannel implements ChannelInterface
{
    public function key(): string
    {
        return 'website';
    }

    public function receiveMessage(array $payload): array
    {
        $body = trim((string) ($payload['body'] ?? $payload['text'] ?? ''));
        if ($body === '') {
            throw new \InvalidArgumentException('Пустое сообщение виджета');
        }

        return [
            'body' => $body,
            'message_type' => (string) ($payload['message_type'] ?? 'text'),
            'sender_type' => 'visitor',
            'metadata' => is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [],
        ];
    }

    public function sendMessage(array $conversation, array $message): array
    {
        // Website delivery is realtime/SSE — no external provider call.
        return [
            'success' => true,
            'external_id' => isset($message['id']) ? (string) $message['id'] : null,
            'error' => null,
        ];
    }

    public function normalizeVisitor(array $payload): array
    {
        $name = isset($payload['name']) ? trim((string) $payload['name']) : null;
        $email = isset($payload['email']) ? mb_strtolower(trim((string) $payload['email'])) : null;
        $phone = isset($payload['phone']) ? trim((string) $payload['phone']) : null;

        return [
            'name' => $name !== '' ? $name : null,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'external_id' => isset($payload['user_id']) ? (string) $payload['user_id'] : null,
            'attributes' => is_array($payload['attributes'] ?? null) ? $payload['attributes'] : [],
        ];
    }

    public function createConversation(int $companyId, array $ctx): array
    {
        $siteId = (int) ($ctx['site_id'] ?? 0);
        $visitorId = (int) ($ctx['visitor_id'] ?? 0);
        if ($siteId <= 0 || $visitorId <= 0) {
            throw new \InvalidArgumentException('site_id и visitor_id обязательны');
        }

        $clientId = isset($ctx['client_id']) ? (int) $ctx['client_id'] : null;
        if ($clientId === null && (!empty($ctx['email']) || !empty($ctx['phone']) || !empty($ctx['name']))) {
            $client = ClientService::findOrCreateFromContact(
                $companyId,
                isset($ctx['name']) ? (string) $ctx['name'] : null,
                isset($ctx['email']) ? (string) $ctx['email'] : null,
                isset($ctx['phone']) ? (string) $ctx['phone'] : null,
                'website'
            );
            $clientId = (int) $client['id'];
        }

        $conversation = ConversationService::createFromVisitor(
            $companyId,
            $siteId,
            $visitorId,
            $clientId,
            isset($ctx['department_id']) ? (int) $ctx['department_id'] : null
        );

        if (!empty($ctx['body'])) {
            MessageService::send(
                $companyId,
                (int) $conversation['id'],
                'visitor',
                (string) $ctx['body'],
                $visitorId,
                is_array($ctx['metadata'] ?? null) ? $ctx['metadata'] : []
            );
            $conversation = ConversationService::get($companyId, (int) $conversation['id']) ?? $conversation;
        }

        return $conversation;
    }
}
