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
use App\Services\History;
use App\Services\Master;

/** 設定（マスタ管理）。管理者のみ */
final class SettingsController
{
    /** @return array<string, array<string, mixed>> */
    private static function defs(): array
    {
        $shops = array_column(Master::shops(false), 'name', 'id');
        $cats = array_column(Db::rows('SELECT id, name FROM menu_categories ORDER BY sort_no, id'), 'name', 'id');
        return [
            'shops' => ['label' => '店舗', 'table' => 'shops', 'order' => 'sort_no, id', 'fields' => [
                'name' => ['店舗名', 'text', true], 'slug' => ['TableCheck 店舗スラッグ', 'text'], 'sort_no' => ['並び順', 'int'], 'is_active' => ['使用中', 'bool'],
            ]],
            'venues' => ['label' => '会場・テーブル', 'table' => 'venues', 'order' => 'shop_id, sort_no, id', 'fields' => [
                'shop_id' => ['店舗', 'select', true, $shops], 'name' => ['会場名', 'text', true], 'signage_name' => ['サイネージ表示名（空なら会場名）', 'text'],
                'building' => ['館・階', 'text'], 'report_name' => ['帳票の会場名（空なら帳票の固定行に出さない）', 'text'],
                'cap_min' => ['最少人数', 'int'], 'cap_max' => ['最大人数', 'int'], 'sort_no' => ['並び順', 'int'], 'is_active' => ['使用中', 'bool'],
            ]],
            'flags' => ['label' => 'フラグ', 'table' => 'flags', 'order' => 'sort_no, id', 'fields' => [
                'name' => ['フラグ名', 'text', true], 'color' => ['色', 'color'], 'hide_on_signage' => ['この予約をサイネージに出さない', 'bool'], 'sort_no' => ['並び順', 'int'], 'is_active' => ['使用中', 'bool'],
            ]],
            'options' => ['label' => '選択肢（用途・来店経路・きっかけ・顧客タグ）', 'table' => 'options', 'order' => 'kind, sort_no, id', 'fields' => [
                'kind' => ['種類', 'select', true, Master::OPTION_KINDS], 'name' => ['名称', 'text', true], 'sort_no' => ['並び順', 'int'], 'is_active' => ['使用中', 'bool'],
            ]],
            'menu_categories' => ['label' => 'メニューカテゴリ', 'table' => 'menu_categories', 'order' => 'sort_no, id', 'fields' => [
                'name' => ['カテゴリ名', 'text', true], 'sort_no' => ['並び順', 'int'], 'is_active' => ['使用中', 'bool'],
            ]],
            'menu_items' => ['label' => 'メニュー', 'table' => 'menu_items', 'order' => 'category_id, sort_no, id', 'fields' => [
                'category_id' => ['カテゴリ', 'select', true, $cats], 'name' => ['メニュー名', 'text', true], 'price' => ['税込単価（円）', 'int'], 'sort_no' => ['並び順', 'int'], 'is_active' => ['使用中', 'bool'],
            ]],
            'users' => ['label' => 'ユーザー管理', 'table' => 'users', 'order' => 'id', 'fields' => [
                'login_id' => ['ログインID', 'text', true], 'name' => ['氏名', 'text', true], 'role' => ['権限', 'select', true, ['staff' => 'スタッフ', 'admin' => '管理者']],
                'password' => ['パスワード（新規は必須・変更時のみ入力）', 'password'], 'is_active' => ['使用中', 'bool'],
            ]],
        ];
    }

    public static function index(): void
    {
        Auth::requireAdmin();
        $t = App::param('t');
        $defs = self::defs();
        if ($t === '') {
            View::render('settings/index', ['title' => '設定', 'defs' => $defs]);
            return;
        }
        if (!isset($defs[$t])) {
            Router::notFound();
            return;
        }
        $d = $defs[$t];
        $rows = Db::rows('SELECT * FROM ' . $d['table'] . ' ORDER BY ' . $d['order']);
        $editId = App::intParam('id');
        $edit = null;
        if (App::param('new') === '1') {
            $edit = [];
        } elseif ($editId > 0) {
            $edit = Db::row('SELECT * FROM ' . $d['table'] . ' WHERE id = ?', [$editId]);
        }
        View::render('settings/list', ['title' => $d['label'], 't' => $t, 'd' => $d, 'rows' => $rows, 'edit' => $edit, 'errors' => []]);
    }

