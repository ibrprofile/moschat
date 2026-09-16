<?php

declare(strict_types=1);

use MosChat\Controllers\Api\Internal\AnalyticsController;
use MosChat\Controllers\Api\Internal\CrmController;
use MosChat\Controllers\Api\Internal\DepartmentsController;
use MosChat\Controllers\Api\Internal\EmployeesController;
use MosChat\Controllers\Api\Internal\InboxController;
use MosChat\Controllers\Api\Internal\RealtimeController as InternalRealtimeController;
use MosChat\Controllers\Api\Internal\SettingsController;
use MosChat\Controllers\Api\Internal\TokensController;
use MosChat\Controllers\Api\Internal\VisitorsController;
use MosChat\Controllers\Api\Internal\WebhooksController;
use MosChat\Controllers\Api\V1\ApiV1Controller;
use MosChat\Controllers\RealtimeController;
use MosChat\Core\Auth;
use MosChat\Core\Database;

$router = app()->router();

$session = ['AuthRequired', 'CompanyRequired'];
$sessionWrite = ['AuthRequired', 'CompanyRequired', 'VerifyCsrf'];

// ─── Internal session API ───────────────────────────────────────────

$router->get('/api/internal/me', static function () {
    if (!Auth::check()) {
        json_error('UNAUTHORIZED', 'Требуется вход', 401);
    }
    $companies = Database::fetchAll(
        'SELECT c.id, c.name, c.slug, r.`key` AS role_key
         FROM company_members cm
         JOIN companies c ON c.id = cm.company_id
         JOIN roles r ON r.id = cm.role_id
         WHERE cm.user_id = ? AND cm.status = ?
         ORDER BY c.name ASC',
        [Auth::id(), 'active']
    );
    json_ok([
        'user' => Auth::user(),
        'company' => Auth::company(),
        'membership' => Auth::membership(),
        'companies' => $companies,
    ]);
}, ['AuthRequired']);

// Inbox
$router->get('/api/internal/inbox/conversations', [InboxController::class, 'index'], $session);
$router->get('/api/internal/inbox/conversations/{id}', [InboxController::class, 'show'], $session);
$router->post('/api/internal/inbox/conversations/{id}/messages', [InboxController::class, 'message'], $sessionWrite);
$router->patch('/api/internal/inbox/conversations/{id}', [InboxController::class, 'update'], $sessionWrite);

// CRM
$router->get('/api/internal/crm/clients', [CrmController::class, 'index'], $session);
$router->get('/api/internal/crm/clients/search', [CrmController::class, 'search'], $session);
$router->post('/api/internal/crm/clients', [CrmController::class, 'store'], $sessionWrite);
$router->get('/api/internal/crm/clients/{id}', [CrmController::class, 'show'], $session);
$router->patch('/api/internal/crm/clients/{id}', [CrmController::class, 'update'], $sessionWrite);
$router->post('/api/internal/crm/clients/{id}/merge', [CrmController::class, 'merge'], $sessionWrite);

// Visitors
$router->get('/api/internal/visitors', [VisitorsController::class, 'index'], $session);
$router->post('/api/internal/visitors/{id}/start-chat', [VisitorsController::class, 'startChat'], $sessionWrite);

// Employees / departments
$router->get('/api/internal/employees', [EmployeesController::class, 'index'], $session);
$router->post('/api/internal/employees/invite', [EmployeesController::class, 'invite'], $sessionWrite);
$router->get('/api/internal/departments', [DepartmentsController::class, 'index'], $session);
$router->post('/api/internal/departments', [DepartmentsController::class, 'store'], $sessionWrite);

// Analytics
$router->get('/api/internal/analytics/summary', [AnalyticsController::class, 'summary'], $session);

// Settings
$router->patch('/api/internal/settings/general', [SettingsController::class, 'general'], $sessionWrite);
$router->post('/api/internal/settings/general', [SettingsController::class, 'general'], $sessionWrite);
$router->patch('/api/internal/settings/chat', [SettingsController::class, 'chat'], $sessionWrite);
$router->post('/api/internal/settings/chat', [SettingsController::class, 'chat'], $sessionWrite);
$router->patch('/api/internal/settings/widget', [SettingsController::class, 'widget'], $sessionWrite);
$router->post('/api/internal/settings/widget', [SettingsController::class, 'widget'], $sessionWrite);
$router->patch('/api/internal/settings/privacy', [SettingsController::class, 'privacy'], $sessionWrite);
$router->post('/api/internal/settings/privacy', [SettingsController::class, 'privacy'], $sessionWrite);
$router->post('/api/internal/billing/plan', [SettingsController::class, 'billingPlan'], $sessionWrite);
$router->post('/api/internal/profile/password', [SettingsController::class, 'password'], $sessionWrite);

// API tokens & webhooks
$router->get('/api/internal/api-tokens', [TokensController::class, 'index'], $session);
$router->post('/api/internal/api-tokens', [TokensController::class, 'store'], $sessionWrite);
$router->delete('/api/internal/api-tokens/{id}', [TokensController::class, 'destroy'], $sessionWrite);

$router->get('/api/internal/webhooks', [WebhooksController::class, 'index'], $session);
$router->post('/api/internal/webhooks', [WebhooksController::class, 'store'], $sessionWrite);
$router->patch('/api/internal/webhooks/{id}', [WebhooksController::class, 'update'], $sessionWrite);
$router->delete('/api/internal/webhooks/{id}', [WebhooksController::class, 'destroy'], $sessionWrite);
$router->get('/api/internal/webhooks/{id}/deliveries', [WebhooksController::class, 'deliveries'], $session);

// Realtime (SSE + poll fallback)
$router->get('/api/internal/realtime', [InternalRealtimeController::class, 'stream'], $session);
$router->get('/api/internal/realtime/poll', [InternalRealtimeController::class, 'poll'], $session);
$router->get('/realtime/app', [RealtimeController::class, 'app'], $session);
$router->get('/realtime/widget', [RealtimeController::class, 'widget']);

// ─── Public REST API v1 (Bearer) ────────────────────────────────────

$router->get('/api/v1/clients', [ApiV1Controller::class, 'clientsIndex']);
$router->post('/api/v1/clients', [ApiV1Controller::class, 'clientsStore']);
$router->get('/api/v1/clients/{id}', [ApiV1Controller::class, 'clientsShow']);
$router->patch('/api/v1/clients/{id}', [ApiV1Controller::class, 'clientsUpdate']);
$router->delete('/api/v1/clients/{id}', [ApiV1Controller::class, 'clientsDestroy']);

$router->get('/api/v1/conversations', [ApiV1Controller::class, 'conversationsIndex']);
$router->get('/api/v1/conversations/{id}', [ApiV1Controller::class, 'conversationsShow']);
$router->patch('/api/v1/conversations/{id}', [ApiV1Controller::class, 'conversationsUpdate']);
$router->get('/api/v1/conversations/{id}/messages', [ApiV1Controller::class, 'messagesIndex']);
$router->post('/api/v1/conversations/{id}/messages', [ApiV1Controller::class, 'messagesStore']);
