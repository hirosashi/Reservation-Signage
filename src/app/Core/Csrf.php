<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        $t = Session::get('_token');
        if (!is_string($t) || $t === '') {
            $t = bin2hex(random_bytes(32));
            Session::set('_token', $t);
        }
        return $t;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . View::e(self::token()) . '">';
    }

    public static function verify(): void
    {
        $sent = $_POST['_token'] ?? '';
        if (!is_string($sent) || !hash_equals(self::token(), $sent)) {
            http_response_code(400);
            Session::flash('warn', '画面の有効期限が切れました。もう一度やり直してください。');
            App::redirect('/');
        }
    }
}
