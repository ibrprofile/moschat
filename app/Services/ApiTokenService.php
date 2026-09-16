<?php

declare(strict_types=1);

namespace MosChat\Services;

use MosChat\Core\Auth;
use MosChat\Core\Database;

final class ApiTokenService
{
    /**
     * @param list<string>|null $scopes
     * @return array{token: array<string, mixed>, plaintext: string}
     */
    public static function create(int $companyId, string $name, ?array $scopes = null, ?int $createdBy = null): array
    {
        FeatureGate::assertCan('api', $companyId);

        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Укажите название токена');
        }

        $createdBy ??= Auth::id();
        $prefix = substr(random_token(8), 0, 8);
        $secret = random_token(24);
        $plaintext = 'sk_live_' . $prefix . '_' . $secret;

        $tokenId = Database::insert('api_tokens', [
            'company_id' => $companyId,
            'name' => $name,
            'token_prefix' => $prefix,
            'token_hash' => password_hash($plaintext, PASSWORD_DEFAULT),
            'scopes' => $scopes === null ? null : json_encode(array_values($scopes), JSON_UNESCAPED_UNICODE),
            'created_by' => $createdBy,
            'created_at' => now(),
        ]);

        $token = Database::fetch('SELECT id, company_id, name, token_prefix, scopes, created_by, created_at, last_used_at, revoked_at FROM api_tokens WHERE id = ?', [$tokenId]) ?? [];
        AuditLog::log('api_token.created', 'api_token', $tokenId, ['name' => $name]);

        return [
            'token' => $token,
            'plaintext' => $plaintext,
        ];
    }

    public static function verify(?string $bearer): ?array
    {
        return self::verifyBearer($bearer);
    }

    public static function verifyBearer(?string $bearer): ?array
    {
        if ($bearer === null || $bearer === '') {
            return null;
        }

        // Expected: sk_live_{prefix}_{secret}
        if (!preg_match('/^sk_live_([a-f0-9]{8})_(.+)$/', $bearer, $m)) {
            return null;
        }

        $prefix = $m[1];
        $candidates = Database::fetchAll(
            'SELECT * FROM api_tokens WHERE token_prefix = ? AND revoked_at IS NULL',
            [$prefix]
        );

        foreach ($candidates as $row) {
            if (!password_verify($bearer, (string) $row['token_hash'])) {
                continue;
            }

            Database::update('api_tokens', [
                'last_used_at' => now(),
            ], 'id = ?', [(int) $row['id']]);

            if ($row['scopes'] !== null && is_string($row['scopes'])) {
                $row['scopes'] = json_decode($row['scopes'], true) ?: [];
            }

            return $row;
        }

        return null;
    }

    public static function revoke(int $companyId, int $tokenId): void
    {
        $token = Database::fetch(
            'SELECT * FROM api_tokens WHERE id = ? AND company_id = ? AND revoked_at IS NULL',
            [$tokenId, $companyId]
        );
        if (!$token) {
            throw new \RuntimeException('Токен не найден');
        }

        Database::update('api_tokens', [
            'revoked_at' => now(),
        ], 'id = ?', [$tokenId]);

        AuditLog::log('api_token.revoked', 'api_token', $tokenId);
    }
}
