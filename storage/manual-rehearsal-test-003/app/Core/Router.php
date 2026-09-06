<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$this->norm($path)] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$this->norm($path)] = $handler;
    }

    private function norm(string $p): string
    {
        $p = '/' . ltrim($p, '/');
        $p = preg_replace('#/+#', '/', $p);
        return rtrim($p, '/') ?: '/';
    }

    public function dispatch(string $method, string $path)
    {
        $method = strtoupper($method);
        $path = $this->norm($path);

        if (function_exists('base_enforce_assignment_access')) {
            base_enforce_assignment_access($path, $method);
        }

        $handler = $this->routes[$method][$path] ?? null;

        if (!$handler) {
            http_response_code(404);
            echo "404 Not Found: " . htmlspecialchars($path);
            return null;
        }

        return $handler();
    }

    public function listRoutes(): array
    {
        return $this->routes;
    }
}
