<?php

declare(strict_types=1);

namespace MosChat\Core;

final class App
{
    private static ?self $instance = null;
    private Config $config;
    private Router $router;
    private Request $request;
    private string $requestId;

    private function __construct()
    {
        $this->loadEnv(base_path('.env'));
        $this->config = new Config(base_path('config'));
        $this->router = new Router();
        $this->request = Request::capture();
        $this->requestId = 'req_' . bin2hex(random_bytes(8));
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function boot(): void
    {
        date_default_timezone_set('UTC');
        Session::start();
        Csrf::token();

        require base_path('routes/web.php');
        require base_path('routes/api.php');
        require base_path('routes/widget.php');
    }

    public function run(): void
    {
        $this->boot();
        $this->router->dispatch($this->request);
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config->get($key, $default);
    }

    private function loadEnv(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
