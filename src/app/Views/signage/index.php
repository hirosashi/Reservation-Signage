<?php use App\Core\App; use App\Core\Auth; use App\Core\Csrf; use App\Core\View; ?>
<div class="pagehead"><h1>サイネージ</h1></div>
<div class="grid2">
  <section class="card"><h2>表示URL（端末に設定）</h2>
    <p>会場案内（縦型50インチ）：<br><a href="<?= App::url('signage/venue.html') ?>" target="_blank" rel="noopener"><?= View::e(App::url('signage/venue.html')) ?></a></p>
    <p>日時を指定して確認：<a href="<?= App::url('signage/venue.html') ?>?date=<?= View::e($date) ?>&time=17:30" target="_blank" rel="noopener"><?= View::e($date) ?> 17:30 の表示</a></p>
    <p class="muted small">表示データ：<a href="<?= App::url('api/signage/events') ?>?date=<?= View::e($date) ?>" target="_blank" rel="noopener"><?= View::e(App::url('api/signage/events')) ?></a>（旧 tablecheck-sync.php と同じ形式）</p>
    <p class="muted small">予約で「サイネージに表示する」をオンにし、仮予約・キャンセル・無断キャンセル以外のステータスで、会場が入っている予約が表示されます。「サイネージ非表示」設定のフラグが付いた予約は出ません。</p>
    <?php if (Auth::isAdmin()): ?>
    <form method="post" action="<?= App::url('/screens/settings') ?>" class="inline"><?= Csrf::field() ?>
      <label>表示データの日数 <input type="number" name="signage_days" min="1" max="31" value="<?= View::e($days) ?>" class="w-qty"></label><button class="btn btn-sm">保存</button></form>
    <?php endif; ?>
  </section>
  <section class="card"><h2>プレビュー（<?= View::e($date) ?> 17:30）</h2>
    <div class="signage-preview"><iframe src="<?= App::url('signage/venue.html') ?>?date=<?= View::e($date) ?>&time=17:30" title="会場案内プレビュー"></iframe></div>
  </section>
</div>
<section class="card"><h2><?= View::e($date) ?> のサイネージ表示内容（<?= count($events) ?>件）</h2>
  <?php if (!$events): ?><p class="empty">表示する予約はありません。</p><?php else: ?>
  <table class="table"><thead><tr><th>時間</th><th>会場</th><th>館・階</th><th>表示名</th><th>人数</th></tr></thead><tbody>
  <?php foreach ($events as $e): ?><tr><td><?= View::e($e['time'] . '〜' . $e['endTime']) ?></td><td><?= View::e($e['venue']) ?></td><td><?= View::e($e['location']) ?></td><td><?= View::e($e['name']) ?></td><td class="num"><?= View::e($e['pax']) ?></td></tr><?php endforeach; ?>
  </tbody></table><?php endif; ?>
</section>
