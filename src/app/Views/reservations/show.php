<?php
use App\Core\App;
use App\Core\Csrf;
use App\Core\View;
use App\Services\Customers;
use App\Services\Master;
$c = $r['customer'];
$back = '/reservations/' . $r['id'];
?>
<div class="pagehead">
  <h1>予約詳細 <code><?= View::e($r['code']) ?></code></h1>
  <a class="btn btn-primary btn-sm" href="<?= App::url('/list') ?>?date=<?= View::e($r['rsv_date']) ?>&edit=<?= (int)$r['id'] ?>">編集</a>
  <form method="post" action="<?= App::url('/reservations/' . $r['id'] . '/status') ?>" class="inline">
    <?= Csrf::field() ?><input type="hidden" name="return" value="<?= View::e($back) ?>">
    <?= View::select('status', Master::STATUSES, $r['status']) ?><button class="btn btn-sm">ステータス変更</button>
  </form>
  <form method="post" action="<?= App::url('/reservations/' . $r['id'] . '/delete') ?>" class="inline" onsubmit="return confirm('この予約を削除しますか？')">
    <?= Csrf::field() ?><input type="hidden" name="return" value="/list?date=<?= View::e($r['rsv_date']) ?>"><button class="btn btn-sm btn-danger">削除</button>
  </form>
</div>
<div class="grid2 detail">
  <section class="card"><h2>顧客</h2>
    <?php if ($c): ?>
      <p><a href="<?= App::url('/customers/' . $c['id']) ?>"><strong><?= View::e(Customers::displayName($c)) ?></strong></a></p>
      <dl class="dl"><dt>カナ</dt><dd><?= View::e(trim($c['last_kana'] . ' ' . $c['first_kana'])) ?></dd><dt>電話</dt><dd><?= View::e($c['tel']) ?></dd><dt>メール</dt><dd><?= View::e($c['email']) ?></dd>
      <dt>来館</dt><dd><?= (int)($stats['count'] ?? 0) ?>回（最終 <?= View::d($stats['last'] ?? null) ?>）</dd><dt>顧客メモ</dt><dd class="pre"><?= View::e($c['memo']) ?></dd></dl>
    <?php else: ?><p class="muted">顧客は未登録です。</p><?php endif; ?>
  </section>
  <section class="card"><h2>予約</h2>
    <dl class="dl"><?php foreach ($summary as $k => $v): if ($k === '注文') continue; ?><dt><?= View::e($k) ?></dt><dd class="pre"><?= View::e($v) ?></dd><?php endforeach; ?>
      <dt>作成</dt><dd><?= View::dt($r['created_at']) ?> <?= View::e($users[$r['created_by']] ?? '') ?></dd>
      <dt>更新</dt><dd><?= View::dt($r['updated_at']) ?> <?= View::e($users[$r['updated_by']] ?? '') ?></dd></dl>
    <?php if ($r['orders']): ?>
    <table class="table"><thead><tr><th>注文</th><th class="num">単価</th><th class="num">数量</th><th class="num">小計</th></tr></thead><tbody>
      <?php foreach ($r['orders'] as $o): ?><tr><td><?= View::e($o['name']) ?></td><td class="num"><?= View::yen($o['unit_price']) ?></td><td class="num"><?= (int)$o['qty'] ?></td><td class="num"><?= View::yen((int)$o['unit_price'] * (int)$o['qty']) ?></td></tr><?php endforeach; ?>
      <tr><th colspan="3">合計（税込）</th><th class="num"><?= View::yen($r['order_total']) ?></th></tr></tbody></table>
    <?php endif; ?>
  </section>
</div>
<section class="card"><h2>操作履歴</h2>
  <?php foreach ($histories as $h): ?>
    <div class="hist">
      <div class="hist-head"><?= View::dt($h['created_at']) ?>　<?= View::e($h['user_name'] ?? '') ?>　<b><?= ['create' => '作成', 'update' => '変更', 'delete' => '削除'][$h['action']] ?? View::e($h['action']) ?></b></div>
      <?php if ($h['detail']): ?><table class="diff"><?php foreach ($h['detail'] as $k => [$a, $b]): ?><tr><th><?= View::e($k) ?></th><td class="del"><?= View::e($a) ?></td><td class="add"><?= View::e($b) ?></td></tr><?php endforeach; ?></table><?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>
