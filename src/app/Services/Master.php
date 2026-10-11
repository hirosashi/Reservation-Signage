<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Db;

/** マスタの取得（1リクエスト内でキャッシュ） */
final class Master
{
    /** @var array<string, mixed> */
    private static array $cache = [];

    public const STATUSES = [
        'tentative' => '仮予約',
        'unconfirmed' => '未確認',
        'confirmed' => '確認済',
        'prepared' => '準備',
        'seated' => 'ご案内済み',
        'finished' => '終了',
        'cancelled' => 'キャンセル',
        'noshow' => '無断キャンセル',
    ];

    /** サイネージ・帳票に出さないステータス */
    public const HIDDEN_STATUSES = ['tentative', 'cancelled', 'noshow'];

    /** 一覧・チャートで「有効な予約」として数えないステータス */
    public const INACTIVE_STATUSES = ['cancelled', 'noshow'];

    public const OPTION_KINDS = [
        'purpose' => '用途',
        'source' => '来店経路',
        'trigger' => 'きっかけ',
        'customer_tag' => '顧客タグ',
    ];

    /** @return array<int, array<string, mixed>> */
    public static function shops(bool $activeOnly = true): array
    {
        return self::memo('shops' . (int)$activeOnly, fn () => Db::rows('SELECT * FROM shops' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_no, id'));
    }

    /** @return array<int, array<string, mixed>> */
    public static function venues(bool $activeOnly = true): array
    {
        return self::memo('venues' . (int)$activeOnly, fn () => Db::rows(
            'SELECT v.*, s.name AS shop_name, s.sort_no AS shop_sort FROM venues v JOIN shops s ON s.id = v.shop_id'
            . ($activeOnly ? ' WHERE v.is_active = 1 AND s.is_active = 1' : '')
            . ' ORDER BY s.sort_no, s.id, v.sort_no, v.id'
        ));
    }

    /** @return array<int, array<string, mixed>> */
    public static function flags(bool $activeOnly = true): array
    {
        return self::memo('flags' . (int)$activeOnly, fn () => Db::rows('SELECT * FROM flags' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_no, id'));
    }

    /** @return array<int, string> id => name */
    public static function options(string $kind): array
    {
        return self::memo('opt' . $kind, function () use ($kind) {
            $r = [];
            foreach (Db::rows('SELECT id, name FROM options WHERE kind = ? AND is_active = 1 ORDER BY sort_no, id', [$kind]) as $o) {
                $r[(int)$o['id']] = (string)$o['name'];
            }
            return $r;
        });
    }

    /** @return array<int, array<string, mixed>> */
    public static function menuItems(): array
    {
        return self::memo('menu', fn () => Db::rows(
            'SELECT m.*, c.name AS category_name FROM menu_items m JOIN menu_categories c ON c.id = m.category_id'
            . ' WHERE m.is_active = 1 AND c.is_active = 1 ORDER BY c.sort_no, c.id, m.sort_no, m.id'
        ));
    }

    public static function setting(string $name, string $default = ''): string
    {
        $all = self::memo('settings', function () {
            $r = [];
            foreach (Db::rows('SELECT name, value FROM settings') as $s) {
                $r[(string)$s['name']] = (string)$s['value'];
            }
            return $r;
        });
        return $all[$name] ?? $default;
    }

    public static function saveSetting(string $name, string $value): void
    {
        Db::exec('DELETE FROM settings WHERE name = ?', [$name]);
        Db::exec('INSERT INTO settings (name, value) VALUES (?,?)', [$name, $value]);
        unset(self::$cache['settings']);
    }

    public static function clear(): void
    {
        self::$cache = [];
    }

    private static function memo(string $key, callable $fn): mixed
    {
        if (!array_key_exists($key, self::$cache)) {
            self::$cache[$key] = $fn();
        }
        return self::$cache[$key];
    }
}
