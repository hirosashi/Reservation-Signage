<?php
use App\Core\App;
use App\Core\View;
use App\Services\Master;
use App\Services\Signage;
$span = ($endHour - $startHour) * 60;
$pos = fn (string $t) => max(0, min(100, (Signage::minutes($t) - $startHour * 60) / $span * 100));
$curShop = null;
$q = ['date' => $date] + ($shop ? ['shop' => $shop] : []);
?>
<div class="pagehead">
  <h1>チャート</h1>
  <form method="get" class="inline">
    <input type="hidden" name="date" value="<?= View::e($date) ?>">
    <?= View::select('shop', array_column(Master::shops(), 'name', 'id'), $shop ?: '', 'onchange="this.form.submit()"', 'すべての店舗') ?>
  </form>
  <span class="muted">空いている所をクリックすると、その会場・時間で新規予約</span>
</div>
<?php if ($unassigned): ?>
<div class="flash flash-warn">会場未定の予約：
  <?php foreach ($unassigned as $r): ?><a href="?<?= View::e(http_build_query($q + ['edit' => $r['id']])) ?>"><?= View::e($r['start_time'] . ' ' . ($r['group_name'] ?: '名称未設定')) ?></a>　<?php endforeach; ?>
</div>
<?php endif; ?>
<div class="chart" data-start="<?= $startHour ?>" data-end="<?= $endHour ?>" data-date="<?= View::e($date) ?>">
  <div class="chart-row chart-head">
    <div class="chart-label">会場</div>
    <div class="chart-track"><?php for ($h = $startHour; $h < $endHour; $h++): ?><span class="hour" style="left: <?= ($h - $startHour) * 60 / $span * 100 ?>%"><?= $h ?></span><?php endfor; ?></div>
  </div>
  <?php foreach ($venues as $v): ?>
    <?php if ($curShop !== $v['shop_id']): $curShop = $v['shop_id']; ?><div class="chart-shop"><?= View::e($v['shop_name']) ?></div><?php endif; ?>
    <div class="chart-row">
      <div class="chart-label"><?= View::e($v['name']) ?><?php if ($v['building']): ?><small><?= View::e($v['building']) ?></small><?php endif; ?></div>
      <div class="chart-track" data-venue="<?= (int)$v['id'] ?>">
        <?php for ($h = $startHour; $h < $endHour; $h++): ?><span class="gridline" style="left: <?= ($h - $startHour) * 60 / $span * 100 ?>%"></span><?php endfor; ?>
        <?php foreach ($byVenue[(int)$v['id']] ?? [] as $r): $l = $pos($r['start_time']); $w = max(1.5, $pos($r['end_time']) - $l); ?>
          <a class="bar status-<?= View::e($r['status']) ?>" style="left: <?= $l ?>%; width: <?= $w ?>%" href="?<?= View::e(http_build_query($q + ['edit' => $r['id']])) ?>" title="<?= View::e($r['start_time'] . '〜' . $r['end_time'] . ' ' . ($r['group_name'] ?? '') . ' ' . $r['pax'] . '名') ?>">
            <b><?= (int)$r['pax'] ?></b> <?= View::e($r['group_name'] ?: '名称未設定') ?>
            <?php foreach ($r['flags'] as $f): ?><i class="dot" style="--c: <?= View::e($f['color'] ?: '#888') ?>"></i><?php endforeach; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
