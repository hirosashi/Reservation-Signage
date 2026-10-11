<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\Clock;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Services\Master;
use App\Services\Signage;

final class SignageController
{
    /** 会場案内の表示データ（公開。サイネージ端末はログインしない） */
    public static function events(): void
    {
        $from = Clock::parse(App::param('date'))?->format('Y-m-d') ?? Clock::today();
        $days = max(1, min(31, App::intParam('days', (int)Master::setting('signage_days', '7'))));
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('Access-Control-Allow-Origin: *');
        echo json_encode(Signage::feed($from, $days), JSON_UNESCAPED_UNICODE);
    }

    public static function index(): void
    {
        Auth::requireLogin();
        $date = BoardController::date();
        $feed = Signage::feed($date, 1);
        BoardController::frame('signage/index', [
            'title' => 'サイネージ', 'date' => $date, 'events' => $feed['events'],
            'days' => Master::setting('signage_days', '7'),
        ]);
    }

    public static function saveSettings(): void
    {
        Auth::requireAdmin();
        Csrf::verify();
        Master::saveSetting('signage_days', (string)max(1, min(31, App::intParam('signage_days', 7))));
        Session::flash('ok', 'サイネージ設定を保存しました。');
        App::redirect('/signage');
    }
}
