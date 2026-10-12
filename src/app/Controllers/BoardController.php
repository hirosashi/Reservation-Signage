<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\Clock;
use App\Core\Session;
use App\Core\View;
use App\Services\Master;
use App\Services\Reservations;

/** 現場画面（ホーム・チャート・リスト・カレンダー）。右側の予約入力欄はどの画面でも開ける */
final class BoardController
{
    public static function home(): void
    {
        Auth::requireLogin();
        $date = self::date();
        $all = Reservations::between($date, $date);
        $active = array_values(array_filter($all, fn ($r) => !in_array($r['status'], Master::INACTIVE_STATUSES, true)));
        $flagCounts = [];
        foreach ($active as $r) {
            foreach ($r['flags'] as $f) {
                $flagCounts[$f['name']] = ($flagCounts[$f['name']] ?? 0) + 1;
            }
        }
        arsort($flagCounts);
        $statusCounts = [];
        foreach ($all as $r) {
            $statusCounts[$r['status']] = ($statusCounts[$r['status']] ?? 0) + 1;
        }
        self::frame('board/home', [
            'title' => 'ホーム',
            'date' => $date,
            'reservations' => $active,
            'kpi' => [
                'groups' => count($active),
                'pax' => array_sum(array_column($active, 'pax')),
                'amount' => array_sum(array_column($active, 'order_total')),
                'unassigned' => count(array_filter($active, fn ($r) => $r['venues'] === [])),
            ],
            'flagCounts' => $flagCounts,
            'statusCounts' => $statusCounts,
        ]);
    }

    public static function list(): void
    {
        Auth::requireLogin();
        $date = self::date();
        $status = App::param('status');
        $rows = Reservations::between($date, $date);
        if ($status !== '') {
            $rows = array_values(array_filter($rows, fn ($r) => $r['status'] === $status));
        } else {
            $rows = array_values(array_filter($rows, fn ($r) => !in_array($r['status'], Master::INACTIVE_STATUSES, true)));
        }
        self::frame('board/list', ['title' => 'リスト', 'date' => $date, 'reservations' => $rows, 'status' => $status]);
    }

    public static function chart(): void
    {
        Auth::requireLogin();
        $date = self::date();
        $rows = array_values(array_filter(Reservations::between($date, $date), fn ($r) => !in_array($r['status'], Master::INACTIVE_STATUSES, true)));
        $byVenue = [];
        $unassigned = [];
        foreach ($rows as $r) {
            if ($r['venues'] === []) {
                $unassigned[] = $r;
            }
            foreach ($r['venues'] as $v) {
                $byVenue[(int)$v['id']][] = $r;
            }
        }
        $shop = App::intParam('shop');
        $venues = array_values(array_filter(Master::venues(), fn ($v) => $shop === 0 || (int)$v['shop_id'] === $shop));
        self::frame('board/chart', [
            'title' => 'チャート', 'date' => $date, 'venues' => $venues, 'byVenue' => $byVenue,
            'unassigned' => $unassigned, 'shop' => $shop, 'startHour' => 7, 'endHour' => 24,
        ]);
    }

    public static function calendar(): void
    {
        Auth::requireLogin();
        $ym = App::param('ym');
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            $ym = substr(self::date(), 0, 7);
        }
        $first = new \DateTimeImmutable($ym . '-01');
        $last = $first->modify('last day of this month');
        $days = [];
        foreach (Reservations::between($first->format('Y-m-d'), $last->format('Y-m-d'), false) as $r) {
            $p = \App\Services\Report::period((string)$r['start_time']);
            $d = (string)$r['rsv_date'];
            $days[$d][$p]['groups'] = ($days[$d][$p]['groups'] ?? 0) + 1;
            $days[$d][$p]['pax'] = ($days[$d][$p]['pax'] ?? 0) + (int)$r['pax'];
        }
        self::frame('board/calendar', ['title' => 'カレンダー', 'date' => self::date(), 'first' => $first, 'last' => $last, 'days' => $days]);
    }

    public static function date(): string
    {
        $d = App::param('date');
        return Clock::parse($d)?->format('Y-m-d') ?? Clock::today();
    }

    /** 予約入力欄（右）を付けて描画 @param array<string, mixed> $vars */
    public static function frame(string $template, array $vars): void
    {
        $vars['panel'] = self::panel($vars['date'] ?? Clock::today());
        $vars['returnUrl'] = self::returnUrl();
        View::render($template, $vars);
    }

    /** @return array<string, mixed>|null */
    private static function panel(string $date): ?array
    {
        $old = Session::get('panel_old');
        Session::remove('panel_old');
        $editId = App::intParam('edit');
        if ($editId > 0) {
            $r = Reservations::find($editId);
            if ($r === null) {
                return null;
            }
            return ['mode' => 'edit', 'r' => $r, 'old' => is_array($old) ? $old : null];
        }
        if (App::param('new') === '1') {
            $time = App::param('time');
            $time = preg_match('/^\d{2}:\d{2}$/', $time) ? $time : '18:00';
            $endMin = min(24 * 60 - 1, \App\Services\Signage::minutes($time) + 120);
            $venue = App::intParam('venue');
            return ['mode' => 'new', 'r' => [
                'id' => null, 'code' => '', 'rsv_date' => $date, 'start_time' => $time,
                'end_time' => sprintf('%02d:%02d', intdiv($endMin, 60), $endMin % 60),
                'adults' => 2, 'children' => 0, 'infants' => 0, 'customer_id' => null, 'customer' => null,
                'group_name' => '', 'sign_name' => '', 'show_on_signage' => 1,
                'purpose_id' => null, 'source_id' => null, 'trigger_id' => null, 'status' => 'confirmed',
                'memo1' => '', 'memo2' => '',
                'venues' => $venue > 0 ? [['id' => $venue]] : [], 'flags' => [], 'orders' => [],
            ], 'old' => is_array($old) ? $old : null];
        }
        return null;
    }

    /** 入力欄を閉じたときに戻る URL（edit/new 等を除く） */
    public static function returnUrl(): string
    {
        $q = $_GET;
        foreach (['edit', 'new', 'time', 'venue'] as $k) {
            unset($q[$k]);
        }
        $qs = http_build_query($q);
        return App::currentPath() . ($qs !== '' ? '?' . $qs : '');
    }
}
