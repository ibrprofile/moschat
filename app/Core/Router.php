<?php

declare(strict_types=1);

namespace MosChat\Core;

final class Router
{
    /** @var list<array{methods: list<string>, pattern: string, handler: callable|array{0: class-string, 1: string}, middleware: list<string>}> */
    private array $routes = [];

    public function get(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add(['GET'], $pattern, $handler, $middleware);
    }

    public function post(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add(['POST'], $pattern, $handler, $middleware);
    }

    public function patch(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add(['PATCH'], $pattern, $handler, $middleware);
    }

    public function put(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add(['PUT'], $pattern, $handler, $middleware);
    }

    public function delete(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add(['DELETE'], $pattern, $handler, $middleware);
    }

    public function any(array $methods, string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add($methods, $pattern, $handler, $middleware);
    }

    private function add(array $methods, string $pattern, callable|array $handler, array $middleware): void
    {
        $this->routes[] = [
            'methods' => array_map('strtoupper', $methods),
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): mixed
    {
        foreach ($this->routes as $route) {
            if (!in_array($request->method(), $route['methods'], true)) {
                continue;
            }
            $params = $this->match($route['pattern'], $request->path());
            if ($params === null) {
                continue;
            }

            $handler = $route['handler'];
            foreach ($route['middleware'] as $mw) {
                $class = 'MosChat\\Middleware\\' . $mw;
                if (!class_exists($class)) {
                    throw new \RuntimeException("Middleware missing: {$mw}");
                }
                (new $class())->handle($request);
            }

            if (is_array($handler)) {
                [$class, $method] = $handler;
                $controller = new $class();
                return $controller->{$method}($request, ...array_values($params));
            }

            return $handler($request, ...array_values($params));
        }

        if ($request->isJson()) {
            json_error('NOT_FOUND', 'Route not found', 404);
        }

        http_response_code(404);
        echo View::render('errors/404', [], 'layouts/auth');
        exit;
    }

    /** @return array<string, string>|null */
    private function match(string $pattern, string $path): ?array
    {
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';
        if (!preg_match($regex, $path, $matches)) {
            return null;
        }
        $params = [];
        foreach ($matches as $key => $value) {
            if (!is_int($key)) {
                $params[$key] = $value;
            }
        }
        return $params;
    }
}
