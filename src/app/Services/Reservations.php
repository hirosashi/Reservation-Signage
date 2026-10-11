<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Clock;
use App\Core\Db;

/** 予約の読み書き・履歴 */
final class Reservations
{
    private const CODE_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $r = Db::row('SELECT * FROM reservations WHERE id = ? AND deleted_at IS NULL', [$id]);
        if ($r === null) {
            return null;
        }
        return self::attach([$r])[0];
    }

    /**
     * 期間の予約（会場・フラグ・注文・顧客付き）
     * @return array<int, array<string, mixed>>
     */
    public static function between(string $from, string $to, bool $includeInactive = true): array
    {
        $sql = 'SELECT * FROM reservations WHERE deleted_at IS NULL AND rsv_date >= ? AND rsv_date <= ?';
        if (!$includeInactive) {
            $sql .= " AND status NOT IN ('" . implode("','", Master::INACTIVE_STATUSES) . "')";
        }
        return self::attach(Db::rows($sql . ' ORDER BY rsv_date, start_time, id', [$from, $to]));
    }

    /** @param array<int, array<string, mixed>> $rows @return array<int, array<string, mixed>> */
    public static function attach(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $ids = array_map(fn ($r) => (int)$r['id'], $rows);
        $in = implode(',', $ids);
        $venues = [];
        foreach (Db::rows("SELECT rv.reservation_id, v.id, v.name, v.signage_name, v.building, v.report_name, v.shop_id FROM reservation_venues rv JOIN venues v ON v.id = rv.venue_id WHERE rv.reservation_id IN ($in) ORDER BY v.sort_no, v.id") as $v) {
            $venues[(int)$v['reservation_id']][] = $v;
        }
        $flags = [];
        foreach (Db::rows("SELECT rf.reservation_id, f.id, f.name, f.color, f.hide_on_signage FROM reservation_flags rf JOIN flags f ON f.id = rf.flag_id WHERE rf.reservation_id IN ($in) ORDER BY f.sort_no, f.id") as $f) {
            $flags[(int)$f['reservation_id']][] = $f;
        }
        $orders = [];
        foreach (Db::rows("SELECT * FROM order_items WHERE reservation_id IN ($in) ORDER BY sort_no, id") as $o) {
            $orders[(int)$o['reservation_id']][] = $o;
        }
        $custIds = array_values(array_unique(array_filter(array_map(fn ($r) => (int)($r['customer_id'] ?? 0), $rows))));
        $customers = [];
        if ($custIds !== []) {
            foreach (Db::rows('SELECT * FROM customers WHERE id IN (' . implode(',', $custIds) . ')') as $c) {
                $customers[(int)$c['id']] = $c;
            }
        }
        foreach ($rows as &$r) {
            $id = (int)$r['id'];
            $r['venues'] = $venues[$id] ?? [];
            $r['flags'] = $flags[$id] ?? [];
            $r['orders'] = $orders[$id] ?? [];
            $r['order_total'] = array_sum(array_map(fn ($o) => (int)$o['unit_price'] * (int)$o['qty'], $r['orders']));
            $r['customer'] = $customers[(int)($r['customer_id'] ?? 0)] ?? null;
            $r['pax'] = (int)$r['adults'] + (int)$r['children'] + (int)$r['infants'];
        }
        unset($r);
        return $rows;
    }

    /**
     * 保存（新規・更新）。$data は検証済みの値
     * @param array<string, mixed> $data
     * @param array<int, int> $venueIds
     * @param array<int, int> $flagIds
     * @param array<int, array{menu_item_id: ?int, name: string, unit_price: int, qty: int}> $orders
     */
    public static function save(?int $id, array $data, array $venueIds, array $flagIds, array $orders): int
    {
        return Db::tx(function () use ($id, $data, $venueIds, $flagIds, $orders) {
            $now = Clock::nowStr();
            $before = $id !== null ? self::find($id) : null;
            $cols = ['shop_id', 'rsv_date', 'start_time', 'end_time', 'adults', 'children', 'infants', 'customer_id', 'group_name', 'sign_name', 'show_on_signage', 'purpose_id', 'source_id', 'trigger_id', 'status', 'memo1', 'memo2'];
            $vals = array_map(fn ($c) => $data[$c] ?? null, $cols);
            if ($before === null) {
                $code = self::newCode();
                Db::exec(
                    'INSERT INTO reservations (code, ' . implode(', ', $cols) . ', created_by, updated_by, created_at, updated_at) VALUES (?' . str_repeat(',?', count($cols)) . ',?,?,?,?)',
                    array_merge([$code], $vals, [Auth::id(), Auth::id(), $now, $now])
                );
                $id = Db::lastId();
            } else {
                Db::exec(
                    'UPDATE reservations SET ' . implode(' = ?, ', $cols) . ' = ?, updated_by = ?, updated_at = ? WHERE id = ?',
                    array_merge($vals, [Auth::id(), $now, $id])
                );
            }
            Db::exec('DELETE FROM reservation_venues WHERE reservation_id = ?', [$id]);
            foreach (array_unique($venueIds) as $v) {
                Db::exec('INSERT INTO reservation_venues (reservation_id, venue_id) VALUES (?,?)', [$id, $v]);
            }
            Db::exec('DELETE FROM reservation_flags WHERE reservation_id = ?', [$id]);
            foreach (array_unique($flagIds) as $f) {
                Db::exec('INSERT INTO reservation_flags (reservation_id, flag_id) VALUES (?,?)', [$id, $f]);
            }
            Db::exec('DELETE FROM order_items WHERE reservation_id = ?', [$id]);
            foreach (array_values($orders) as $i => $o) {
                Db::exec('INSERT INTO order_items (reservation_id, menu_item_id, name, unit_price, qty, sort_no) VALUES (?,?,?,?,?,?)', [$id, $o['menu_item_id'], $o['name'], $o['unit_price'], $o['qty'], $i]);
            }
            $after = self::find($id);
            $diff = self::diff($before, $after);
            if ($before === null || $diff !== []) {
                History::add('reservation', $id, $before === null ? 'create' : 'update', $diff);
            }
            return $id;
        });
    }

    public static function setStatus(int $id, string $status): void
    {
        $before = self::find($id);
        if ($before === null || !isset(Master::STATUSES[$status]) || $before['status'] === $status) {
            return;
        }
        Db::exec('UPDATE reservations SET status = ?, updated_by = ?, updated_at = ? WHERE id = ?', [$status, Auth::id(), Clock::nowStr(), $id]);
        History::add('reservation', $id, 'update', ['ステータス' => [Master::STATUSES[$before['status']] ?? $before['status'], Master::STATUSES[$status]]]);
    }

    public static function delete(int $id): void
    {
        Db::exec('UPDATE reservations SET deleted_at = ?, updated_by = ? WHERE id = ?', [Clock::nowStr(), Auth::id(), $id]);
        History::add('reservation', $id, 'delete', []);
    }

    /** 表示用の要約（比較用） @param array<string, mixed>|null $r @return array<string, string> */
    public static function summary(?array $r): array
    {
        if ($r === null) {
            return [];
        }
        $opt = fn (string $kind, mixed $id) => Master::options($kind)[(int)$id] ?? '';
        return [
            '日付' => (string)$r['rsv_date'],
            '時間' => $r['start_time'] . '〜' . $r['end_time'],
            '人数' => '大人' . $r['adults'] . ' 子供' . $r['children'] . ' 幼児' . $r['infants'],
            '会場' => implode('、', array_map(fn ($v) => (string)$v['name'], $r['venues'])),
            '顧客' => Customers::displayName($r['customer']),
            'グループ名' => (string)$r['group_name'],
            '看板名' => (string)$r['sign_name'],
            'サイネージ表示' => (int)$r['show_on_signage'] === 1 ? '表示' : '非表示',
            '用途' => $opt('purpose', $r['purpose_id']),
            '来店経路' => $opt('source', $r['source_id']),
            'きっかけ' => $opt('trigger', $r['trigger_id']),
            'ステータス' => Master::STATUSES[$r['status']] ?? (string)$r['status'],
            'フラグ' => implode('、', array_map(fn ($f) => (string)$f['name'], $r['flags'])),
            '注文' => implode(' / ', array_map(fn ($o) => $o['name'] . ' ' . number_format((int)$o['unit_price']) . '円×' . $o['qty'], $r['orders'])),
            '予約メモ' => (string)$r['memo1'],
            '予約メモ2' => (string)$r['memo2'],
        ];
    }

    /** @param array<string, mixed>|null $a @param array<string, mixed>|null $b @return array<string, array{0: string, 1: string}> */
    public static function diff(?array $a, ?array $b): array
    {
        $sa = self::summary($a);
        $sb = self::summary($b);
        $d = [];
        foreach ($sb as $k => $v) {
            $old = $sa[$k] ?? '';
            if ($old !== $v) {
                $d[$k] = [$old, $v];
            }
        }
        return $d;
    }

    private static function newCode(): string
    {
        do {
            $c = '';
            for ($i = 0; $i < 6; $i++) {
                $c .= self::CODE_CHARS[random_int(0, strlen(self::CODE_CHARS) - 1)];
            }
        } while (Db::value('SELECT COUNT(*) FROM reservations WHERE code = ?', [$c]) > 0);
        return $c;
    }

    /** 予約の表示名（看板名 → グループ名 → 顧客名） @param array<string, mixed> $r */
    public static function title(array $r): string
    {
        foreach ([(string)($r['group_name'] ?? ''), Customers::displayName($r['customer'] ?? null)] as $s) {
            if (trim($s) !== '') {
                return $s;
            }
        }
        return '（名称未設定）';
    }
}
