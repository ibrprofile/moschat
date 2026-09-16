<?php

declare(strict_types=1);

namespace MosChat\Services;

use MosChat\Core\Database;

final class CompanyService
{
    public static function createCompany(int $userId, array $data): array
    {
        return self::create($userId, $data);
    }

    public static function create(int $userId, array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $website = trim((string) ($data['website'] ?? ''));
        $timezone = trim((string) ($data['timezone'] ?? 'Europe/Moscow'));
        $locale = trim((string) ($data['locale'] ?? 'ru'));

        if ($name === '') {
            throw new \InvalidArgumentException('Укажите название компании');
        }

        $baseSlug = slugify($name);
        $slug = $baseSlug;
        $i = 1;
        while (Database::fetch('SELECT id FROM companies WHERE slug = ?', [$slug])) {
            $slug = $baseSlug . '-' . $i++;
        }

        Database::begin();
        try {
            $companyId = Database::insert('companies', [
                'name' => $name,
                'slug' => $slug,
                'website' => $website !== '' ? $website : null,
                'timezone' => $timezone,
                'locale' => $locale,
                'onboarding_step' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $ownerRole = Database::fetch('SELECT id FROM roles WHERE company_id IS NULL AND `key` = ?', ['owner']);
            if (!$ownerRole) {
                throw new \RuntimeException('Системная роль owner не найдена. Запустите миграции.');
            }

            Database::insert('company_members', [
                'company_id' => $companyId,
                'user_id' => $userId,
                'role_id' => (int) $ownerRole['id'],
                'status' => 'active',
                'presence' => 'online',
                'last_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $freePlan = Database::fetch('SELECT id FROM plans WHERE `key` = ?', ['free']);
            if ($freePlan) {
                Database::insert('subscriptions', [
                    'company_id' => $companyId,
                    'plan_id' => (int) $freePlan['id'],
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $deptId = Database::insert('departments', [
                'company_id' => $companyId,
                'name' => 'Поддержка',
                'description' => 'Основной отдел',
                'color' => '#2563EB',
                'icon' => 'headset',
                'is_default' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            Database::query(
                'INSERT INTO department_members (department_id, user_id) VALUES (?, ?)',
                [$deptId, $userId]
            );

            $pipelineId = Database::insert('pipelines', [
                'company_id' => $companyId,
                'name' => 'Основная',
                'is_default' => 1,
                'created_at' => now(),
            ]);
            $stages = [
                ['Новая', 'new', 0, '#6B7280'],
                ['В работе', 'in_progress', 1, '#2563EB'],
                ['Выиграна', 'won', 2, '#16A34A'],
                ['Проиграна', 'lost', 3, '#DC2626'],
            ];
            foreach ($stages as [$sName, $sKey, $pos, $color]) {
                Database::insert('pipeline_stages', [
                    'pipeline_id' => $pipelineId,
                    'name' => $sName,
                    'stage_key' => $sKey,
                    'position' => $pos,
                    'color' => $color,
                ]);
            }

            foreach ([['Lead', '#2563EB'], ['VIP', '#D97706'], ['Hot', '#DC2626'], ['Support', '#0284C7']] as [$tag, $color]) {
                Database::insert('tags', [
                    'company_id' => $companyId,
                    'name' => $tag,
                    'color' => $color,
                    'created_at' => now(),
                ]);
            }

            Database::insert('client_categories', [
                'company_id' => $companyId,
                'name' => 'Клиент',
                'color' => '#6B7280',
                'description' => 'Основная категория',
                'created_at' => now(),
            ]);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }

        AuditLog::log('company.created', 'company', $companyId);
        return Database::fetch('SELECT * FROM companies WHERE id = ?', [$companyId]) ?? [];
    }
}
