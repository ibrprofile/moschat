<?php

declare(strict_types=1);

namespace MosChat\Controllers\Api\Widget;

use MosChat\Core\Database;
use MosChat\Core\RateLimiter;
use MosChat\Core\Request;
use MosChat\Services\ClientService;
use MosChat\Services\ConversationService;
use MosChat\Services\MessageService;
use MosChat\Services\RealtimePublisher;
use MosChat\Services\SiteService;

final class WidgetController
{
    public function bootstrap(Request $request): void
    {
        $site = $this->resolveSite($request);
        $companyId = (int) $site['company_id'];
        $siteId = (int) $site['id'];

        if (!RateLimiter::attempt('widget:boot:' . $request->ip(), 60, 60)) {
            json_error('RATE_LIMITED', 'Слишком много запросов', 429);
        }

        $visitorToken = $request->header('X-Visitor-Token')
            ?: (string) $request->input('visitor_token', '');
        $visitor = $this->upsertVisitor($companyId, $siteId, $visitorToken, $request);

        $settings = SiteService::widgetSettings($siteId) ?? [];
        $openConversation = Database::fetch(
            "SELECT * FROM conversations
             WHERE company_id = ? AND visitor_id = ? AND status IN ('new','open','pending')
             ORDER BY id DESC LIMIT 1",
            [$companyId, (int) $visitor['id']]
        );

        json_ok([
            // flat fields the widget.js boot() reads directly
            'visitor_token'   => $visitor['visitor_key'],
            'session_key'     => $visitor['visitor_key'], // reuse visitor key as session identifier
            'conversation_id' => $openConversation ? (int) $openConversation['id'] : null,
            'settings'        => $settings,
            // extra context (not used by widget but useful for debugging)
            'site' => [
                'id'         => $siteId,
                'name'       => $site['name'],
                'public_key' => $site['public_key'],
            ],
            'visitor' => [
                'id'        => (int) $visitor['id'],
                'token'     => $visitor['visitor_key'],
                'client_id' => $visitor['client_id'] !== null ? (int) $visitor['client_id'] : null,
            ],
        ]);
    }

    public function messages(Request $request): void
    {
        $ctx = $this->authenticatedVisitor($request);
        $companyId = $ctx['company_id'];
        $visitorId = $ctx['visitor_id'];

        $conversationId = (int) $request->input('conversation_id', 0);
        $conversation = $this->visitorConversation($companyId, $visitorId, $conversationId);

        if ($request->method() === 'GET') {
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', 50);
            $result = MessageService::list($companyId, (int) $conversation['id'], $page, $perPage);
            // Hide internal notes from visitors
            $items = array_values(array_filter(
                $result['items'],
                static fn (array $m): bool => ($m['message_type'] ?? '') !== 'note'
            ));
            json_ok([
                'items'           => $items,
                'page'            => $result['page'],
                'per_page'        => $result['per_page'],
                'total'           => count($items),
                'conversation_id' => (int) $conversation['id'],
            ]);
        }

        if (!RateLimiter::attempt('widget:msg:' . $visitorId, 60, 60)) {
            json_error('RATE_LIMITED', 'Слишком много сообщений', 429);
        }

        $body = trim((string) $request->input('body', $request->input('text', '')));
        if ($body === '') {
            json_error('VALIDATION_ERROR', 'Пустое сообщение', 422);
        }

        try {
            $message = MessageService::send(
                $companyId,
                (int) $conversation['id'],
                'visitor',
                $body,
                $visitorId
            );
        } catch (\InvalidArgumentException $e) {
            json_error('VALIDATION_ERROR', $e->getMessage(), 422);
        }

        json_ok([
            'message' => $message,
            'conversation' => ConversationService::get($companyId, (int) $conversation['id']),
        ], [], 201);
    }

    public function contact(Request $request): void
    {
        $ctx = $this->authenticatedVisitor($request);
        $companyId = $ctx['company_id'];
        $visitorId = $ctx['visitor_id'];
        $siteId = $ctx['site_id'];

        $name = $request->input('name');
        $email = $request->input('email');
        $phone = $request->input('phone');

        $client = ClientService::findOrCreateFromContact(
            $companyId,
            $name !== null ? (string) $name : null,
            $email !== null ? (string) $email : null,
            $phone !== null ? (string) $phone : null,
            'website'
        );

        Database::update('visitors', [
            'client_id' => (int) $client['id'],
            'last_seen_at' => now(),
        ], 'id = ?', [$visitorId]);

        $conversationId = (int) $request->input('conversation_id', 0);
        if ($conversationId > 0) {
            $conversation = ConversationService::get($companyId, $conversationId);
            if ($conversation && (int) ($conversation['visitor_id'] ?? 0) === $visitorId) {
                Database::update('conversations', [
                    'client_id' => (int) $client['id'],
                    'updated_at' => now(),
                ], 'id = ? AND company_id = ?', [$conversationId, $companyId]);
            }
        } elseif ($request->input('start_conversation') || $request->input('body')) {
            $conversation = ConversationService::createFromVisitor(
                $companyId,
                $siteId,
                $visitorId,
                (int) $client['id']
            );
            $conversationId = (int) $conversation['id'];
            $body = trim((string) $request->input('body', ''));
            if ($body !== '') {
                MessageService::send($companyId, $conversationId, 'visitor', $body, $visitorId);
            }
        }

        json_ok([
            'client' => $client,
            'conversation_id' => $conversationId > 0 ? $conversationId : null,
        ]);
    }

