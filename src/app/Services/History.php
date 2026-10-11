<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Clock;
use App\Core\Db;

final class History
{
    /** @param array<string, mixed> $detail */
    public static function add(string $target, ?int $targetId, string $action, array $detail): void
    {
        Db::exec(
            'INSERT INTO histories (target, target_id, user_id, action, detail, created_at) VALUES (?,?,?,?,?,?)',
            [$target, $targetId, Auth::id(), $action, json_encode($detail, JSON_UNESCAPED_UNICODE), Clock::nowStr()]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function of(string $target, int $id): array
    {
        $rows = Db::rows('SELECT h.*, u.name AS user_name FROM histories h LEFT JOIN users u ON u.id = h.user_id WHERE h.target = ? AND h.target_id = ? ORDER BY h.id DESC', [$target, $id]);
        foreach ($rows as &$r) {
            $r['detail'] = json_decode((string)$r['detail'], true) ?: [];
        }
        return $rows;
    }
}
