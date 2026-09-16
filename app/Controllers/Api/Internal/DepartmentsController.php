<?php

declare(strict_types=1);

namespace MosChat\Controllers\Api\Internal;

use MosChat\Core\Auth;
use MosChat\Core\Database;
use MosChat\Core\Permission;
use MosChat\Core\Request;
use MosChat\Services\AuditLog;
use MosChat\Services\FeatureGate;

final class DepartmentsController
{
    public function index(Request $request): void
    {
        Permission::require('departments.read');
        $companyId = (int) Auth::companyId();

        $items = Database::fetchAll(
            'SELECT d.*,
                    (SELECT COUNT(*) FROM department_members dm WHERE dm.department_id = d.id) AS members_count
             FROM departments d
             WHERE d.company_id = ?
             ORDER BY d.is_default DESC, d.name ASC',
            [$companyId]
        );

        json_ok($items);
    }

    public function store(Request $request): void
    {
        Permission::require('departments.write');
        $companyId = (int) Auth::companyId();
        FeatureGate::assertCan('departments', $companyId);

        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            json_error('VALIDATION_ERROR', 'Укажите название отдела', 422);
        }

        $id = Database::insert('departments', [
            'company_id' => $companyId,
            'name' => $name,
            'description' => trim((string) $request->input('description', '')) ?: null,
            'color' => trim((string) $request->input('color', '#2563EB')) ?: '#2563EB',
            'icon' => trim((string) $request->input('icon', '')) ?: null,
            'is_default' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditLog::log('department.created', 'department', $id);
        $dept = Database::fetch('SELECT * FROM departments WHERE id = ?', [$id]);
        json_ok(['department' => $dept], [], 201);
    }
}
