<?php

declare(strict_types=1);

namespace MosChat\Middleware;

use MosChat\Core\Auth;
use MosChat\Core\Request;

final class GuestOnly
{
    public function handle(Request $request): void
    {
        if (Auth::check()) {
            redirect('/app/inbox');
        }
    }
}
