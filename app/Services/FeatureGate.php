<?php

declare(strict_types=1);

namespace MosChat\Services;

use MosChat\Core\Auth;
use MosChat\Core\Database;

final class FeatureGate
{
    /** @var array<string, mixed>|null */
    private static ?array $planCache = null;

    public static function can(string $feature, ?int $companyId = null): bool
    {
        $plan = self::plan($companyId);
        $features = $plan['features'] ?? [];
        return !empty($features[$feature]);
    }

    public static function limit(string $key, ?int $companyId = null): int
    {
        $plan = self::plan($companyId);
        $limits = $plan['limits'] ?? [];
        return (int) ($limits[$key] ?? 0);
    }

    public static function assertCan(string $feature, ?int $companyId = null): void
    {
        if (!self::can($feature, $companyId)) {
            json_error('PLAN_LIMIT', 'Функция доступна на тарифе PRO', 402);
        }
    }

    public static function assertWithinLimit(string $limitKey, int $current, ?int $companyId = null): void
    {
        $max = self::limit($limitKey, $companyId);
        if ($max >= 0 && $current >= $max) {
            json_error('PLAN_LIMIT', 'Достигнут лимит тарифа', 402, ['limit' => $limitKey, 'max' => $max]);
        }
    }

    /** @return array{key: string, features: array, limits: array} */
    public static function plan(?int $companyId = null): array
    {
        $companyId ??= Auth::companyId();
        if (!$companyId) {
            return self::freeDefaults();
        }

        if (self::$planCache !== null && (int) (self::$planCache['_company_id'] ?? 0) === $companyId) {
            return self::$planCache;
        }

        $row = Database::fetch(
            'SELECT p.`key`, p.features, p.limits_json
             FROM subscriptions s
             JOIN plans p ON p.id = s.plan_id
             WHERE s.company_id = ? AND s.status IN ("active","trialing")
             ORDER BY s.id DESC LIMIT 1',
            [$companyId]
        );

        if (!$row) {
            $plan = self::freeDefaults();
        } else {
            $plan = [
                'key' => $row['key'],
                'features' => json_decode((string) $row['features'], true) ?: [],
                'limits' => json_decode((string) $row['limits_json'], true) ?: [],
                '_company_id' => $companyId,
            ];
        }

        self::$planCache = $plan;
        return $plan;
    }

    /** @return array{key: string, features: array, limits: array} */
    private static function freeDefaults(): array
    {
        return [
            'key' => 'free',
            'features' => [
                'api' => false,
                'webhooks' => false,
                'departments' => false,
                'saved_replies' => false,
                'advanced_analytics' => false,
                'remove_branding' => false,
                'routing' => false,
            ],
            'limits' => [
                'sites' => 1,
                'employees' => 1,
                'clients' => 200,
                'conversations_month' => 300,
                'history_days' => 30,
            ],
        ];
    }
}
