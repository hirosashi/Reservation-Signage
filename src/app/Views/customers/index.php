<?php use App\Core\App; use App\Core\View; use App\Services\Customers; ?>
<div class="pagehead">
  <h1>顧客検索</h1>
  <form method="get" class="inline"><input type="hidden" name="date" value="<?= View::e($date) ?>"><input type="search" name="q" value="<?= View::e($q) ?>" placeholder="氏名・カナ・会社・電話・メール"><button class="btn btn-sm">検索</button></form>
  <a class="btn btn-sm btn-primary" href="<?= App::url('/customers/edit') ?>">＋ 顧客を登録</a>
</div>
<?php if (!$customers): ?><p class="empty">該当する顧客はありません。</p><?php else: ?>
<table class="table"><thead><tr><th>氏名・会社</th><th>カナ</th><th>電話</th><th>メール</th><th>更新</th></tr></thead><tbody>
<?php foreach ($customers as $c): ?>
  <tr><td><a href="<?= App::url('/customers/' . $c['id']) ?>"><?= View::e(Customers::displayName($c)) ?></a></td><td><?= View::e(trim($c['last_kana'] . ' ' . $c['first_kana'])) ?></td><td><?= View::e($c['tel']) ?></td><td><?= View::e($c['email']) ?></td><td><?= View::d($c['updated_at']) ?></td></tr>
<?php endforeach; ?></tbody></table>
<?php endif; ?>
