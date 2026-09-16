<?php

declare(strict_types=1);

namespace MosChat\Controllers;

use MosChat\Core\Auth;
use MosChat\Core\Csrf;
use MosChat\Core\Database;
use MosChat\Core\RateLimiter;
use MosChat\Core\Request;
use MosChat\Core\Session;
use MosChat\Core\View;
use MosChat\Services\AuditLog;
use MosChat\Services\MailService;

final class AuthController
{
    public function showLogin(Request $request): void
    {
        echo View::render('auth/login', [
            'title' => 'Вход — MosChat',
            'error' => Session::pullFlash('error'),
            'message' => Session::pullFlash('message'),
            'csrf' => Csrf::token(),
        ], 'layouts/auth');
    }

    public function showRegister(Request $request): void
    {
        echo View::render('auth/register', [
            'title' => 'Регистрация — MosChat',
            'error' => Session::pullFlash('error'),
            'csrf' => Csrf::token(),
            'invite' => (string) $request->input('invite', ''),
        ], 'layouts/auth');
    }

    public function register(Request $request): void
    {
        if (!RateLimiter::attempt('register:' . $request->ip(), 5, 60)) {
            Session::flash('error', 'Слишком много попыток. Подождите минуту.');
            redirect('/register');
        }

        $name = trim((string) $request->input('name', ''));
        $email = mb_strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');
        $password2 = (string) $request->input('password_confirmation', '');
        $inviteToken = trim((string) $request->input('invite', ''));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            Session::flash('error', 'Проверьте имя, email и пароль (минимум 8 символов).');
            $url = '/register';
            if ($inviteToken !== '') $url .= '?invite=' . urlencode($inviteToken);
            redirect($url);
        }
        if ($password !== $password2) {
            Session::flash('error', 'Пароли не совпадают.');
            $url = '/register';
            if ($inviteToken !== '') $url .= '?invite=' . urlencode($inviteToken);
            redirect($url);
        }
        if (Database::fetch('SELECT id FROM users WHERE email = ?', [$email])) {
            Session::flash('error', 'Пользователь с таким email уже существует.');
            $url = '/register';
            if ($inviteToken !== '') $url .= '?invite=' . urlencode($inviteToken);
            redirect($url);
        }

        Database::begin();
        try {
            $userId = Database::insert('users', [
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'name' => $name,
                'timezone' => 'Europe/Moscow',
                'locale' => 'ru',
                'email_verified_at' => null,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $token = random_token(32);
            Database::insert('email_verifications', [
                'user_id' => $userId,
                'code' => $code,
                'token_hash' => hash('sha256', $token),
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400),
                'created_at' => now(),
            ]);

            $user = Database::fetch('SELECT * FROM users WHERE id = ?', [$userId]);
            if (!$user) {
                throw new \RuntimeException('User not created');
            }
            Auth::login($user);
            AuditLog::log('login', 'user', $userId, ['via' => 'register']);

            if ($inviteToken !== '') {
                try {
                    \MosChat\Services\InviteService::accept($inviteToken, $userId);
                    $membership = Database::fetch(
                        'SELECT company_id FROM company_members WHERE user_id = ? AND status = ? ORDER BY id ASC LIMIT 1',
                        [$userId, 'active']
                    );
                    if ($membership) {
                        Auth::setCompany((int) $membership['company_id']);
                    }
                } catch (\Throwable $e) {
                    Session::flash('error', 'Приглашение недействительно: ' . $e->getMessage());
                }
            }

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }

        try {
            $appName = (string) config('app.name', 'MosChat');
            $content = "<p>Здравствуйте, <strong>{$name}</strong>!</p>"
                . "<p>Ваш код подтверждения email:</p>"
                . "<div style=\"font-size:28px;font-weight:700;letter-spacing:0.15em;padding:14px 18px;background:#09090b;border:1px solid rgba(255,255,255,0.12);border-radius:10px;text-align:center;margin:14px 0;color:#ffffff;\">{$code}</div>"
                . "<p>Если вы не регистрировались в {$appName}, просто проигнорируйте это письмо.</p>";
            MailService::send($email, "Код подтверждения: {$code}", MailService::wrap($content, "Подтверждение email — {$appName}"));
        } catch (\Throwable) {
            Session::flash('dev_verify_code', $code);
        }

        redirect('/verify-email');
    }

