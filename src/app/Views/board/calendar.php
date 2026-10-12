<?php
use App\Core\App;
use App\Core\View;
use App\Services\Report;
$prev = $first->modify('-1 month')->format('Y-m');
$next = $first->modify('+1 month')->format('Y-m');
$lead = (int)$first->format('w');
$today = \App\Core\Clock::today();
?>
<div class="pagehead">
  <h1>カレンダー</h1>
  <a class="btn btn-sm" href="?ym=<?= $prev ?>&date=<?= $prev ?>-01">◀ 前月</a>
  <strong><?= $first->format('Y年n月') ?></strong>
  <a class="btn btn-sm" href="?ym=<?= $next ?>&date=<?= $next ?>-01">翌月 ▶</a>
</div>
<table class="calendar">
  <thead><tr><?php foreach (['日', '月', '火', '水', '木', '金', '土'] as $i => $w): ?><th class="<?= $i === 0 ? 'sun' : ($i === 6 ? 'sat' : '') ?>"><?= $w ?></th><?php endforeach; ?></tr></thead>
  <tbody><tr>
  <?php for ($i = 0; $i < $lead; $i++): ?><td class="blank"></td><?php endfor; ?>
  <?php for ($d = $first; $d <= $last; $d = $d->modify('+1 day')): $ds = $d->format('Y-m-d'); $w = (int)$d->format('w'); ?>
    <?php if ($w === 0 && $ds !== $first->format('Y-m-d')): ?></tr><tr><?php endif; ?>
    <td class="<?= $w === 0 ? 'sun' : ($w === 6 ? 'sat' : '') ?> <?= $ds === $today ? 'today' : '' ?>">
      <a class="day" href="<?= App::url('/list') ?>?date=<?= $ds ?>"><?= $d->format('j') ?></a>
      <?php foreach (Report::PERIODS as $k => $p): if (empty($days[$ds][$k])) continue; ?>
        <div class="cal-p"><span><?= ['morning' => '朝', 'afternoon' => '昼', 'evening' => '夜'][$k] ?></span><?= $days[$ds][$k]['groups'] ?>組 <?= $days[$ds][$k]['pax'] ?>名</div>
      <?php endforeach; ?>
    </td>
  <?php endfor; ?>
  <?php for ($i = ((int)$last->format('w')) + 1; $i < 7; $i++): ?><td class="blank"></td><?php endfor; ?>
  </tr></tbody>
</table>