    public static function save(): void
    {
        Auth::requireAdmin();
        Csrf::verify();
        $t = App::param('t');
        $defs = self::defs();
        if (!isset($defs[$t])) {
            Router::notFound();
            return;
        }
        $d = $defs[$t];
        $id = App::intParam('id');
        $vals = [];
        $errors = [];
        foreach ($d['fields'] as $f => $def) {
            [$label, $type] = $def;
            $req = $def[2] ?? false;
            $v = App::param($f);
            if ($type === 'bool') {
                $vals[$f] = App::param($f) === '1' ? 1 : 0;
                continue;
            }
            if ($type === 'password') {
                if ($v === '' && $id === 0) {
                    $errors[] = 'パスワードを入力してください。';
                } elseif ($v !== '' && strlen($v) < 8) {
                    $errors[] = 'パスワードは8文字以上にしてください。';
                } elseif ($v !== '') {
                    $vals['password_hash'] = password_hash($v, PASSWORD_DEFAULT);
                }
                continue;
            }
            if ($req && $v === '') {
                $errors[] = $label . 'を入力してください。';
            }
            if ($type === 'int') {
                $vals[$f] = $v === '' ? ($f === 'sort_no' || $f === 'price' ? 0 : null) : (int)$v;
            } elseif ($type === 'select') {
                $vals[$f] = isset($def[3][$v]) ? $v : null;
                if ($req && $vals[$f] === null) {
                    $errors[] = $label . 'を選んでください。';
                }
            } else {
                $max = ['login_id' => 50, 'building' => 50, 'color' => 20][$f] ?? ($t === 'menu_items' && $f === 'name' ? 200 : 100);
                if (mb_strlen($v) > $max) {
                    $errors[] = $label . 'は' . $max . '文字以内で入力してください。';
                }
                $vals[$f] = $v === '' ? null : $v;
            }
        }
        if ($t === 'users') {
            $dup = Db::value('SELECT COUNT(*) FROM users WHERE login_id = ? AND id <> ?', [$vals['login_id'], $id]);
            if ((int)$dup > 0) {
                $errors[] = 'そのログインIDは使われています。';
            }
            if ($id === Auth::id() && ($vals['role'] !== 'admin' || $vals['is_active'] !== 1)) {
                $errors[] = '自分自身の管理者権限・使用中は外せません。';
            }
        }
        if ($errors !== []) {
            $rows = Db::rows('SELECT * FROM ' . $d['table'] . ' ORDER BY ' . $d['order']);
            View::render('settings/list', ['title' => $d['label'], 't' => $t, 'd' => $d, 'rows' => $rows, 'edit' => $vals + ['id' => $id], 'errors' => $errors]);
            return;
        }
        if ($t === 'users') {
            $vals['updated_at'] = Clock::nowStr();
        }
        if ($id > 0) {
            Db::exec('UPDATE ' . $d['table'] . ' SET ' . implode(' = ?, ', array_keys($vals)) . ' = ? WHERE id = ?', array_merge(array_values($vals), [$id]));
        } else {
            if ($t === 'users') {
                $vals['created_at'] = Clock::nowStr();
            }
            Db::exec('INSERT INTO ' . $d['table'] . ' (' . implode(', ', array_keys($vals)) . ') VALUES (' . implode(',', array_fill(0, count($vals), '?')) . ')', array_values($vals));
            $id = Db::lastId();
        }
        unset($vals['password_hash']);
        History::add('master', $id, $t, $vals);
        Session::flash('ok', $d['label'] . 'を保存しました。');
        App::redirect('/settings?t=' . $t);
    }
}