    public function login(Request $request): void
    {
        $email = mb_strtolower(trim((string) $request->input('email', '')));
        $ip = $request->ip();

        if (!RateLimiter::attempt('login:' . $ip, 10, 60)) {
            Session::flash('error', 'Слишком много попыток входа.');
            redirect('/login');
        }

        Database::insert('login_attempts', [
            'email' => $email,
            'ip' => $ip,
            'attempted_at' => now(),
        ]);

        $user = Database::fetch('SELECT * FROM users WHERE email = ?', [$email]);
        $password = (string) $request->input('password', '');

        if (!$user || !password_verify($password, $user['password_hash']) || $user['status'] !== 'active') {
            Session::flash('error', 'Неверный email или пароль.');
            redirect('/login');
        }

        Auth::login($user);
        AuditLog::log('login', 'user', (int) $user['id']);

        $membership = Database::fetch(
            'SELECT company_id FROM company_members WHERE user_id = ? AND status = ? ORDER BY id ASC LIMIT 1',
            [(int) $user['id'], 'active']
        );
        if ($membership) {
            Auth::setCompany((int) $membership['company_id']);
            $company = Database::fetch('SELECT * FROM companies WHERE id = ?', [(int) $membership['company_id']]);
            if ($company && empty($company['onboarding_completed_at'])) {
                redirect('/onboarding');
            }
            if (empty($user['email_verified_at'])) {
                redirect('/verify-email');
            }
            redirect('/app/inbox');
        }

        if (empty($user['email_verified_at'])) {
            redirect('/verify-email');
        }
        redirect('/onboarding');
    }

    public function logout(Request $request): void
    {
        if (Auth::id()) {
            AuditLog::log('logout', 'user', Auth::id());
        }
        Auth::logout();
        redirect('/login');
    }

    public function showForgot(Request $request): void
    {
        echo View::render('auth/forgot', [
            'title' => 'Восстановление пароля — MosChat',
            'message' => Session::pullFlash('message'),
            'error' => Session::pullFlash('error'),
            'csrf' => Csrf::token(),
        ], 'layouts/auth');
    }

    public function forgot(Request $request): void
    {
        if (!RateLimiter::attempt('forgot:' . $request->ip(), 5, 60)) {
            Session::flash('error', 'Слишком много запросов.');
            redirect('/forgot-password');
        }

        $email = mb_strtolower(trim((string) $request->input('email', '')));
        $user = Database::fetch('SELECT * FROM users WHERE email = ?', [$email]);
        Session::flash('message', 'Если аккаунт существует, мы отправили инструкции на email.');

        if ($user) {
            $token = random_token(32);
            Database::insert('password_resets', [
                'user_id' => (int) $user['id'],
                'token_hash' => hash('sha256', $token),
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
                'created_at' => now(),
            ]);

            $resetUrl = url('/reset-password?token=' . urlencode($token));
            $appName = (string) config('app.name', 'MosChat');
            $content = "<p>Здравствуйте!</p>"
                . "<p>Для восстановления пароля перейдите по ссылке ниже:</p>"
                . "<p><a href=\"{$resetUrl}\" style=\"display:inline-block;padding:10px 18px;background:#2563eb;color:#ffffff;border-radius:8px;text-decoration:none;font-weight:500;\">Восстановить пароль</a></p>"
                . "<p style=\"font-size:12px;color:#a1a1aa;word-break:break-all;\">{$resetUrl}</p>"
                . "<p>Ссылка действительна 1 час. Если вы не запрашивали восстановление — проигнорируйте письмо.</p>";

            try {
                MailService::send($email, "Восстановление пароля — {$appName}", MailService::wrap($content, "Восстановление пароля"));
            } catch (\Throwable) {
                Session::flash('dev_reset_token', $token);
                Session::flash('message', 'Если аккаунт существует, используйте ссылку восстановления. (dev token сохранён в сессии)');
                redirect('/reset-password?token=' . urlencode($token));
            }
        }

        redirect('/forgot-password');
    }

    public function showReset(Request $request): void
    {
        echo View::render('auth/reset', [
            'title' => 'Новый пароль — MosChat',
            'token' => (string) $request->input('token', ''),
            'error' => Session::pullFlash('error'),
            'message' => Session::pullFlash('message'),
            'csrf' => Csrf::token(),
        ], 'layouts/auth');
    }

    public function reset(Request $request): void
    {
        $token = (string) $request->input('token', '');
        $password = (string) $request->input('password', '');
        $password2 = (string) $request->input('password_confirmation', '');

        if (strlen($password) < 8 || $password !== $password2) {
            Session::flash('error', 'Пароль минимум 8 символов и должен совпадать.');
            redirect('/reset-password?token=' . urlencode($token));
        }

        $row = Database::fetch(
            'SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1',
            [hash('sha256', $token)]
        );
        if (!$row) {
            Session::flash('error', 'Ссылка недействительна или устарела.');
            redirect('/forgot-password');
        }

        Database::update('users', [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'updated_at' => now(),
        ], 'id = ?', [(int) $row['user_id']]);
        Database::update('password_resets', ['used_at' => now()], 'id = ?', [(int) $row['id']]);

        Session::flash('message', 'Пароль обновлён. Войдите с новым паролем.');
        redirect('/login');
    }

