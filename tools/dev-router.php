<?php
// ローカル確認用: php -S 127.0.0.1:8090 -t src tools/dev-router.php
$path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if ($path !== '/' && is_file(__DIR__ . '/../src' . $path)) {
    return false;
}
require __DIR__ . '/../src/index.php';
