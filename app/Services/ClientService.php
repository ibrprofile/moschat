<?php

declare(strict_types=1);

namespace MosChat\Services;

use MosChat\Core\Database;

final class ClientService
{
    /**
     * @param array{q?: string|null, status?: string|null, page?: int, per_page?: int} $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public static function list(int $companyId, array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;

        $where = ['c.company_id = ?'];
        $params = [$companyId];

        if (!empty($filters['status'])) {
            $where[] = 'c.status = ?';
            $params[] = (string) $filters['status'];
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.company_name LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like);
        }

        $whereSql = implode(' AND ', $where);

        $total = (int) (Database::fetch(
            "SELECT COUNT(*) AS cnt FROM clients c WHERE {$whereSql}",
            $params
        )['cnt'] ?? 0);

        $items = Database::fetchAll(
            "SELECT c.*, u.name AS responsible_user_name, d.name AS department_name, cat.name AS category_name
             FROM clients c
             LEFT JOIN users u ON u.id = c.responsible_user_id
             LEFT JOIN departments d ON d.id = c.department_id
             LEFT JOIN client_categories cat ON cat.id = c.category_id
             WHERE {$whereSql}
             ORDER BY c.last_contact_at DESC, c.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public static function search(int $companyId, string $q, int $page = 1, int $perPage = 25): array
    {
        return self::list($companyId, [
            'q' => $q,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    public static function get(int $companyId, int $clientId): ?array
    {
        $client = Database::fetch(
            'SELECT c.*, u.name AS responsible_user_name, d.name AS department_name, cat.name AS category_name
             FROM clients c
             LEFT JOIN users u ON u.id = c.responsible_user_id
             LEFT JOIN departments d ON d.id = c.department_id
             LEFT JOIN client_categories cat ON cat.id = c.category_id
             WHERE c.company_id = ? AND c.id = ?',
            [$companyId, $clientId]
        );
        if (!$client) {
            return null;
        }

        $client['tags'] = Database::fetchAll(
            'SELECT t.* FROM tags t
             JOIN client_tags ct ON ct.tag_id = t.id
             WHERE ct.client_id = ?',
            [$clientId]
        );

        return $client;
    }

    public static function create(int $companyId, array $data): array
    {
        $name = isset($data['name']) ? trim((string) $data['name']) : null;
        $email = isset($data['email']) ? mb_strtolower(trim((string) $data['email'])) : null;
        $phone = isset($data['phone']) ? preg_replace('/\s+/', '', trim((string) $data['phone'])) : null;
        $companyName = isset($data['company_name']) ? trim((string) $data['company_name']) : null;

        if ($name === '') {
            $name = null;
        }
        if ($email === '') {
            $email = null;
        }
        if ($phone === '') {
            $phone = null;
        }
        if ($companyName === '') {
            $companyName = null;
        }

        if ($name === null && $email === null && $phone === null) {
            throw new \InvalidArgumentException('Укажите имя, email или телефон');
        }

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Некорректный email');
        }

        FeatureGate::assertWithinLimit(
            'clients',
            (int) (Database::fetch('SELECT COUNT(*) AS c FROM clients WHERE company_id = ?', [$companyId])['c'] ?? 0),
            $companyId
        );

        $clientId = Database::insert('clients', [
            'company_id' => $companyId,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'company_name' => $companyName,
            'status' => (string) ($data['status'] ?? 'lead'),
            'category_id' => isset($data['category_id']) ? (int) $data['category_id'] : null,
            'responsible_user_id' => isset($data['responsible_user_id']) ? (int) $data['responsible_user_id'] : null,
            'department_id' => isset($data['department_id']) ? (int) $data['department_id'] : null,
            'source' => (string) ($data['source'] ?? 'manual'),
            'first_contact_at' => now(),
            'last_contact_at' => now(),
            'conversations_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $client = self::get($companyId, $clientId) ?? [];
        AuditLog::log('client.created', 'client', $clientId, ['source' => $client['source'] ?? 'manual']);
        WebhookDispatcher::dispatch($companyId, 'client.created', ['client' => $client]);
        RealtimePublisher::publish('company.' . $companyId, 'client.created', ['client_id' => $clientId]);

        return $client;
    }

    public static function update(int $companyId, int $clientId, array $data): array
    {
        $client = self::get($companyId, $clientId);
        if (!$client) {
            throw new \RuntimeException('Клиент не найден');
        }

        $allowed = [
            'name', 'email', 'phone', 'company_name', 'status',
            'category_id', 'responsible_user_id', 'department_id', 'source',
        ];
        $updates = ['updated_at' => now()];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            if (in_array($field, ['name', 'company_name', 'source', 'status'], true)) {
                $value = $value === null ? null : trim((string) $value);
                if ($value === '') {
                    $value = null;
                }
            } elseif ($field === 'email') {
                $value = $value === null ? null : mb_strtolower(trim((string) $value));
                if ($value === '') {
                    $value = null;
                } elseif ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException('Некорректный email');
                }
            } elseif ($field === 'phone') {
                $value = $value === null ? null : preg_replace('/\s+/', '', trim((string) $value));
                if ($value === '') {
                    $value = null;
                }
            } elseif (in_array($field, ['category_id', 'responsible_user_id', 'department_id'], true)) {
                $value = $value === null || $value === '' ? null : (int) $value;
            }
            $updates[$field] = $value;
        }

        Database::update('clients', $updates, 'id = ? AND company_id = ?', [$clientId, $companyId]);

        $updated = self::get($companyId, $clientId) ?? [];
        AuditLog::log('client.updated', 'client', $clientId);
        WebhookDispatcher::dispatch($companyId, 'client.updated', ['client' => $updated]);
        RealtimePublisher::publish('company.' . $companyId, 'client.updated', ['client_id' => $clientId]);

        return $updated;
    }

    public static function findOrCreateFromContact(
        int $companyId,
        ?string $name,
        ?string $email,
        ?string $phone,
        string $source = 'website'
    ): array {
        $name = $name !== null ? trim($name) : null;
        $email = $email !== null ? mb_strtolower(trim($email)) : null;
        $phone = $phone !== null ? preg_replace('/\s+/', '', trim($phone)) : null;

        if ($email === '') {
            $email = null;
        }
        if ($phone === '') {
            $phone = null;
        }
        if ($name === '') {
            $name = null;
        }

        $existing = null;
        if ($email !== null) {
            $existing = Database::fetch(
                'SELECT * FROM clients WHERE company_id = ? AND email = ? LIMIT 1',
                [$companyId, $email]
            );
        }
        if (!$existing && $phone !== null) {
            $existing = Database::fetch(
                'SELECT * FROM clients WHERE company_id = ? AND phone = ? LIMIT 1',
                [$companyId, $phone]
            );
        }

        if ($existing) {
            $updates = ['last_contact_at' => now(), 'updated_at' => now()];
            if ($name !== null && ($existing['name'] === null || $existing['name'] === '')) {
                $updates['name'] = $name;
            }
            if ($email !== null && ($existing['email'] === null || $existing['email'] === '')) {
                $updates['email'] = $email;
            }
            if ($phone !== null && ($existing['phone'] === null || $existing['phone'] === '')) {
                $updates['phone'] = $phone;
            }
            Database::update('clients', $updates, 'id = ? AND company_id = ?', [
                (int) $existing['id'],
                $companyId,
            ]);
            return Database::fetch('SELECT * FROM clients WHERE id = ?', [(int) $existing['id']]) ?? $existing;
        }

        FeatureGate::assertWithinLimit(
            'clients',
            (int) (Database::fetch('SELECT COUNT(*) AS c FROM clients WHERE company_id = ?', [$companyId])['c'] ?? 0),
            $companyId
        );

        $clientId = Database::insert('clients', [
            'company_id' => $companyId,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'status' => 'lead',
            'source' => $source,
            'first_contact_at' => now(),
            'last_contact_at' => now(),
            'conversations_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $client = Database::fetch('SELECT * FROM clients WHERE id = ?', [$clientId]) ?? [];
        AuditLog::log('client.created', 'client', $clientId, ['source' => $source]);
        WebhookDispatcher::dispatch($companyId, 'client.created', ['client' => $client]);
        RealtimePublisher::publish('company.' . $companyId, 'client.created', ['client_id' => $clientId]);

        return $client;
    }

    public static function merge(int $companyId, int $primaryId, int $secondaryId): array
    {
        if ($primaryId === $secondaryId) {
            throw new \InvalidArgumentException('Нельзя объединить клиента с самим собой');
        }

        $primary = Database::fetch(
            'SELECT * FROM clients WHERE id = ? AND company_id = ?',
            [$primaryId, $companyId]
        );
        $secondary = Database::fetch(
            'SELECT * FROM clients WHERE id = ? AND company_id = ?',
            [$secondaryId, $companyId]
        );

        if (!$primary || !$secondary) {
            throw new \RuntimeException('Клиент не найден');
        }

        Database::begin();
        try {
            $fields = ['name', 'email', 'phone', 'company_name', 'avatar_path', 'category_id', 'responsible_user_id', 'department_id'];
            $updates = ['updated_at' => now(), 'last_contact_at' => now()];
            foreach ($fields as $field) {
                $p = $primary[$field] ?? null;
                $s = $secondary[$field] ?? null;
                if (($p === null || $p === '') && $s !== null && $s !== '') {
                    $updates[$field] = $s;
                }
            }
            $updates['conversations_count'] = (int) $primary['conversations_count'] + (int) $secondary['conversations_count'];

            Database::update('clients', $updates, 'id = ?', [$primaryId]);

            Database::query(
                'UPDATE conversations SET client_id = ? WHERE company_id = ? AND client_id = ?',
                [$primaryId, $companyId, $secondaryId]
            );
            Database::query(
                'UPDATE visitors SET client_id = ? WHERE company_id = ? AND client_id = ?',
                [$primaryId, $companyId, $secondaryId]
            );
            Database::query(
                'UPDATE deals SET client_id = ? WHERE company_id = ? AND client_id = ?',
                [$primaryId, $companyId, $secondaryId]
            );
            Database::query(
                'UPDATE tasks SET client_id = ? WHERE company_id = ? AND client_id = ?',
                [$primaryId, $companyId, $secondaryId]
            );

            $secTags = Database::fetchAll('SELECT tag_id FROM client_tags WHERE client_id = ?', [$secondaryId]);
            foreach ($secTags as $row) {
                $exists = Database::fetch(
                    'SELECT client_id FROM client_tags WHERE client_id = ? AND tag_id = ?',
                    [$primaryId, (int) $row['tag_id']]
                );
                if (!$exists) {
                    Database::query(
                        'INSERT INTO client_tags (client_id, tag_id) VALUES (?, ?)',
                        [$primaryId, (int) $row['tag_id']]
                    );
                }
            }
            Database::query('DELETE FROM client_tags WHERE client_id = ?', [$secondaryId]);

            $secFields = Database::fetchAll(
                'SELECT field_id, value FROM client_custom_field_values WHERE client_id = ?',
                [$secondaryId]
            );
            foreach ($secFields as $row) {
                $exists = Database::fetch(
                    'SELECT id FROM client_custom_field_values WHERE client_id = ? AND field_id = ?',
                    [$primaryId, (int) $row['field_id']]
                );
                if (!$exists) {
                    Database::insert('client_custom_field_values', [
                        'client_id' => $primaryId,
                        'field_id' => (int) $row['field_id'],
                        'value' => $row['value'],
                    ]);
                }
            }
            Database::query('DELETE FROM client_custom_field_values WHERE client_id = ?', [$secondaryId]);

            Database::query('DELETE FROM clients WHERE id = ? AND company_id = ?', [$secondaryId, $companyId]);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }

        AuditLog::log('client.merged', 'client', $primaryId, [
            'secondary_id' => $secondaryId,
        ]);

        $merged = Database::fetch('SELECT * FROM clients WHERE id = ?', [$primaryId]) ?? [];
        WebhookDispatcher::dispatch($companyId, 'client.updated', ['client' => $merged, 'merged_from' => $secondaryId]);
        RealtimePublisher::publish('company.' . $companyId, 'client.updated', [
            'client_id' => $primaryId,
            'merged_from' => $secondaryId,
        ]);

        return $merged;
    }
}
