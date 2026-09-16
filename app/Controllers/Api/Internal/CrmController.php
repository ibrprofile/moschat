<?php

declare(strict_types=1);

namespace MosChat\Controllers\Api\Internal;

use MosChat\Core\Auth;
use MosChat\Core\Database;
use MosChat\Core\Permission;
use MosChat\Core\Request;
use MosChat\Services\AuditLog;
use MosChat\Services\ClientService;

final class CrmController
{
    public function index(Request $request): void
    {
        $this->search($request);
    }

    public function search(Request $request): void
    {
        Permission::require('clients.read');
        $companyId = (int) Auth::companyId();
        $q = trim((string) $request->input('q', ''));
        $status = trim((string) $request->input('status', ''));
        $page = max(1, (int) $request->input('page', 1));
        $limit = min(100, (int) $request->input('limit', 50));
        $offset = ($page - 1) * $limit;

        $params = [$companyId];
        $where = 'company_id = ?';
        if ($q !== '') {
            $where .= ' AND (name LIKE ? OR email LIKE ? OR phone LIKE ? OR company_name LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if ($status !== '') {
            $where .= ' AND status = ?';
            $params[] = $status;
        }

        $count = (int) (Database::fetch(
            "SELECT COUNT(*) AS c FROM clients WHERE {$where}",
            $params
        )['c'] ?? 0);

        $items = Database::fetchAll(
            "SELECT id, name, email, phone, company_name, status, source, tag, deal_value,
                    conversations_count, meetings_count, last_contact_at, created_at, updated_at
             FROM clients WHERE {$where} ORDER BY last_contact_at DESC, id DESC LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        json_ok([
            'items' => $items,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $count,
                'pages' => $limit > 0 ? (int) ceil($count / $limit) : 0,
            ],
        ]);
    }

    public function show(Request $request, string $id): void
    {
        Permission::require('clients.read');
        $client = Database::fetch(
            'SELECT * FROM clients WHERE id = ? AND company_id = ?',
            [(int) $id, Auth::companyId()]
        );
        if (!$client) {
            json_error('NOT_FOUND', 'Клиент не найден', 404);
        }
        json_ok(['client' => $client]);
    }

    public function store(Request $request): void
    {
        Permission::require('clients.write');
        try {
            $client = ClientService::findOrCreateFromContact(
                (int) Auth::companyId(),
                (string) $request->input('name', ''),
                $request->input('email') ? (string) $request->input('email') : null,
                $request->input('phone') ? (string) $request->input('phone') : null,
                'manual'
            );
            json_ok($client, [], 201);
        } catch (\Throwable $e) {
            json_error('INVALID_REQUEST', $e->getMessage(), 400);
        }
    }

    public function update(Request $request, string $id): void
    {
        Permission::require('clients.write');
        $companyId = (int) Auth::companyId();
        $client = Database::fetch('SELECT * FROM clients WHERE id = ? AND company_id = ?', [(int) $id, $companyId]);
        if (!$client) {
            json_error('NOT_FOUND', 'Клиент не найден', 404);
        }
        $data = ['updated_at' => now()];
        foreach (['name', 'email', 'phone', 'company_name', 'status'] as $field) {
            if ($request->input($field) !== null) {
                $data[$field] = $request->input($field);
            }
        }
        Database::update('clients', $data, 'id = ? AND company_id = ?', [(int) $id, $companyId]);
        json_ok(Database::fetch('SELECT * FROM clients WHERE id = ? AND company_id = ?', [(int) $id, $companyId]));
    }

    public function merge(Request $request, string $id): void
    {
        Permission::require('clients.write');
        $companyId = (int) Auth::companyId();
        $targetId = (int) $id;
        $sourceId = (int) $request->input('source_id', 0);

        if ($sourceId <= 0 || $sourceId === $targetId) {
            json_error('VALIDATION_ERROR', 'Укажите исходного клиента для объединения', 422);
        }

        $target = Database::fetch('SELECT * FROM clients WHERE id = ? AND company_id = ?', [$targetId, $companyId]);
        $source = Database::fetch('SELECT * FROM clients WHERE id = ? AND company_id = ?', [$sourceId, $companyId]);
        if (!$target || !$source) {
            json_error('NOT_FOUND', 'Клиент не найден', 404);
        }

        Database::begin();
        try {
            Database::update('conversations', ['client_id' => $targetId], 'client_id = ? AND company_id = ?', [$sourceId, $companyId]);
            Database::update('messages', ['client_id' => $targetId], 'client_id = ? AND company_id = ?', [$sourceId, $companyId]);
            Database::update('visitors', ['client_id' => $targetId], 'client_id = ? AND company_id = ?', [$sourceId, $companyId]);

            $merged = [
                'name' => $target['name'] ?: $source['name'],
                'email' => $target['email'] ?: $source['email'],
                'phone' => $target['phone'] ?: $source['phone'],
                'company_name' => $target['company_name'] ?: $source['company_name'],
                'conversations_count' => ((int) $target['conversations_count']) + ((int) $source['conversations_count']),
                'meetings_count' => ((int) $target['meetings_count']) + ((int) $source['meetings_count']),
                'updated_at' => now(),
            ];
            Database::update('clients', $merged, 'id = ? AND company_id = ?', [$targetId, $companyId]);
            Database::delete('clients', 'id = ? AND company_id = ?', [$sourceId, $companyId]);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }

        AuditLog::log('client.merged', 'client', $targetId, ['source_id' => $sourceId]);
        json_ok(Database::fetch('SELECT * FROM clients WHERE id = ? AND company_id = ?', [$targetId, $companyId]));
    }
}
