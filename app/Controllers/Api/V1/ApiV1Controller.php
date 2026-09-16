<?php

declare(strict_types=1);

namespace MosChat\Controllers\Api\V1;

use MosChat\Core\Database;
use MosChat\Core\RateLimiter;
use MosChat\Core\Request;
use MosChat\Services\ApiTokenService;
use MosChat\Services\ClientService;
use MosChat\Services\ConversationService;
use MosChat\Services\FeatureGate;
use MosChat\Services\MessageService;

final class ApiV1Controller
{
    /** @var array<string, mixed>|null */
    private ?array $token = null;

    public function clientsIndex(Request $request): void
    {
        $companyId = $this->auth($request);
        $result = ClientService::list($companyId, [
            'q' => $request->input('q'),
            'status' => $request->input('status'),
            'page' => (int) $request->input('page', 1),
            'per_page' => (int) $request->input('per_page', 25),
        ]);
        json_ok($result['items'], [
            'page' => $result['page'],
            'per_page' => $result['per_page'],
            'total' => $result['total'],
        ]);
    }

    public function clientsStore(Request $request): void
    {
        $companyId = $this->auth($request);
        try {
            $client = ClientService::create($companyId, $request->all());
        } catch (\InvalidArgumentException $e) {
            json_error('VALIDATION_ERROR', $e->getMessage(), 422);
        }
        json_ok(['client' => $client], [], 201);
    }

    public function clientsShow(Request $request, string $id): void
    {
        $companyId = $this->auth($request);
        $client = ClientService::get($companyId, (int) $id);
        if (!$client) {
            json_error('NOT_FOUND', 'Клиент не найден', 404);
        }
        json_ok(['client' => $client]);
    }

    public function clientsUpdate(Request $request, string $id): void
    {
        $companyId = $this->auth($request);
        try {
            $client = ClientService::update($companyId, (int) $id, $request->all());
        } catch (\RuntimeException $e) {
            json_error('NOT_FOUND', $e->getMessage(), 404);
        } catch (\InvalidArgumentException $e) {
            json_error('VALIDATION_ERROR', $e->getMessage(), 422);
        }
        json_ok(['client' => $client]);
    }

    public function clientsDestroy(Request $request, string $id): void
    {
        $companyId = $this->auth($request);
        $client = ClientService::get($companyId, (int) $id);
        if (!$client) {
            json_error('NOT_FOUND', 'Клиент не найден', 404);
        }
        Database::query('DELETE FROM clients WHERE id = ? AND company_id = ?', [(int) $id, $companyId]);
        json_ok(['deleted' => true]);
    }

    public function conversationsIndex(Request $request): void
    {
        $companyId = $this->auth($request);
        $filters = [
            'q' => $request->input('q'),
            'page' => (int) $request->input('page', 1),
            'per_page' => (int) $request->input('per_page', 25),
        ];
        if ($request->input('status')) {
            $filters['status'] = (string) $request->input('status');
        }
        if ($request->input('assigned_user_id') !== null && $request->input('assigned_user_id') !== '') {
            $filters['assigned'] = (int) $request->input('assigned_user_id');
        }
        $result = ConversationService::list($companyId, $filters);
        json_ok($result['items'], [
            'page' => $result['page'],
            'per_page' => $result['per_page'],
            'total' => $result['total'],
        ]);
    }

    public function conversationsShow(Request $request, string $id): void
    {
        $companyId = $this->auth($request);
        $conversation = ConversationService::get($companyId, (int) $id);
        if (!$conversation) {
            json_error('NOT_FOUND', 'Диалог не найден', 404);
        }
        json_ok(['conversation' => $conversation]);
    }

    public function conversationsUpdate(Request $request, string $id): void
    {
        $companyId = $this->auth($request);
        $conversationId = (int) $id;
        $conversation = ConversationService::get($companyId, $conversationId);
        if (!$conversation) {
            json_error('NOT_FOUND', 'Диалог не найден', 404);
        }

        try {
            if ($request->input('status') !== null && $request->input('status') !== '') {
                $conversation = ConversationService::setStatus(
                    $companyId,
                    $conversationId,
                    (string) $request->input('status')
                );
            }
            if (array_key_exists('assigned_user_id', $request->all())
                || array_key_exists('assigned_department_id', $request->all())) {
                $userId = $request->input('assigned_user_id');
                $deptId = $request->input('assigned_department_id');
                $conversation = ConversationService::assign(
                    $companyId,
                    $conversationId,
                    $userId === null || $userId === '' ? null : (int) $userId,
                    $deptId === null || $deptId === '' ? null : (int) $deptId
                );
            }
        } catch (\InvalidArgumentException $e) {
            json_error('VALIDATION_ERROR', $e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            json_error('NOT_FOUND', $e->getMessage(), 404);
        }

        json_ok(['conversation' => $conversation]);
    }

    public function messagesIndex(Request $request, string $id): void
    {
        $companyId = $this->auth($request);
        try {
            $result = MessageService::list(
                $companyId,
                (int) $id,
                (int) $request->input('page', 1),
                (int) $request->input('per_page', 50)
            );
        } catch (\RuntimeException $e) {
            json_error('NOT_FOUND', $e->getMessage(), 404);
        }
        json_ok($result['items'], [
            'page' => $result['page'],
            'per_page' => $result['per_page'],
            'total' => $result['total'],
        ]);
    }

    public function messagesStore(Request $request, string $id): void
    {
        $companyId = $this->auth($request);
        $body = trim((string) $request->input('body', ''));
        $kind = (string) $request->input('type', 'agent');
        if (!in_array($kind, ['agent', 'note', 'system'], true)) {
            $kind = 'agent';
        }
        if ($body === '') {
            json_error('VALIDATION_ERROR', 'Пустое сообщение', 422);
        }

        try {
            $message = MessageService::send(
                $companyId,
                (int) $id,
                $kind,
                $body,
                $this->token['created_by'] !== null ? (int) $this->token['created_by'] : null
            );
        } catch (\RuntimeException $e) {
            json_error('NOT_FOUND', $e->getMessage(), 404);
        } catch (\InvalidArgumentException $e) {
            json_error('VALIDATION_ERROR', $e->getMessage(), 422);
        }

        json_ok(['message' => $message], [], 201);
    }

    private function auth(Request $request): int
    {
        $token = ApiTokenService::verify($request->bearerToken());
        if (!$token) {
            json_error('UNAUTHORIZED', 'Неверный API-токен', 401);
        }

        $companyId = (int) $token['company_id'];
        FeatureGate::assertCan('api', $companyId);

        $limit = FeatureGate::plan($companyId)['key'] === 'pro' ? 600 : 60;
        if (!RateLimiter::attempt('api_v1:' . $companyId, $limit, 60)) {
            json_error('RATE_LIMITED', 'Превышен лимит запросов', 429);
        }

        $this->token = $token;
        return $companyId;
    }
}
