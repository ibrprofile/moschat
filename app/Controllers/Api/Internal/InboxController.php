<?php

declare(strict_types=1);

namespace MosChat\Controllers\Api\Internal;

use MosChat\Core\Auth;
use MosChat\Core\Database;
use MosChat\Core\Permission;
use MosChat\Core\Request;
use MosChat\Services\ClientService;
use MosChat\Services\ConversationService;
use MosChat\Services\MessageService;

final class InboxController
{
    public function index(Request $request): void
    {
        Permission::require('inbox.read');
        $companyId = (int) Auth::companyId();
        $filter = (string) $request->input('filter', '');
        $status = $request->input('status');
        $filters = [
            'q' => $request->input('q'),
            'page' => (int) $request->input('page', 1),
            'per_page' => (int) $request->input('per_page', 40),
        ];

        if ($filter === 'unassigned' || $request->input('unassigned')) {
            $filters['unassigned'] = true;
        } elseif ($filter === 'mine' || $request->input('mine')) {
            $filters['mine'] = true;
        }

        if (in_array((string) $status, ['new', 'open', 'pending', 'closed'], true)) {
            $filters['status'] = $status;
        } elseif (in_array($filter, ['open', 'pending', 'closed', 'new'], true)) {
            $filters['status'] = $filter;
        }

        json_ok(ConversationService::list($companyId, $filters));
    }

    public function show(Request $request, string $id): void
    {
        Permission::require('inbox.read');
        $companyId = (int) Auth::companyId();
        $conversation = ConversationService::get($companyId, (int) $id);
        if (!$conversation) {
            json_error('NOT_FOUND', 'Диалог не найден', 404);
        }

        $messages = MessageService::list($companyId, (int) $id, 1, 100);
        $client = null;
        if (!empty($conversation['client_id'])) {
            $client = Database::fetch(
                'SELECT * FROM clients WHERE id = ? AND company_id = ?',
                [(int) $conversation['client_id'], $companyId]
            );
        }

        Database::update('conversations', [
            'unread_agent_count' => 0,
            'updated_at' => now(),
        ], 'id = ? AND company_id = ?', [(int) $id, $companyId]);

        json_ok([
            'conversation' => $conversation,
            'messages' => $messages,
            'client' => $client,
        ]);
    }

    public function message(Request $request, string $id): void
    {
        Permission::require('inbox.write');
        $companyId = (int) Auth::companyId();
        $body = trim((string) $request->input('body', ''));
        $type = (string) $request->input('type', 'text');
        $kind = $type === 'note' ? 'note' : 'agent';

        try {
            $message = MessageService::send($companyId, (int) $id, $kind, $body, Auth::id());
            json_ok($message);
        } catch (\InvalidArgumentException $e) {
            json_error('VALIDATION_ERROR', $e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            json_error('NOT_FOUND', $e->getMessage(), 404);
        }
    }

    public function update(Request $request, string $id): void
    {
        Permission::require('inbox.write');
        $companyId = (int) Auth::companyId();
        $conversationId = (int) $id;

        try {
            if ($request->input('assign_me')) {
                json_ok(ConversationService::assign($companyId, $conversationId, Auth::id()));
            }
            if ($request->input('status')) {
                json_ok(ConversationService::setStatus($companyId, $conversationId, (string) $request->input('status')));
            }
            if ($request->input('assigned_user_id') !== null || $request->input('assigned_department_id') !== null) {
                $userId = $request->input('assigned_user_id');
                $deptId = $request->input('assigned_department_id');
                json_ok(ConversationService::assign(
                    $companyId,
                    $conversationId,
                    $userId === '' || $userId === null ? null : (int) $userId,
                    $deptId === null || $deptId === '' ? null : (int) $deptId
                ));
            }
            if ($request->input('tag_id')) {
                json_ok(ConversationService::addTag($companyId, $conversationId, (int) $request->input('tag_id')));
            }
            json_ok(ConversationService::get($companyId, $conversationId));
        } catch (\Throwable $e) {
            json_error('INVALID_REQUEST', $e->getMessage(), 400);
        }
    }
}
