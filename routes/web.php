<?php

declare(strict_types=1);

use MosChat\Controllers\AppController;
use MosChat\Controllers\AuthController;
use MosChat\Controllers\OnboardingController;

$router = app()->router();

$router->get('/', static function () {
    if (\MosChat\Core\Auth::check()) {
        redirect('/app/inbox');
    }
    redirect('/login');
});

$router->get('/login', [AuthController::class, 'showLogin'], ['GuestOnly']);
$router->post('/login', [AuthController::class, 'login'], ['GuestOnly', 'VerifyCsrf']);
$router->get('/register', [AuthController::class, 'showRegister'], ['GuestOnly']);
$router->post('/register', [AuthController::class, 'register'], ['GuestOnly', 'VerifyCsrf']);
$router->post('/logout', [AuthController::class, 'logout'], ['AuthRequired', 'VerifyCsrf']);
$router->get('/forgot-password', [AuthController::class, 'showForgot'], ['GuestOnly']);
$router->post('/forgot-password', [AuthController::class, 'forgot'], ['GuestOnly', 'VerifyCsrf']);
$router->get('/reset-password', [AuthController::class, 'showReset'], ['GuestOnly']);
$router->post('/reset-password', [AuthController::class, 'reset'], ['GuestOnly', 'VerifyCsrf']);

$router->get('/verify-email', [AuthController::class, 'showVerify'], ['AuthRequired']);
$router->post('/verify-email', [AuthController::class, 'verify'], ['AuthRequired', 'VerifyCsrf']);
$router->post('/verify-email/resend', [AuthController::class, 'resendVerification'], ['AuthRequired', 'VerifyCsrf']);

$router->get('/onboarding', [OnboardingController::class, 'show'], ['AuthRequired', 'CompanyRequired']);
$router->post('/onboarding/company', [OnboardingController::class, 'stepCompany'], ['AuthRequired', 'VerifyCsrf']);
$router->post('/onboarding/chat', [OnboardingController::class, 'stepChat'], ['AuthRequired', 'CompanyRequired', 'VerifyCsrf']);
$router->post('/onboarding/install', [OnboardingController::class, 'stepInstall'], ['AuthRequired', 'CompanyRequired', 'VerifyCsrf']);
$router->post('/onboarding/complete', [OnboardingController::class, 'complete'], ['AuthRequired', 'CompanyRequired', 'VerifyCsrf']);

$router->get('/app', static fn () => redirect('/app/inbox'), ['AuthRequired', 'CompanyRequired']);
$router->get('/app/inbox', [AppController::class, 'inbox'], ['AuthRequired', 'CompanyRequired']);
$router->get('/app/inbox/{id}', [AppController::class, 'inbox'], ['AuthRequired', 'CompanyRequired']);
$router->get('/app/crm', [AppController::class, 'crm'], ['AuthRequired', 'CompanyRequired']);
$router->get('/app/crm/clients', [AppController::class, 'crm'], ['AuthRequired', 'CompanyRequired']);
$router->get('/app/crm/clients/{id}', [AppController::class, 'client'], ['AuthRequired', 'CompanyRequired']);
$router->get('/app/visitors', [AppController::class, 'visitors'], ['AuthRequired', 'CompanyRequired']);
$router->get('/app/analytics', [AppController::class, 'analytics'], ['AuthRequired', 'CompanyRequired']);
$router->get('/app/employees', [AppController::class, 'employees'], ['AuthRequired', 'CompanyRequired']);
$router->get('/app/departments', [AppController::class, 'departments'], ['AuthRequired', 'CompanyRequired']);
$router->get('/app/settings', [AppController::class, 'settings'], ['AuthRequired', 'CompanyRequired']);
$router->get('/app/settings/{section}', [AppController::class, 'settings'], ['AuthRequired', 'CompanyRequired']);
$router->get('/app/docs', [AppController::class, 'docs'], ['AuthRequired', 'CompanyRequired']);
$router->get('/app/profile', [AppController::class, 'profile'], ['AuthRequired', 'CompanyRequired']);