    public function typing(Request $request): void
    {
        $ctx = $this->authenticatedVisitor($request);
        $companyId = $ctx['company_id'];
        $visitorId = $ctx['visitor_id'];
        $conversationId = (int) $request->input('conversation_id', 0);

        if ($conversationId > 0) {
            $conversation = ConversationService::get($companyId, $conversationId);
            if (!$conversation || (int) ($conversation['visitor_id'] ?? 0) !== $visitorId) {
                json_error('FORBIDDEN', 'Нет доступа к диалогу', 403);
            }
        }

        RealtimePublisher::publish('company.' . $companyId, 'typing', [
            'conversation_id' => $conversationId ?: null,
            'visitor_id' => $visitorId,
            'from' => 'visitor',
        ]);

        if ($conversationId > 0) {
            RealtimePublisher::publish('conversation.' . $conversationId, 'typing', [
                'conversation_id' => $conversationId,
                'visitor_id' => $visitorId,
                'from' => 'visitor',
            ]);
        }

        json_ok(['ok' => true]);
    }

    /** @return array<string, mixed> */
    private function resolveSite(Request $request): array
    {
        $publicKey = $request->header('X-Site-Key')
            ?: (string) $request->input('site_key', $request->input('public_key', ''));
        $publicKey = trim($publicKey);
        if ($publicKey === '') {
            json_error('UNAUTHORIZED', 'Укажите X-Site-Key', 401);
        }

        $site = SiteService::findByPublicKey($publicKey);
        if (!$site) {
            json_error('NOT_FOUND', 'Сайт не найден', 404);
        }

        return $site;
    }

    /**
     * @return array{company_id: int, site_id: int, visitor_id: int, visitor: array<string, mixed>, site: array<string, mixed>}
     */
    private function authenticatedVisitor(Request $request): array
    {
        $site = $this->resolveSite($request);
        $token = $request->header('X-Visitor-Token')
            ?: (string) $request->input('visitor_token', '');
        $token = trim($token);
        if ($token === '') {
            json_error('UNAUTHORIZED', 'Укажите X-Visitor-Token', 401);
        }

        $visitor = Database::fetch(
            'SELECT * FROM visitors WHERE company_id = ? AND site_id = ? AND visitor_key = ?',
            [(int) $site['company_id'], (int) $site['id'], $token]
        );
        if (!$visitor) {
            json_error('UNAUTHORIZED', 'Неверный visitor token', 401);
        }

        Database::update('visitors', ['last_seen_at' => now()], 'id = ?', [(int) $visitor['id']]);

        return [
            'company_id' => (int) $site['company_id'],
            'site_id' => (int) $site['id'],
            'visitor_id' => (int) $visitor['id'],
            'visitor' => $visitor,
            'site' => $site,
        ];
    }

    /** @return array<string, mixed> */
    private function upsertVisitor(int $companyId, int $siteId, string $token, Request $request): array
    {
        $token = trim($token);
        $visitor = null;
        if ($token !== '') {
            $visitor = Database::fetch(
                'SELECT * FROM visitors WHERE company_id = ? AND visitor_key = ?',
                [$companyId, $token]
            );
        }

        $ua = mb_substr($request->userAgent(), 0, 512);
        $ipHash = hash('sha256', $request->ip());

        if ($visitor) {
            Database::update('visitors', [
                'last_seen_at' => now(),
                'user_agent' => $ua,
                'ip_hash' => $ipHash,
                'page_views' => (int) $visitor['page_views'] + 1,
                'landing_page' => $request->input('page_url')
                    ? mb_substr((string) $request->input('page_url'), 0, 512)
                    : $visitor['landing_page'],
                'referrer' => $request->input('referrer')
                    ? mb_substr((string) $request->input('referrer'), 0, 512)
                    : $visitor['referrer'],
            ], 'id = ?', [(int) $visitor['id']]);
            return Database::fetch('SELECT * FROM visitors WHERE id = ?', [(int) $visitor['id']]) ?? $visitor;
        }

        $visitorKey = $token !== '' ? $token : 'vk_' . random_token(16);
        $id = Database::insert('visitors', [
            'company_id' => $companyId,
            'site_id' => $siteId,
            'visitor_key' => $visitorKey,
            'client_id' => null,
            'ip_hash' => $ipHash,
            'user_agent' => $ua,
            'language' => $request->header('Accept-Language')
                ? mb_substr((string) $request->header('Accept-Language'), 0, 32)
                : null,
            'referrer' => $request->input('referrer')
                ? mb_substr((string) $request->input('referrer'), 0, 512)
                : null,
            'landing_page' => $request->input('page_url')
                ? mb_substr((string) $request->input('page_url'), 0, 512)
                : null,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'page_views' => 1,
        ]);

        return Database::fetch('SELECT * FROM visitors WHERE id = ?', [$id]) ?? [];
    }

    /** @return array<string, mixed> */
    private function visitorConversation(int $companyId, int $visitorId, int $conversationId): array
    {
        if ($conversationId > 0) {
            $conversation = ConversationService::get($companyId, $conversationId);
            if (!$conversation || (int) ($conversation['visitor_id'] ?? 0) !== $visitorId) {
                json_error('FORBIDDEN', 'Нет доступа к диалогу', 403);
            }
            return $conversation;
        }

        $existing = Database::fetch(
            "SELECT * FROM conversations
             WHERE company_id = ? AND visitor_id = ? AND status IN ('new','open','pending')
             ORDER BY id DESC LIMIT 1",
            [$companyId, $visitorId]
        );
        if ($existing) {
            return $existing;
        }

        $visitor = Database::fetch('SELECT * FROM visitors WHERE id = ?', [$visitorId]);
        if (!$visitor) {
            json_error('NOT_FOUND', 'Посетитель не найден', 404);
        }

        return ConversationService::createFromVisitor(
            $companyId,
            (int) $visitor['site_id'],
            $visitorId,
            $visitor['client_id'] !== null ? (int) $visitor['client_id'] : null
        );
    }
}
