<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\Clock;
use App\Core\Csrf;
use App\Core\Db;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Services\Customers;
use App\Services\History;
use App\Services\Master;
use App\Services\Reservations;

final class CustomerController
{
    private const FIELDS = ['last_name' => '姓', 'first_name' => '名', 'last_kana' => 'セイ', 'first_kana' => 'メイ', 'company' => '会社名', 'department' => '部署', 'title' => '役職', 'tel' => '電話番号', 'email' => 'メール', 'gender' => '性別', 'memo' => '顧客メモ'];

    public static function index(): void
    {
        Auth::requireLogin();
        $q = App::param('q');
        $rows = Customers::search($q, 100);
        BoardController::frame('customers/index', ['title' => '顧客検索', 'q' => $q, 'customers' => $rows, 'date' => BoardController::date()]);
    }

    /** 予約入力欄の顧客検索（JSON） */
    public static function api(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=utf-8');
        $out = [];
        foreach (Customers::search(App::param('q'), 20) as $c) {
            $out[] = ['id' => (int)$c['id'], 'label' => Customers::displayName($c), 'tel' => (string)$c['tel']];
        }
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    public static function show(int $id): void
    {
        Auth::requireLogin();
        $c = Db::row('SELECT * FROM customers WHERE id = ?', [$id]);
        if ($c === null) {
            Router::notFound();
            return;
        }
        $ids = array_map(fn ($r) => (int)$r['id'], Db::rows('SELECT id FROM reservations WHERE customer_id = ? AND deleted_at IS NULL ORDER BY rsv_date DESC, start_time DESC LIMIT 100', [$id]));
        $rsv = $ids === [] ? [] : Reservations::attach(Db::rows('SELECT * FROM reservations WHERE id IN (' . implode(',', $ids) . ') ORDER BY rsv_date DESC, start_time DESC'));
        BoardController::frame('customers/show', [
            'title' => Customers::displayName($c), 'c' => $c, 'reservations' => $rsv,
            'stats' => Customers::visitStats($id), 'tags' => array_intersect_key(Master::options('customer_tag'), array_flip(Customers::tagIds($id))),
            'date' => BoardController::date(),
        ]);
    }

    public static function edit(): void
    {
        Auth::requireLogin();
        $id = App::intParam('id');
        $c = $id > 0 ? Db::row('SELECT * FROM customers WHERE id = ?', [$id]) : null;
        View::render('customers/edit', [
            'title' => $c ? '顧客の編集' : '顧客の登録', 'c' => $c ?? [], 'fields' => self::FIELDS,
            'tagIds' => $c ? Customers::tagIds($id) : [], 'errors' => [],
        ]);
    }

    public static function save(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        $id = App::intParam('id');
        $vals = [];
        foreach (array_keys(self::FIELDS) as $f) {
            $v = $f === 'memo' ? (string)($_POST[$f] ?? '') : App::param($f);
            $vals[$f] = mb_substr($v, 0, $f === 'memo' ? 20000 : 200);
        }
        if (trim($vals['last_name'] . $vals['first_name'] . $vals['last_kana'] . $vals['company']) === '') {
            View::render('customers/edit', ['title' => '顧客', 'c' => $vals + ['id' => $id], 'fields' => self::FIELDS, 'tagIds' => [], 'errors' => ['氏名・カナ・会社名のどれかを入力してください。']]);
            return;
        }
        $now = Clock::nowStr();
        if ($id > 0) {
            Db::exec('UPDATE customers SET ' . implode(' = ?, ', array_keys($vals)) . ' = ?, updated_at = ? WHERE id = ?', array_merge(array_values($vals), [$now, $id]));
            History::add('customer', $id, 'update', []);
        } else {
            Db::exec('INSERT INTO customers (' . implode(', ', array_keys($vals)) . ', created_at, updated_at) VALUES (' . str_repeat('?,', count($vals)) . '?,?)', array_merge(array_values($vals), [$now, $now]));
            $id = Db::lastId();
            History::add('customer', $id, 'create', []);
        }
        Db::exec('DELETE FROM customer_tags WHERE customer_id = ?', [$id]);
        $valid = Master::options('customer_tag');
        foreach (array_unique(array_map('intval', (array)($_POST['tag_ids'] ?? []))) as $t) {
            if (isset($valid[$t])) {
                Db::exec('INSERT INTO customer_tags (customer_id, option_id) VALUES (?,?)', [$id, $t]);
            }
        }
        Session::flash('ok', '顧客を保存しました。');
        App::redirect('/customers/' . $id);
    }
}
