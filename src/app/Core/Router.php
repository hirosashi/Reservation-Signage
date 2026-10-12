<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, array<string, array{0: class-string, 1: string}>> */
    private array $routes = ['GET' => [], 'POST' => []];

    /** @param array{0: class-string, 1: string} $handler */
    public function get(string $path, array $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    /** @param array{0: class-string, 1: string} $handler */
    public function post(string $path, array $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    /** '/jobs/{id}' のような数値パラメータ付きルートにも対応 */
    public function dispatch(): void
    {
        $method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = App::currentPath();
        $h = $this->routes[$method][$path] ?? null;
        $args = [];
        if ($h === null) {
            foreach ($this->routes[$method] ?? [] as $pattern => $handler) {
                if (!str_contains($pattern, '{')) {
                    continue;
                }
                $re = '#^' . preg_replace('#\{[a-z_]+\}#', '(\d+)', $pattern) . '$#';
                if (preg_match($re, $path, $m)) {
                    $h = $handler;
                    $args = array_map('intval', array_slice($m, 1));
                    break;
                }
            }
        }
        if ($h === null) {
            self::notFound();
            return;
        }
        [$class, $fn] = $h;
        $class::$fn(...$args);
    }

    public static function notFound(): void
    {
        http_response_code(404);
        View::render('errors/404', ['title' => 'ページが見つかりません']);
    }
}
