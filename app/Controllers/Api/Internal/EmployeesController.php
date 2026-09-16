<?php

declare(strict_types=1);

namespace MosChat\Controllers\Api\Internal;

use MosChat\Core\Auth;
use MosChat\Core\Database;
use MosChat\Core\Permission;
use MosChat\Core\Request;
use MosChat\Services\InviteService;

final class EmployeesController
{
    public function index(Request $request): void
    {
        Permission::require('employees.read');
        $companyId = (int) Auth::companyId();
        $q = trim((string) $request->input('q', ''));

        $where = 'cm.company_id = ?';
        $params = [$companyId];
        if ($q !== '') {
            $where .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $items = Database::fetchAll(
            "SELECT cm.id, cm.user_id, cm.status, cm.presence, cm.last_seen_at, cm.created_at,
                    u.name, u.email, u.avatar_path,
                    r.`key` AS role_key, r.name AS role_name
             FROM company_members cm
             JOIN users u ON u.id = cm.user_id
             JOIN roles r ON r.id = cm.role_id
             WHERE {$where}
             ORDER BY u.name ASC",
            $params
        );

        $invites = Database::fetchAll(
            'SELECT i.id, i.email, i.expires_at, i.created_at, r.name AS role_name, r.`key` AS role_key
             FROM invitations i
             JOIN roles r ON r.id = i.role_id
             WHERE i.company_id = ? AND i.accepted_at IS NULL AND i.expires_at > UTC_TIMESTAMP()
             ORDER BY i.id DESC',
            [$companyId]
        );

        $roles = Database::fetchAll(
            'SELECT id, `key`, name FROM roles WHERE company_id IS NULL OR company_id = ? ORDER BY id ASC',
            [$companyId]
        );

        json_ok([
            'employees' => $items,
            'invitations' => $invites,
            'roles' => $roles,
        ]);
    }

    public function invite(Request $request): void
    {
        Permission::require('employees.write');
        $companyId = (int) Auth::companyId();

        $email = (string) $request->input('email', '');
        $roleId = (int) $request->input('role_id', 0);

        if ($roleId <= 0) {
            $roleKey = (string) $request->input('role', 'agent');
            $role = Database::fetch(
                'SELECT id FROM roles WHERE company_id IS NULL AND `key` = ?',
                [$roleKey]
            );
            $roleId = $role ? (int) $role['id'] : 0;
        }

        if ($roleId <= 0) {
            json_error('VALIDATION_ERROR', 'Укажите роль', 422);
        }

        try {
            $result = InviteService::create($companyId, $email, $roleId, Auth::id());
        } catch (\InvalidArgumentException $e) {
            json_error('VALIDATION_ERROR', $e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            json_error('SERVER_ERROR', $e->getMessage(), 400);
        }

        json_ok([
            'invitation' => $result['invitation'],
            'invite_token' => $result['token'],
            'invite_url' => url('/register?invite=' . urlencode($result['token'])),
        ], [], 201);
    }
}
