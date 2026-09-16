<?php

declare(strict_types=1);

namespace MosChat\Services;

use MosChat\Core\Auth;
use MosChat\Core\Database;
use MosChat\Services\MailService;

final class InviteService
{
    /**
     * @return array{invitation: array<string, mixed>, token: string}
     */
    public static function create(int $companyId, string $email, int $roleId, ?int $invitedBy = null): array
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Некорректный email');
        }

        $role = Database::fetch(
            'SELECT * FROM roles WHERE id = ? AND (company_id IS NULL OR company_id = ?)',
            [$roleId, $companyId]
        );
        if (!$role) {
            throw new \InvalidArgumentException('Роль не найдена');
        }
        if (($role['key'] ?? '') === 'owner') {
            throw new \InvalidArgumentException('Нельзя пригласить владельца');
        }

        $existingMember = Database::fetch(
            'SELECT id, status FROM company_members cm
             JOIN users u ON u.id = cm.user_id
             WHERE cm.company_id = ? AND u.email = ?',
            [$companyId, $email]
        );
        if ($existingMember && $existingMember['status'] === 'active') {
            throw new \InvalidArgumentException('Пользователь уже в компании');
        }

        $memberCount = (int) (Database::fetch(
            'SELECT COUNT(*) AS c FROM company_members WHERE company_id = ? AND status = ?',
            [$companyId, 'active']
        )['c'] ?? 0);
        $pendingInvites = (int) (Database::fetch(
            'SELECT COUNT(*) AS c FROM invitations
             WHERE company_id = ? AND accepted_at IS NULL AND expires_at > UTC_TIMESTAMP()',
            [$companyId]
        )['c'] ?? 0);

        FeatureGate::assertWithinLimit('employees', $memberCount + $pendingInvites, $companyId);

        $invitedBy ??= Auth::id();
        if (!$invitedBy) {
            throw new \RuntimeException('Не указан приглашающий');
        }

        $token = random_token(32);
        $invitationId = Database::insert('invitations', [
            'company_id' => $companyId,
            'email' => $email,
            'role_id' => $roleId,
            'token_hash' => hash('sha256', $token),
            'invited_by' => $invitedBy,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 7 * 86400),
            'created_at' => now(),
        ]);

        $invitation = Database::fetch('SELECT * FROM invitations WHERE id = ?', [$invitationId]) ?? [];
        AuditLog::log('invite.created', 'invitation', $invitationId, [
            'email' => $email,
            'role_id' => $roleId,
        ]);

        $company = Database::fetch('SELECT * FROM companies WHERE id = ?', [$companyId]);
        $inviter = $invitedBy ? Database::fetch('SELECT * FROM users WHERE id = ?', [$invitedBy]) : null;
        $inviteUrl = url('/register?invite=' . urlencode($token));
        $appName = (string) config('app.name', 'MosChat');
        $companyName = $company['name'] ?? 'компания';
        $inviterName = $inviter['name'] ?? 'Команда MosChat';
        $roleName = $role['name'] ?? 'сотрудник';

        $content = "<p>Здравствуйте!</p>"
            . "<p><strong>{$inviterName}</strong> приглашает вас присоединиться к команде <strong>{$companyName}</strong> в {$appName} в роли <strong>{$roleName}</strong>.</p>"
            . "<p><a href=\"{$inviteUrl}\" style=\"display:inline-block;padding:10px 20px;background:#2563eb;color:#ffffff;border-radius:8px;text-decoration:none;font-weight:600;\">Принять приглашение</a></p>"
            . "<p style=\"font-size:12px;color:#a1a1aa;word-break:break-all;\">{$inviteUrl}</p>"
            . "<p>Ссылка действительна 7 дней. Если вы не ожидали этого письма — просто проигнорируйте его.</p>";

        try {
            MailService::send($email, "Приглашение в {$companyName} — {$appName}", MailService::wrap($content, "Приглашение в команду"));
        } catch (\Throwable) {
        }

        return [
            'invitation' => $invitation,
            'token' => $token,
            'invite_url' => $inviteUrl,
        ];
    }

    public static function accept(string $token, int $userId): array
    {
        $hash = hash('sha256', $token);
        $invitation = Database::fetch(
            'SELECT * FROM invitations
             WHERE token_hash = ? AND accepted_at IS NULL AND expires_at > UTC_TIMESTAMP()
             ORDER BY id DESC LIMIT 1',
            [$hash]
        );
        if (!$invitation) {
            throw new \RuntimeException('Приглашение недействительно или устарело');
        }

        $user = Database::fetch('SELECT * FROM users WHERE id = ? AND status = ?', [$userId, 'active']);
        if (!$user) {
            throw new \RuntimeException('Пользователь не найден');
        }

        if (mb_strtolower((string) $user['email']) !== mb_strtolower((string) $invitation['email'])) {
            throw new \RuntimeException('Email не совпадает с приглашением');
        }

        $companyId = (int) $invitation['company_id'];

        $memberCount = (int) (Database::fetch(
            'SELECT COUNT(*) AS c FROM company_members WHERE company_id = ? AND status = ?',
            [$companyId, 'active']
        )['c'] ?? 0);
        FeatureGate::assertWithinLimit('employees', $memberCount, $companyId);

        Database::begin();
        try {
            $existing = Database::fetch(
                'SELECT * FROM company_members WHERE company_id = ? AND user_id = ?',
                [$companyId, $userId]
            );

            if ($existing) {
                Database::update('company_members', [
                    'role_id' => (int) $invitation['role_id'],
                    'status' => 'active',
                    'updated_at' => now(),
                ], 'id = ?', [(int) $existing['id']]);
            } else {
                Database::insert('company_members', [
                    'company_id' => $companyId,
                    'user_id' => $userId,
                    'role_id' => (int) $invitation['role_id'],
                    'status' => 'active',
                    'presence' => 'offline',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Database::update('invitations', [
                'accepted_at' => now(),
            ], 'id = ?', [(int) $invitation['id']]);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }

        AuditLog::log('invite.accepted', 'invitation', (int) $invitation['id'], [
            'user_id' => $userId,
            'company_id' => $companyId,
        ]);

        $membership = Database::fetch(
            'SELECT cm.*, r.`key` AS role_key, r.name AS role_name
             FROM company_members cm
             JOIN roles r ON r.id = cm.role_id
             WHERE cm.company_id = ? AND cm.user_id = ?',
            [$companyId, $userId]
        );

        WebhookDispatcher::dispatch($companyId, 'employee.created', [
            'user_id' => $userId,
            'membership' => $membership,
        ]);

        return $membership ?? [];
    }
}
