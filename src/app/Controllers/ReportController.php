<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Services\Report;
use App\Services\Xlsx;

/** 宴会ご予約一覧表（PDF は印刷、Excel は .xlsx） */
final class ReportController
{
    public static function show(): void
    {
        Auth::requireLogin();
        $date = BoardController::date();
        View::render('report/show', ['title' => '宴会ご予約一覧表', 'date' => $date, 'report' => Report::build($date)], 'layout_report');
    }

    public static function xlsx(): void
    {
        Auth::requireLogin();
        $date = BoardController::date();
        $rep = Report::build($date);
        $rows = [];
        $head = [];
        $sub = [];
        $merges = [];
        $col = 0;
        foreach (Report::PERIODS as $key => $p) {
            $head[] = $p['label'] . ($rep['totals'][$key] > 0 ? '　' . $rep['totals'][$key] . '名' : '');
            for ($i = 1; $i < count(Report::COLUMNS); $i++) {
                $head[] = '';
            }
            $merges[] = Xlsx::col($col) . '3:' . Xlsx::col($col + count(Report::COLUMNS) - 1) . '3';
            $col += count(Report::COLUMNS);
            foreach (Report::COLUMNS as $c) {
                $sub[] = $c;
            }
        }
        $dt = new \DateTimeImmutable($date);
        $w = ['日', '月', '火', '水', '木', '金', '土'][(int)$dt->format('w')];
        $rows[] = ['宴会ご予約一覧表　' . $dt->format('Y年n月j日') . '（' . $w . '）'];
        $rows[] = [''];
        $rows[] = $head;
        $rows[] = $sub;
        $merges[] = 'A1:' . Xlsx::col($col - 1) . '1';
        foreach ($rep['rows'] as $r) {
            $line = [];
            foreach (array_keys(Report::PERIODS) as $key) {
                $es = $r['cells'][$key];
                $j = fn (string $f) => implode("\n", array_map(fn ($e) => $e[$f], $es));
                array_push($line, $r['venue'], $j('mark'), $j('name'), $j('type'), $j('pax'), $j('time'), $j('note'));
            }
            $rows[] = $line;
        }
        $widths = [];
        foreach (Report::PERIODS as $_) {
            array_push($widths, 10, 3, 24, 5, 5, 11, 16);
        }
        Xlsx::download('宴会ご予約一覧表_' . $date . '.xlsx', $dt->format('Y-m-d'), $rows, $merges, $widths);
    }
}
