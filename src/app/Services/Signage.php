<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;

/**
 * サイネージ用イベント。旧 tablecheck-sync.php の GET 応答と同じ形で返すので、
 * 会場案内（signage/venue.html）の表示ロジックはそのまま使える。
 */
final class Signage
{
    /** @return array<string, mixed> */
    public static function feed(string $from, int $days): array
    {
        $to = (new \DateTimeImmutable($from))->modify('+' . max(0, $days - 1) . ' days')->format('Y-m-d');
        $events = [];
        foreach (Reservations::between($from, $to, false) as $r) {
            $e = self::event($r);
            if ($e !== null) {
                $events[] = $e;
            }
        }
        $locations = [];
        foreach (Master::venues() as $v) {
            if ((string)$v['building'] !== '') {
                $locations[self::venueLabel($v)] = (string)$v['building'];
            }
        }
        return [
            'ok' => true,
            'source' => 'reservation-signage',
            'syncedAt' => Clock::now()->format(DATE_ATOM),
            'startDate' => $from,
            'days' => $days,
            'filters' => ['status' => [], 'flags' => []],
            'venueLocations' => $locations,
            'venueAliases' => new \stdClass(),
            'events' => $events,
        ];
    }

    /** @param array<string, mixed> $r @return array<string, mixed>|null */
    public static function event(array $r): ?array
    {
        if (in_array($r['status'], Master::HIDDEN_STATUSES, true) || (int)$r['show_on_signage'] !== 1 || $r['venues'] === []) {
            return null;
        }
        foreach ($r['flags'] as $f) {
            if ((int)$f['hide_on_signage'] === 1) {
                return null;
            }
        }
        $name = trim((string)$r['sign_name']);
        if ($name === '') {
            $name = trim((string)$r['group_name']);
        }
        $start = self::minutes((string)$r['start_time']);
        $end = self::minutes((string)$r['end_time']);
        return [
            'id' => 'rsv-' . $r['code'],
            'source' => 'reservation',
            'date' => (string)$r['rsv_date'],
            'visibleOnSignage' => true,
            'time' => (string)$r['start_time'],
            'endTime' => (string)$r['end_time'],
            'durationMinutes' => $end > $start ? $end - $start : 0,
            'venue' => implode(', ', array_map([self::class, 'venueLabel'], $r['venues'])),
            'location' => (string)($r['venues'][0]['building'] ?? ''),
            'name' => $name,
            'pax' => (string)$r['pax'],
            'status' => (string)$r['status'],
            'flagIds' => array_map(fn ($f) => (string)$f['id'], $r['flags']),
            'flags' => array_map(fn ($f) => (string)$f['name'], $r['flags']),
        ];
    }

    /** @param array<string, mixed> $v */
    public static function venueLabel(array $v): string
    {
        $s = trim((string)($v['signage_name'] ?? ''));
        return $s !== '' ? $s : (string)$v['name'];
    }

    public static function minutes(string $hm): int
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $hm, $m)) {
            return 0;
        }
        return (int)$m[1] * 60 + (int)$m[2];
    }
}
