<?php use App\Core\App; use App\Core\View; use App\Services\Master; ?>
<div class="pagehead">
  <h1>リスト</h1>
  <form method="get" class="inline">
    <input type="hidden" name="date" value="<?= View::e($date) ?>">
    <?= View::select('status', Master::STATUSES, $status, 'onchange="this.form.submit()"', '有効な予約すべて') ?>
  </form>
  <span class="muted"><?= count($reservations) ?>件・<?= array_sum(array_column($reservations, 'pax')) ?>名</span>
</div>
<?= View::partial('partials/rsv_table', ['reservations' => $reservations, 'baseQuery' => array_filter(['date' => $date, 'status' => $status]), 'basePath' => '/list']) ?>
