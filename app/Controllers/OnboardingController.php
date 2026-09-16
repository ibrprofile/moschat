<?php

declare(strict_types=1);

namespace MosChat\Controllers;

use MosChat\Core\Auth;
use MosChat\Core\Csrf;
use MosChat\Core\Database;
use MosChat\Core\Request;
use MosChat\Core\Session;
use MosChat\Core\View;
use MosChat\Services\CompanyService;
use MosChat\Services\SiteService;

final class OnboardingController
{
    public function show(Request $request): void
    {
        $user = Auth::user();
        $company = Auth::company();
        $step = 1;
        $site = null;

        if ($company) {
            $step = max(2, (int) $company['onboarding_step']);
            $site = Database::fetch('SELECT * FROM sites WHERE company_id = ? ORDER BY id ASC LIMIT 1', [(int) $company['id']]);
            if ($site && $step < 3) {
                $step = 3;
            }
            if (!empty($company['onboarding_completed_at'])) {
                redirect('/app/inbox');
            }
        }

        $settings = $site ? SiteService::widgetSettings((int) $site['id']) : null;

        echo View::render('onboarding/index', [
            'title' => 'Настройка — MosChat',
            'user' => $user,
            'company' => $company,
            'site' => $site,
            'settings' => $settings,
            'step' => $step,
            'csrf' => Csrf::token(),
            'error' => Session::pullFlash('error'),
            'appUrl' => rtrim((string) config('app.url'), '/'),
        ], 'layouts/auth');
    }

    public function stepCompany(Request $request): void
    {
        try {
            $company = CompanyService::create((int) Auth::id(), [
                'name' => $request->input('name'),
                'website' => $request->input('website'),
                'timezone' => $request->input('timezone', 'Europe/Moscow'),
                'locale' => $request->input('locale', 'ru'),
            ]);
            Auth::setCompany((int) $company['id']);
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        redirect('/onboarding');
    }

    public function stepChat(Request $request): void
    {
        $company = Auth::company();
        if (!$company) {
            redirect('/onboarding');
        }

        $displayName = trim((string) $request->input('company_display_name', $company['name']));
        $welcome = trim((string) $request->input('welcome_message', 'Здравствуйте! Чем можем помочь?'));
        $color = trim((string) $request->input('primary_color', '#2563EB'));
        $position = (string) $request->input('position', 'bottom-right');

        $existing = Database::fetch('SELECT id FROM sites WHERE company_id = ? LIMIT 1', [(int) $company['id']]);
        if (!$existing) {
            SiteService::create((int) $company['id'], $displayName ?: $company['name'], $company['website'], [
                'company_display_name' => $displayName,
                'welcome_message' => $welcome,
                'primary_color' => $color,
                'position' => $position,
            ]);
        } else {
            Database::update('site_widget_settings', [
                'company_display_name' => $displayName,
                'welcome_message' => $welcome,
                'primary_color' => $color,
                'position' => $position,
                'updated_at' => now(),
            ], 'site_id = ?', [(int) $existing['id']]);
        }

        Database::update('companies', [
            'onboarding_step' => 3,
            'updated_at' => now(),
        ], 'id = ?', [(int) $company['id']]);

        redirect('/onboarding');
    }

    public function stepInstall(Request $request): void
    {
        $company = Auth::company();
        if ($company) {
            Database::update('companies', [
                'onboarding_step' => 4,
                'updated_at' => now(),
            ], 'id = ?', [(int) $company['id']]);
        }
        redirect('/onboarding');
    }

    public function complete(Request $request): void
    {
        $company = Auth::company();
        if ($company) {
            Database::update('companies', [
                'onboarding_step' => 5,
                'onboarding_completed_at' => now(),
                'updated_at' => now(),
            ], 'id = ?', [(int) $company['id']]);
        }
        redirect('/app/inbox');
    }
}
