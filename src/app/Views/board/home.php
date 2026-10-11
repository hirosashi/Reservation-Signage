<?php use App\Core\View; use App\Services\Master; ?>
<h1>ホーム</h1>
<div class="kpis">
  <div class="kpi"><span>組数</span><strong><?= $kpi['groups'] ?></strong></div>
  <div class="kpi"><span>人数</span><strong><?= $kpi['pax'] ?><small>名</small></strong></div>
  <div class="kpi"><span>注文金額</span><strong><?= View::yen($kpi['amount']) ?></strong></div>
  <div class="kpi <?= $kpi['unassigned'] ? 'warn' : '' ?>"><span>会場未定</span><strong><?= $kpi['unassigned'] ?></strong></div>
</div>
<div class="grid2">
  <section class="card"><h2>ステータス</h2>
    <ul class="countlist"><?php foreach (Master::STATUSES as $k => $label): if (empty($statusCounts[$k])) continue; ?>
      <li><a href="<?= \App\Core\App::url('/list') ?>?date=<?= View::e($date) ?>&status=<?= $k ?>"><span class="status status-<?= $k ?>"><?= View::e($label) ?></span></a><b><?= $statusCounts[$k] ?></b></li>
    <?php endforeach; ?><?php if (!$statusCounts): ?><li class="muted">予約はありません</li><?php endif; ?></ul>
  </section>
  <section class="card"><h2>フラグ</h2>
    <ul class="countlist"><?php foreach ($flagCounts as $name => $n): ?><li><span><?= View::e($name) ?></span><b><?= $n ?></b></li><?php endforeach; ?>
    <?php if (!$flagCounts): ?><li class="muted">フラグ付きの予約はありません</li><?php endif; ?></ul>
  </section>
</div>
<section class="card"><h2>本日の予約</h2>
  <?= View::partial('partials/rsv_table', ['reservations' => $reservations, 'baseQuery' => ['date' => $date], 'basePath' => '/']) ?>
</section>
