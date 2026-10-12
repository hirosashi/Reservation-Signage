<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Db;

final class Customers
{
    /** @param array<string, mixed>|null $c */
    public static function displayName(?array $c): string
    {
        if ($c === null) {
            return '';
        }
        $name = trim(($c['last_name'] ?? '') . ' ' . ($c['first_name'] ?? ''));
        if ($name === '') {
            $name = trim(($c['last_kana'] ?? '') . ' ' . ($c['first_kana'] ?? ''));
        }
        $company = trim((string)($c['company'] ?? ''));
        if ($company !== '') {
            return $name === '' ? $company : $company . ' ' . $name . '様';
        }
        return $name === '' ? '' : $name . '様';
    }

    /** @return array<int, array<string, mixed>> */
    public static function search(string $q, int $limit = 50): array
    {
        $q = trim($q);
        if ($q === '') {
            return Db::rows('SELECT * FROM customers ORDER BY updated_at DESC, id DESC LIMIT ' . $limit);
        }
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $digits = preg_replace('/\D/', '', $q);
        $sql = "SELECT * FROM customers WHERE last_name LIKE ? OR first_name LIKE ? OR last_kana LIKE ? OR first_kana LIKE ? OR company LIKE ? OR email LIKE ?";
        $p = [$like, $like, $like, $like, $like, $like];
        if ($digits !== '' && strlen((string)$digits) >= 3) {
            $sql .= " OR REPLACE(REPLACE(tel, '-', ''), ' ', '') LIKE ?";
            $p[] = '%' . $digits . '%';
        }
        return Db::rows($sql . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $limit, $p);
    }

    /** @return array<int, int> */
    public static function tagIds(int $id): array
    {
        return array_map(fn ($r) => (int)$r['option_id'], Db::rows('SELECT option_id FROM customer_tags WHERE customer_id = ?', [$id]));
    }

    /** @return array{count: int, last: ?string} */
    public static function visitStats(int $id): array
    {
        $r = Db::row("SELECT COUNT(*) AS c, MAX(rsv_date) AS last FROM reservations WHERE customer_id = ? AND deleted_at IS NULL AND status NOT IN ('cancelled','noshow')", [$id]);
        return ['count' => (int)($r['c'] ?? 0), 'last' => $r['last'] ?? null];
    }
}