    public function showVerify(Request $request): void
    {
        $user = Auth::user();
        if (!$user) {
            redirect('/login');
        }
        if (!empty($user['email_verified_at'])) {
            redirect('/app/inbox');
        }

        echo View::render('auth/verify', [
            'title' => 'Подтверждение email — MosChat',
            'error' => Session::pullFlash('error'),
            'message' => Session::pullFlash('message'),
            'dev_code' => Session::pullFlash('dev_verify_code'),
            'csrf' => Csrf::token(),
            'email' => $user['email'] ?? '',
        ], 'layouts/auth');
    }

    public function verify(Request $request): void
    {
        $user = Auth::user();
        if (!$user) {
            redirect('/login');
        }
        $userId = (int) $user['id'];

        if (!RateLimiter::attempt('verify:' . $userId, 10, 300)) {
            Session::flash('error', 'Слишком много попыток. Подождите 5 минут.');
            redirect('/verify-email');
        }

        $code = trim((string) $request->input('code', ''));
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            Session::flash('error', 'Код должен быть 6 цифр.');
            redirect('/verify-email');
        }

        $row = Database::fetch(
            'SELECT * FROM email_verifications WHERE user_id = ? AND expires_at > UTC_TIMESTAMP() AND used_at IS NULL ORDER BY id DESC LIMIT 1',
            [$userId]
        );
        if (!$row) {
            Session::flash('error', 'Нет активных кодов подтверждения. Запросите новый.');
            redirect('/verify-email');
        }

        if (hash_equals((string) $row['code'], $code)) {
            Database::update('users', [
                'email_verified_at' => now(),
                'updated_at' => now(),
            ], 'id = ?', [$userId]);
            Database::update('email_verifications', [
                'used_at' => now(),
            ], 'id = ?', [(int) $row['id']]);

            AuditLog::log('email.verified', 'user', $userId);

            $membership = Database::fetch(
                'SELECT company_id FROM company_members WHERE user_id = ? AND status = ? ORDER BY id ASC LIMIT 1',
                [$userId, 'active']
            );
            if ($membership) {
                Auth::setCompany((int) $membership['company_id']);
                $company = Database::fetch('SELECT * FROM companies WHERE id = ?', [(int) $membership['company_id']]);
                if ($company && empty($company['onboarding_completed_at'])) {
                    redirect('/onboarding');
                }
                redirect('/app/inbox');
            }
            redirect('/onboarding');
        }

        Session::flash('error', 'Неверный код подтверждения.');
        redirect('/verify-email');
    }

    public function resendVerification(Request $request): void
    {
        $user = Auth::user();
        if (!$user) {
            redirect('/login');
        }
        $userId = (int) $user['id'];

        if (!RateLimiter::attempt('verify-resend:' . $userId, 3, 600)) {
            Session::flash('error', 'Запрашивать код можно не чаще 3 раз за 10 минут.');
            redirect('/verify-email');
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $token = random_token(32);
        Database::insert('email_verifications', [
            'user_id' => $userId,
            'code' => $code,
            'token_hash' => hash('sha256', $token),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400),
            'created_at' => now(),
        ]);

        $appName = (string) config('app.name', 'MosChat');
        $name = $user['name'] ?? 'пользователь';
        $content = "<p>Здравствуйте, <strong>{$name}</strong>!</p>"
            . "<p>Ваш новый код подтверждения email:</p>"
            . "<div style=\"font-size:28px;font-weight:700;letter-spacing:0.15em;padding:14px 18px;background:#09090b;border:1px solid rgba(255,255,255,0.12);border-radius:10px;text-align:center;margin:14px 0;color:#ffffff;\">{$code}</div>"
            . "<p>Если вы не запрашивали код — проигнорируйте письмо.</p>";

        try {
            MailService::send((string) $user['email'], "Код подтверждения: {$code}", MailService::wrap($content, "Подтверждение email — {$appName}"));
            Session::flash('message', 'Новый код отправлен на email.');
        } catch (\Throwable) {
            Session::flash('dev_verify_code', $code);
            Session::flash('message', 'Код подтверждения (dev): ' . $code);
        }

        redirect('/verify-email');
    }
}
