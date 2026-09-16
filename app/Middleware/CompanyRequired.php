<?php

declare(strict_types=1);

namespace MosChat\Middleware;

use MosChat\Core\Auth;
use MosChat\Core\Database;
use MosChat\Core\Request;

final class CompanyRequired
{
    public function handle(Request $request): void
    {
        if (!Auth::check()) {
            return;
        }

        $companyId = Auth::companyId();
        if ($companyId && Auth::membership()) {
            $company = Auth::company();
            if ($company && empty($company['onboarding_completed_at']) && !str_starts_with($request->path(), '/onboarding') && !str_starts_with($request->path(), '/api/internal/onboarding')) {
                if ($request->isJson()) {
                    json_error('ONBOARDING_REQUIRED', 'Завершите онбординг', 403);
                }
                redirect('/onboarding');
            }
            return;
        }

        $membership = Database::fetch(
            'SELECT company_id FROM company_members WHERE user_id = ? AND status = ? ORDER BY id ASC LIMIT 1',
            [Auth::id(), 'active']
        );

        if ($membership) {
            Auth::setCompany((int) $membership['company_id']);
            return;
        }

        if (!str_starts_with($request->path(), '/onboarding') && !str_starts_with($request->path(), '/api/internal/onboarding')) {
            if ($request->isJson()) {
                json_error('ONBOARDING_REQUIRED', 'Создайте компанию', 403);
            }
            redirect('/onboarding');
        }
    }
}
