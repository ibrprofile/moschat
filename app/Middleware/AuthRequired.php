<?php

declare(strict_types=1);

namespace MosChat\Middleware;

use MosChat\Core\Auth;
use MosChat\Core\Csrf;
use MosChat\Core\Request;

final class AuthRequired
{
    public function handle(Request $request): void
    {
        if (!Auth::check()) {
            if ($request->isJson()) {
                json_error('UNAUTHORIZED', 'Требуется вход', 401);
            }
            redirect('/login');
        }
    }
}
