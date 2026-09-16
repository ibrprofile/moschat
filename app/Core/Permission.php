<?php

declare(strict_types=1);

namespace MosChat\Core;

final class Permission
{
    /** @var array<string, bool>|null */
    private static ?array $cache = null;

    public static function can(string $permission): bool
    {
        $membership = Auth::membership();
        if (!$membership) {
            return false;
        }

        if (($membership['role_key'] ?? '') === 'owner') {
            return true;
        }

        if (self::$cache === null) {
            $rows = Database::fetchAll(
                'SELECT p.`key` FROM role_permissions rp
                 JOIN permissions p ON p.id = rp.permission_id
                 WHERE rp.role_id = ?',
                [(int) $membership['role_id']]
            );
            self::$cache = [];
            foreach ($rows as $row) {
                self::$cache[$row['key']] = true;
            }
        }

        return isset(self::$cache[$permission]);
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }

    public static function require(string $permission): void
    {
        if (!self::can($permission)) {
            if (app()->request()->isJson()) {
                json_error('FORBIDDEN', 'Недостаточно прав', 403);
            }
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            exit;
        }
    }
}
