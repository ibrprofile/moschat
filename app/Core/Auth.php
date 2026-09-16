<?php

declare(strict_types=1);

namespace MosChat\Core;

final class Auth
{
    public static function user(): ?array
    {
        $id = Session::get('user_id');
        if (!$id) {
            return null;
        }
        static $cached = null;
        if ($cached !== null && (int) $cached['id'] === (int) $id) {
            return $cached;
        }
        $cached = Database::fetch('SELECT * FROM users WHERE id = ? AND status = ?', [(int) $id, 'active']);
        return $cached;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        Session::put('user_id', (int) $user['id']);
        Database::update('users', ['last_login_at' => now(), 'updated_at' => now()], 'id = ?', [(int) $user['id']]);
    }

    public static function logout(): void
    {
        Session::destroy();
        Session::start();
    }

    public static function companyId(): ?int
    {
        $id = Session::get('company_id');
        return $id ? (int) $id : null;
    }

    public static function setCompany(int $companyId): void
    {
        Session::put('company_id', $companyId);
    }

    public static function membership(): ?array
    {
        $userId = self::id();
        $companyId = self::companyId();
        if (!$userId || !$companyId) {
            return null;
        }
        return Database::fetch(
            'SELECT cm.*, r.`key` AS role_key, r.name AS role_name
             FROM company_members cm
             JOIN roles r ON r.id = cm.role_id
             WHERE cm.company_id = ? AND cm.user_id = ? AND cm.status = ?',
            [$companyId, $userId, 'active']
        );
    }

    public static function company(): ?array
    {
        $companyId = self::companyId();
        if (!$companyId) {
            return null;
        }
        return Database::fetch('SELECT * FROM companies WHERE id = ?', [$companyId]);
    }
}
