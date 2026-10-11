<?php
declare(strict_types=1);

namespace App\Services;

/** 宴会ご予約一覧表（会場行 × 時間帯3区分）。「記」「種別」は当面空欄 */
final class Report
{
    public const PERIODS = [
        'morning' => ['label' => '8:00〜12:00', 'max' => 12 * 60],
        'afternoon' => ['label' => '12:00〜17:00', 'max' => 17 * 60],
        'evening' => ['label' => '17:00〜21:00', 'max' => 24 * 60],
    ];

    public const COLUMNS = ['会場', '記', '看板名', '種別', '人数', '時間', '備考'];

    /**
     * @return array{rows: array<int, array{venue: string, cells: array<string, array<int, array<string, string>>>}>, totals: array<string, int>}
     */
    public static function build(string $date): array
    {
        $reservations = array_filter(
            Reservations::between($date, $date, false),
            fn ($r) => !in_array($r['status'], Master::HIDDEN_STATUSES, true)
        );
        $rows = [];
        $index = [];
        foreach (Master::venues() as $v) {
            $label = trim((string)$v['report_name']);
            if ($label === '' || isset($index[$label])) {
                continue;
            }
            $index[$label] = count($rows);
            $rows[] = ['venue' => $label, 'cells' => array_fill_keys(array_keys(self::PERIODS), [])];
        }
        $totals = array_fill_keys(array_keys(self::PERIODS), 0);
        foreach ($reservations as $r) {
            $period = self::period((string)$r['start_time']);
            $totals[$period] += (int)$r['pax'];
            $labels = [];
            foreach ($r['venues'] as $v) {
                $label = trim((string)$v['report_name']);
                $labels[] = $label !== '' ? $label : (string)$v['name'];
            }
            if ($labels === []) {
                $labels[] = '会場未定';
            }
            $entry = [
                'mark' => '',
                'name' => trim((string)$r['sign_name']) !== '' ? (string)$r['sign_name'] : Reservations::title($r),
                'type' => '',
                'pax' => (string)$r['pax'],
                'time' => $r['start_time'] . '〜' . $r['end_time'],
                'note' => implode('、', array_map(fn ($f) => (string)$f['name'], array_filter($r['flags'], fn ($f) => (int)$f['hide_on_signage'] !== 1))),
            ];
            foreach (array_unique($labels) as $label) {
                if (!isset($index[$label])) {
                    $index[$label] = count($rows);
                    $rows[] = ['venue' => $label, 'cells' => array_fill_keys(array_keys(self::PERIODS), [])];
                }
                $rows[$index[$label]]['cells'][$period][] = $entry;
            }
        }
        return ['rows' => $rows, 'totals' => $totals];
    }

    public static function period(string $time): string
    {
        $m = Signage::minutes($time);
        foreach (self::PERIODS as $key => $p) {
            if ($m < $p['max']) {
                return $key;
            }
        }
        return 'evening';
    }
}
