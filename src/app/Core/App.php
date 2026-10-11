<?php
declare(strict_types=1);

namespace App\Core;

final class App
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /** @return array<string, mixed> */
    public static function config(): array
    {
        if (self::$config === null) {
            $dir = dirname(__DIR__, 2) . '/config';
            $file = $dir . '/config.php';
            if (!is_file($file)) {
                foreach (['config.local.php', 'config.sakura.php'] as $alt) {
                    if (is_file($dir . '/' . $alt)) {
                        $file = $dir . '/' . $alt;
                        break;
                    }
                }
            }
            /** @var array<string, mixed> $cfg */
            $cfg = require $file;
            self::$config = $cfg;
        }
        return self::$config;
    }

    public static function basePath(): string
    {
        $b = (string)(self::config()['base_path'] ?? '');
        return rtrim($b, '/');
    }

    public static function url(string $path): string
    {
        return self::basePath() . '/' . ltrim($path, '/');
    }

    public static function redirect(string $path): never
    {
        header('Location: ' . self::url($path));
        exit;
    }

    public static function currentPath(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string)parse_url($uri, PHP_URL_PATH);
        $base = self::basePath();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }

    public static function param(string $key, string $default = ''): string
    {
        $v = $_GET[$key] ?? $_POST[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    public static function intParam(string $key, int $default = 0): int
    {
        $v = self::param($key, '');
        return $v === '' || !is_numeric($v) ? $default : (int)$v;
    }

    public static function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }
}
