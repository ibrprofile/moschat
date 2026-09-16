<?php

declare(strict_types=1);

namespace MosChat\Middleware;

use MosChat\Core\Csrf;
use MosChat\Core\Request;

final class VerifyCsrf
{
    public function handle(Request $request): void
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }
        $token = $request->input('_csrf') ?? $request->header('X-CSRF-Token');
        if (!Csrf::validate(is_string($token) ? $token : null)) {
            if ($request->isJson()) {
                json_error('FORBIDDEN', 'Неверный CSRF-токен', 419);
            }
            http_response_code(419);
            echo 'CSRF token mismatch';
            exit;
        }
    }
}
