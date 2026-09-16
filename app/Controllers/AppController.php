<?php

declare(strict_types=1);

namespace MosChat\Controllers;

use MosChat\Core\Auth;
use MosChat\Core\Csrf;
use MosChat\Core\Database;
use MosChat\Core\Permission;
use MosChat\Core\Request;
use MosChat\Core\View;
use MosChat\Services\FeatureGate;

final class AppController
{
    private function shell(string $view, string $nav, array $extra = []): void
    {
        $user = Auth::user();
        $company = Auth::company();
        $membership = Auth::membership();
        $plan = FeatureGate::plan();

        echo View::render($view, array_merge([
            'title' => ($extra['pageTitle'] ?? 'MosChat') . ' — MosChat',
            'pageTitle' => $extra['pageTitle'] ?? 'MosChat',
            'user' => $user,
            'company' => $company,
            'membership' => $membership,
            'plan' => $plan,
            'nav' => $nav,
            'csrf' => Csrf::token(),
        ], $extra), 'layouts/app');
    }

    public function inbox(Request $request, ?string $id = null): void
    {
        Permission::require('inbox.read');
        $this->shell('app/inbox', 'inbox', [
            'pageTitle' => 'Inbox',
            'conversationId' => $id,
        ]);
    }

    public function crm(Request $request): void
    {
        Permission::require('clients.read');
        $this->shell('app/crm/index', 'crm', ['pageTitle' => 'CRM']);
    }

    public function client(Request $request, string $id): void
    {
        Permission::require('clients.read');
        $this->shell('app/crm/client', 'crm', [
            'pageTitle' => 'Клиент',
            'clientId' => $id,
        ]);
    }

    public function visitors(Request $request): void
    {
        Permission::require('visitors.read');
        $this->shell('app/visitors', 'visitors', ['pageTitle' => 'Посетители']);
    }

    public function analytics(Request $request): void
    {
        Permission::require('analytics.read');
        $this->shell('app/analytics', 'analytics', ['pageTitle' => 'Аналитика']);
    }

    public function employees(Request $request): void
    {
        Permission::require('employees.read');
        $this->shell('app/employees', 'employees', ['pageTitle' => 'Сотрудники']);
    }

    public function departments(Request $request): void
    {
        Permission::require('departments.read');
        $this->shell('app/departments', 'departments', ['pageTitle' => 'Отделы']);
    }

    public function settings(Request $request, ?string $section = null): void
    {
        Permission::require('settings.manage');
        $section = $section ?: 'general';
        $allowed = ['general', 'chat', 'widget', 'team', 'crm', 'security', 'billing', 'api', 'privacy'];
        if (!in_array($section, $allowed, true)) {
            $section = 'general';
        }
        $site = Database::fetch('SELECT * FROM sites WHERE company_id = ? ORDER BY id ASC LIMIT 1', [Auth::companyId()]);
        $settings = $site ? Database::fetch('SELECT * FROM site_widget_settings WHERE site_id = ?', [(int) $site['id']]) : null;
        $this->shell('app/settings/' . $section, 'settings', [
            'pageTitle' => 'Настройки',
            'section' => $section,
            'site' => $site,
            'widgetSettings' => $settings,
            'appUrl' => rtrim((string) config('app.url'), '/'),
        ]);
    }

    public function docs(Request $request): void
    {
        $this->shell('app/docs', 'docs', ['pageTitle' => 'API & Documentation']);
    }

    public function profile(Request $request): void
    {
        $this->shell('app/profile', 'profile', ['pageTitle' => 'Профиль']);
    }
}
