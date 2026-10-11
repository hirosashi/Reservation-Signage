<?php
use App\Core\App;
use App\Core\View;
use App\Services\Report;
$dt = new DateTimeImmutable($date);
$w = ['日', '月', '火', '水', '木', '金', '土'][(int)$dt->format('w')];
$fields = ['mark', 'name', 'type', 'pax', 'time', 'note'];
?>
<div class="toolbar no-print">
  <form method="get"><label>日付 <input type="date" name="date" value="<?= View::e($date) ?>" onchange="this.form.submit()"></label></form>
  <button type="button" onclick="window.print()">PDF出力（印刷）</button>
  <a class="btn" href="<?= App::url('/report/xlsx') ?>?date=<?= View::e($date) ?>">Excel出力</a>
  <a href="<?= App::url('/') ?>?date=<?= View::e($date) ?>">予約管理へ戻る</a>
</div>
<div class="sheet">
  <header class="sheet-head"><h1>宴会ご予約一覧表</h1><div><?= $dt->format('Y年n月j日') ?>（<?= $w ?>）</div></header>
  <table class="report-matrix">
    <colgroup><?php foreach (Report::PERIODS as $_): foreach ([15, 4, 40, 6, 5, 7, 23] as $r): ?><col style="width: <?= $r / 3 ?>%"><?php endforeach; endforeach; ?></colgroup>
    <thead>
      <tr><?php foreach (Report::PERIODS as $k => $p): ?><th class="period-heading" colspan="<?= count(Report::COLUMNS) ?>"><?= View::e($p['label']) ?><?= $report['totals'][$k] ? '　' . $report['totals'][$k] . '名' : '' ?></th><?php endforeach; ?></tr>
      <tr><?php foreach (Report::PERIODS as $_): foreach (Report::COLUMNS as $c): ?><th><?= View::e($c) ?></th><?php endforeach; endforeach; ?></tr>
    </thead>
    <tbody>
    <?php foreach ($report['rows'] as $row): ?><tr>
      <?php foreach (Report::PERIODS as $k => $_): ?>
        <td class="venue-cell"><?= View::e($row['venue']) ?></td>
        <?php foreach ($fields as $f): ?><td class="<?= $f ?>-cell"><?php foreach ($row['cells'][$k] as $e): ?><div class="report-entry"><?= View::e($e[$f]) ?></div><?php endforeach; ?></td><?php endforeach; ?>
      <?php endforeach; ?>
    </tr><?php endforeach; ?>
    </tbody>
  </table>
</div>
