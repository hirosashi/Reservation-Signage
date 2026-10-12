<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\Clock;
use App\Core\Csrf;
use App\Core\Db;
use App\Core\Session;
use App\Core\View;
use App\Services\Customers;
use App\Services\History;
use App\Services\Master;
use App\Services\OverlapException;
use App\Services\Reservations;
use App\Services\Signage;

final class ReservationController
{
    public static function save(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        $return = AuthController::safeReturn(App::param('return'));
        $id = App::intParam('id');
        $existing = $id > 0 ? Reservations::find($id) : null;
        if ($id > 0 && $existing === null) {
            Session::flash('warn', '予約が見つかりません。');
            App::redirect($return);
        }

        $errors = [];
        $date = Clock::parse(App::param('rsv_date'))?->format('Y-m-d');
        if ($date === null) {
            $errors[] = '日付を正しく入力してください。';
        }
        $start = self::time(App::param('start_time'));
        $end = self::time(App::param('end_time'));
        if ($start === null || $end === null) {
            $errors[] = '時間を正しく入力してください。';
        } elseif (Signage::minutes($end) <= Signage::minutes($start)) {
            $errors[] = '終了時間は開始時間より後にしてください。';
        }
        $adults = max(0, App::intParam('adults'));
        $children = max(0, App::intParam('children'));
        $infants = max(0, App::intParam('infants'));
        if ($adults + $children + $infants <= 0) {
            $errors[] = '人数を入力してください。';
        }
        $status = App::param('status', 'confirmed');
        if (!isset(Master::STATUSES[$status])) {
            $status = 'confirmed';
        }

        $validVenues = array_column(Master::venues(false), null, 'id');
        $venueIds = array_values(array_filter(array_map('intval', (array)($_POST['venue_ids'] ?? [])), fn ($v) => isset($validVenues[$v])));
        $validFlags = array_column(Master::flags(false), null, 'id');
        $flagIds = array_values(array_filter(array_map('intval', (array)($_POST['flag_ids'] ?? [])), fn ($f) => isset($validFlags[$f])));

        $orders = [];
        $menu = array_column(Master::menuItems(), null, 'id');
        $o = (array)($_POST['orders'] ?? []);
        foreach ((array)($o['name'] ?? []) as $i => $name) {
            $name = trim((string)$name);
            $mid = (int)($o['menu_item_id'][$i] ?? 0);
            if ($name === '' && $mid > 0 && isset($menu[$mid])) {
                $name = (string)$menu[$mid]['name'];
            }
            if ($name === '') {
                continue;
            }
            $orders[] = [
                'menu_item_id' => isset($menu[$mid]) ? $mid : null,
                'name' => mb_substr($name, 0, 200),
                'unit_price' => (int)preg_replace('/[^\d-]/', '', (string)($o['unit_price'][$i] ?? '0')),
                'qty' => max(1, (int)($o['qty'][$i] ?? 1)),
            ];
        }

        $customerId = App::intParam('customer_id');
        if ($customerId > 0 && Db::value('SELECT COUNT(*) FROM customers WHERE id = ?', [$customerId]) == 0) {
            $customerId = 0;
        }
        $newCustomer = [];
        foreach (['last_name', 'first_name', 'last_kana', 'first_kana', 'company', 'tel', 'email'] as $k) {
            $newCustomer[$k] = App::param('nc_' . $k);
            $max = ['company' => 200, 'tel' => 30, 'email' => 200][$k] ?? 50;
            if (mb_strlen($newCustomer[$k]) > $max) {
                $errors[] = '新規顧客の項目は' . $max . '文字以内で入力してください（' . $k . '）。';
            }
        }
        $hasNewCustomer = $customerId === 0 && implode('', $newCustomer) !== '';

        $opt = fn (string $key, string $kind) => isset(Master::options($kind)[App::intParam($key)]) ? App::intParam($key) : null;
        $data = [
            'shop_id' => $venueIds !== [] ? (int)$validVenues[$venueIds[0]]['shop_id'] : (int)(Master::shops()[0]['id'] ?? 0),
            'rsv_date' => $date, 'start_time' => $start, 'end_time' => $end,
            'adults' => $adults, 'children' => $children, 'infants' => $infants,
            'customer_id' => $customerId > 0 ? $customerId : null,
            'group_name' => mb_substr(App::param('group_name'), 0, 255),
            'sign_name' => mb_substr(App::param('sign_name'), 0, 255),
            'show_on_signage' => App::param('show_on_signage') === '1' ? 1 : 0,
            'purpose_id' => $opt('purpose_id', 'purpose'),
            'source_id' => $opt('source_id', 'source'),
            'trigger_id' => $opt('trigger_id', 'trigger'),
            'status' => $status,
            'memo1' => mb_substr((string)($_POST['memo1'] ?? ''), 0, 20000),
            'memo2' => mb_substr((string)($_POST['memo2'] ?? ''), 0, 20000),
        ];

        if ($errors === [] && !in_array($status, Master::INACTIVE_STATUSES, true) && App::param('allow_overlap') !== '1') {
            $clash = Reservations::overlaps($id, (string)$date, (string)$start, (string)$end, $venueIds);
            if ($clash !== []) {
                $errors[] = '同じ時間帯に予約が入っている会場があります：' . implode('、', $clash) . '（重ねて保存する場合は「重複を許可」にチェック）';
            }
        }

        if ($errors !== []) {
            Session::set('panel_old', ['post' => $_POST, 'errors' => $errors]);
            $sep = str_contains($return, '?') ? '&' : '?';
            App::redirect($return . $sep . ($id > 0 ? 'edit=' . $id : 'new=1'));
        }

        if ($hasNewCustomer) {
            $now = Clock::nowStr();
            Db::exec(
                'INSERT INTO customers (last_name, first_name, last_kana, first_kana, company, tel, email, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)',
                [$newCustomer['last_name'], $newCustomer['first_name'], $newCustomer['last_kana'], $newCustomer['first_kana'], $newCustomer['company'], $newCustomer['tel'], $newCustomer['email'], $now, $now]
            );
            $data['customer_id'] = Db::lastId();
            History::add('customer', (int)$data['customer_id'], 'create', []);
        }

        try {
            $savedId = Reservations::save($id > 0 ? $id : null, $data, $venueIds, $flagIds, $orders, App::param('allow_overlap') === '1');
        } catch (OverlapException $e) {
            $post = $_POST;
            if ($hasNewCustomer) {
                $post['customer_id'] = (string)$data['customer_id'];
                foreach (array_keys($post) as $k) {
                    if (str_starts_with((string)$k, 'nc_')) {
                        unset($post[$k]);
                    }
                }
            }
            Session::set('panel_old', ['post' => $post, 'errors' => ['同じ時間帯に予約が入っている会場があります：' . implode('、', $e->clashes) . '（重ねて保存する場合は「重複を許可」にチェック）']]);
            $sep = str_contains($return, '?') ? '&' : '?';
            App::redirect($return . $sep . ($id > 0 ? 'edit=' . $id : 'new=1'));
        }
        $saved = Reservations::find($savedId);
        Session::flash('ok', '予約を保存しました（予約ID ' . ($saved['code'] ?? '') . '）。');
        App::redirect($return);
    }

