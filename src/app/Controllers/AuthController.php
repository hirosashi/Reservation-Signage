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

final class AuthController
{
    public static function loginForm(): void
    {
        if (Auth::user() !== null) {
            App::redirect('/');
        }
        View::render('auth/login', ['title' => 'ログイン', 'error' => ''], 'layout_plain');
    }

    public static function login(): void
    {
        Csrf::verify();
        $id = App::param('login_id');
        $err = Auth::attempt($id, (string)($_POST['password'] ?? ''));
        if ($err !== null) {
            View::render('auth/login', ['title' => 'ログイン', 'error' => $err, 'login_id' => $id], 'layout_plain');
            return;
        }
        $to = (string)Session::get('after_login', '');
        Session::remove('after_login');
        $base = App::basePath();
        if ($to !== '' && $base !== '' && str_starts_with($to, $base . '/')) {
            $to = substr($to, strlen($base));
        }
        App::redirect(self::safeReturn($to));
    }

    public static function logout(): void
    {
        Csrf::verify();
        Auth::logout();
        App::redirect('/login');
    }

    public static function passwordForm(): void
    {
        Auth::requireLogin();
        View::render('auth/password', ['title' => 'パスワード変更', 'errors' => []]);
    }

    public static function password(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        $u = Db::row('SELECT * FROM users WHERE id = ?', [Auth::id()]);
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        $errors = [];
        if ($u === null || !password_verify($cur, (string)$u['password_hash'])) {
            $errors[] = '現在のパスワードが違います。';
        }
        if (strlen($new) < 8) {
            $errors[] = '新しいパスワードは8文字以上にしてください。';
        }
        if ($new !== (string)($_POST['confirm'] ?? '')) {
            $errors[] = '確認用のパスワードが一致しません。';
        }
        if ($errors !== []) {
            View::render('auth/password', ['title' => 'パスワード変更', 'errors' => $errors]);
            return;
        }
        Db::exec('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), Clock::nowStr(), Auth::id()]);
        Session::flash('ok', 'パスワードを変更しました。');
        App::redirect('/');
    }

    /** アプリ内パス（/ で始まり // でない）だけを許可 */
    public static function safeReturn(string $to): string
    {
        return ($to !== '' && $to[0] === '/' && !str_starts_with($to, '//') && !str_contains($to, '\\')) ? $to : '/';
    }
}
