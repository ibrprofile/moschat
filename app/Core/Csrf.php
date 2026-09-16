<?php

declare(strict_types=1);

namespace MosChat\Core;

final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::KEY);
        if (!is_string($token) || $token === '') {
            $token = random_token(32);
            Session::put(self::KEY, $token);
        }
        return $token;
    }

    public static function validate(?string $token): bool
    {
        $sessionToken = Session::get(self::KEY);
        return is_string($sessionToken)
            && is_string($token)
            && hash_equals($sessionToken, $token);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }
}
