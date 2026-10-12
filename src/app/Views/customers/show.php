<?php use App\Core\App; use App\Core\View; use App\Services\Customers; ?>
<div class="pagehead">
  <h1><?= View::e(Customers::displayName($c)) ?></h1>
  <a class="btn btn-sm" href="<?= App::url('/customers/edit') ?>?id=<?= (int)$c['id'] ?>">編集</a>
  <?php foreach ($tags as $t): ?><span class="badge"><?= View::e($t) ?></span><?php endforeach; ?>
</div>
<section class="card">
  <dl class="dl">
    <dt>カナ</dt><dd><?= View::e(trim($c['last_kana'] . ' ' . $c['first_kana'])) ?></dd>
    <dt>会社・部署・役職</dt><dd><?= View::e(trim($c['company'] . ' ' . $c['department'] . ' ' . $c['title'])) ?></dd>
    <dt>電話</dt><dd><?= View::e($c['tel']) ?></dd><dt>メール</dt><dd><?= View::e($c['email']) ?></dd>
    <dt>来館</dt><dd><?= $stats['count'] ?>回（最終 <?= View::d($stats['last']) ?>）</dd>
    <dt>顧客メモ</dt><dd class="pre"><?= View::e($c['memo']) ?></dd>
  </dl>
</section>
<section class="card"><h2>予約履歴</h2>
<?php if (!$reservations): ?><p class="empty">予約はありません。</p><?php else: ?>
<table class="table"><thead><tr><th>日付</th><th>時間</th><th>人数</th><th>会場</th><th>グループ名</th><th>注文</th><th>ステータス</th></tr></thead><tbody>
<?php foreach ($reservations as $r): ?><tr><td><a href="<?= App::url('/reservations/' . $r['id']) ?>"><?= View::d($r['rsv_date']) ?></a></td><td><?= View::e($r['start_time'] . '〜' . $r['end_time']) ?></td><td class="num"><?= (int)$r['pax'] ?></td><td><?= View::e(implode('、', array_column($r['venues'], 'name'))) ?></td><td><?= View::e($r['group_name']) ?></td><td class="num"><?= View::yen($r['order_total']) ?></td><td><span class="status status-<?= View::e($r['status']) ?>"><?= View::e(\App\Services\Master::STATUSES[$r['status']] ?? '') ?></span></td></tr><?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</section>
