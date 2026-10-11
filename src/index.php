<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\BoardController;
use App\Controllers\CustomerController;
use App\Controllers\ReportController;
use App\Controllers\ReservationController;
use App\Controllers\SettingsController;
use App\Controllers\SignageController;
use App\Core\Router;
use App\Core\Session;

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/storage/logs/php_error.log');

require __DIR__ . '/app/bootstrap.php';

$isApi = str_starts_with(App\Core\App::currentPath(), '/api/signage/');
if (!$isApi) {
    Session::start();
}
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header('X-Robots-Tag: noindex, nofollow');

$router = new Router();

$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/password', [AuthController::class, 'passwordForm']);
$router->post('/password', [AuthController::class, 'password']);

// 現場画面
$router->get('/', [BoardController::class, 'home']);
$router->get('/list', [BoardController::class, 'list']);
$router->get('/chart', [BoardController::class, 'chart']);
$router->get('/calendar', [BoardController::class, 'calendar']);
$router->post('/reservations/save', [ReservationController::class, 'save']);
$router->get('/reservations/{id}', [ReservationController::class, 'show']);
$router->post('/reservations/{id}/status', [ReservationController::class, 'status']);
$router->post('/reservations/{id}/delete', [ReservationController::class, 'delete']);

// 顧客
$router->get('/customers', [CustomerController::class, 'index']);
$router->get('/customers/edit', [CustomerController::class, 'edit']);
$router->post('/customers/save', [CustomerController::class, 'save']);
$router->get('/customers/{id}', [CustomerController::class, 'show']);
$router->get('/api/customers', [CustomerController::class, 'api']);

// サイネージ・帳票
$router->get('/signage', [SignageController::class, 'index']);
$router->post('/signage/settings', [SignageController::class, 'saveSettings']);
$router->get('/api/signage/events', [SignageController::class, 'events']);
$router->get('/report', [ReportController::class, 'show']);
$router->get('/report/xlsx', [ReportController::class, 'xlsx']);

// 設定
$router->get('/settings', [SettingsController::class, 'index']);
$router->post('/settings/save', [SettingsController::class, 'save']);

try {
    $router->dispatch();
} catch (Throwable $e) {
    error_log((string)$e);
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>エラー</title><p>エラーが発生しました。時間をおいて再度お試しください。</p>';
}
