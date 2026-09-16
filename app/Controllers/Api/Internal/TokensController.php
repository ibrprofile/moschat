<?php

declare(strict_types=1);

namespace MosChat\Controllers\Api\Internal;

use MosChat\Core\Auth;
use MosChat\Core\Database;
use MosChat\Core\Permission;
use MosChat\Core\Request;
use MosChat\Services\ApiTokenService;
use MosChat\Services\FeatureGate;

final class TokensController
{
    public function index(Request $request): void
    {
        Permission::require('api.manage');
        FeatureGate::assertCan('api');
        $companyId = (int) Auth::companyId();

        $items = Database::fetchAll(
            'SELECT id, company_id, name, token_prefix, scopes, created_by, created_at, last_used_at, revoked_at
             FROM api_tokens
             WHERE company_id = ?
             ORDER BY id DESC',
            [$companyId]
        );

        foreach ($items as &$item) {
            if (is_string($item['scopes'] ?? null)) {
                $item['scopes'] = json_decode((string) $item['scopes'], true) ?: [];
            }
        }
        unset($item);

        json_ok($items);
    }

    public function store(Request $request): void
    {
        Permission::require('api.manage');
        $companyId = (int) Auth::companyId();

        $scopes = $request->input('scopes');
        if ($scopes !== null && !is_array($scopes)) {
            $scopes = null;
        }

        try {
            $result = ApiTokenService::create(
                $companyId,
                (string) $request->input('name', ''),
                $scopes,
                Auth::id()
            );
        } catch (\InvalidArgumentException $e) {
            json_error('VALIDATION_ERROR', $e->getMessage(), 422);
        }

        json_ok([
            'token' => $result['token'],
            'plaintext' => $result['plaintext'],
        ], [], 201);
    }

    public function destroy(Request $request, string $id): void
    {
        Permission::require('api.manage');
        $companyId = (int) Auth::companyId();

        try {
            ApiTokenService::revoke($companyId, (int) $id);
        } catch (\RuntimeException $e) {
            json_error('NOT_FOUND', $e->getMessage(), 404);
        }

        json_ok(['revoked' => true]);
    }
}
