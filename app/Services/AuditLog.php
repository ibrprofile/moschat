<?php

declare(strict_types=1);

namespace MosChat\Services;

use MosChat\Core\Auth;
use MosChat\Core\Database;

final class AuditLog
{
    public static function log(string $action, ?string $entityType = null, ?int $entityId = null, array $meta = []): void
    {
        try {
            Database::insert('audit_logs', [
                'company_id' => Auth::companyId(),
                'user_id' => Auth::id(),
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'ip' => app()->request()->ip(),
                'meta' => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Never break main flow on audit failure
        }
    }
}