    public static function show(int $id): void
    {
        Auth::requireLogin();
        $r = Reservations::find($id);
        if ($r === null) {
            \App\Core\Router::notFound();
            return;
        }
        $users = array_column(Db::rows('SELECT id, name FROM users'), 'name', 'id');
        View::render('reservations/show', [
            'title' => '予約詳細 ' . $r['code'],
            'r' => $r,
            'summary' => Reservations::summary($r),
            'histories' => History::of('reservation', $id),
            'stats' => $r['customer_id'] ? Customers::visitStats((int)$r['customer_id']) : null,
            'users' => $users,
            'date' => $r['rsv_date'],
        ]);
    }

    public static function status(int $id): void
    {
        Auth::requireLogin();
        Csrf::verify();
        try {
            Reservations::setStatus($id, App::param('status'));
            Session::flash('ok', 'ステータスを変更しました。');
        } catch (OverlapException $e) {
            Session::flash('warn', '同じ時間帯に予約が入っている会場があるため戻せません：' . implode('、', $e->clashes) . '（重ねる場合は予約の編集で「重複を許可」にチェックして保存）');
        }
        App::redirect(AuthController::safeReturn(App::param('return')));
    }

    public static function delete(int $id): void
    {
        Auth::requireLogin();
        Csrf::verify();
        Reservations::delete($id);
        Session::flash('ok', '予約を削除しました。');
        App::redirect(AuthController::safeReturn(App::param('return')));
    }

    private static function time(string $s): ?string
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($s), $m) || (int)$m[1] > 23 || (int)$m[2] > 59) {
            return null;
        }
        return sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
    }

}
