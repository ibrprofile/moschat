<?php

declare(strict_types=1);

use MosChat\Core\App;

function app(): App
{
    return App::getInstance();
}

function config(string $key, mixed $default = null): mixed
{
    return App::getInstance()->config($key, $default);
}

function env_val(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    if ($value === 'true') {
        return true;
    }
    if ($value === 'false') {
        return false;
    }
    if ($value === 'null') {
        return null;
    }
    return $value;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function slugify(string $text): string
{
    $text = mb_strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text) ?? '';
    return trim($text, '-') ?: 'company';
}

function random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function base_path(string $path = ''): string
{
    return dirname(__DIR__, 2) . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function public_path(string $path = ''): string
{
    return base_path('public' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
}

function storage_path(string $path = ''): string
{
    return base_path('storage' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
}

function url(string $path = ''): string
{
    $base = rtrim((string) config('app.url', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function json_ok(mixed $data = null, array $meta = [], int $status = 200): never
{
    \MosChat\Core\Response::json([
        'success' => true,
        'data' => $data,
        'meta' => array_merge(['request_id' => app()->requestId()], $meta),
    ], $status);
}

function json_error(string $code, string $message, int $status = 400, array $details = []): never
{
    \MosChat\Core\Response::json([
        'success' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
            'details' => $details,
        ],
        'meta' => ['request_id' => app()->requestId()],
    ], $status);
}
