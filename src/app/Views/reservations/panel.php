<?php
use App\Core\App;
use App\Core\Csrf;
use App\Core\View;
use App\Services\Customers;
use App\Services\Master;
$r = $panel['r'];
$old = $panel['old']['post'] ?? null;
$errors = $panel['old']['errors'] ?? [];
$val = fn (string $k) => $old !== null ? ($old[$k] ?? '') : ($r[$k] ?? '');
$venueIds = $old !== null ? array_map('intval', (array)($old['venue_ids'] ?? [])) : array_map(fn ($v) => (int)$v['id'], $r['venues']);
$flagIds = $old !== null ? array_map('intval', (array)($old['flag_ids'] ?? [])) : array_map(fn ($f) => (int)$f['id'], $r['flags']);
$orders = $r['orders'];
if ($old !== null) {
    $orders = [];
    foreach ((array)($old['orders']['name'] ?? []) as $i => $n) {
        $orders[] = ['menu_item_id' => $old['orders']['menu_item_id'][$i] ?? '', 'name' => $n, 'unit_price' => $old['orders']['unit_price'][$i] ?? 0, 'qty' => $old['orders']['qty'][$i] ?? 1];
    }
}
$custId = (int)$val('customer_id');
$custLabel = $custId > 0 ? Customers::displayName(\App\Core\Db::row('SELECT * FROM customers WHERE id = ?', [$custId])) : '';
$showSignage = $old !== null ? (($old['show_on_signage'] ?? '') === '1') : ((int)$r['show_on_signage'] === 1);
$menu = Master::menuItems();
$curShop = null;
?>
<form method="post" action="<?= App::url('/reservations/save') ?>" class="rsv-form" id="rsvForm">
  <?= Csrf::field() ?>
  <input type="hidden" name="id" value="<?= View::e($r['id'] ?? '') ?>">
  <input type="hidden" name="return" value="<?= View::e($returnUrl) ?>">
  <div class="panel-head">
    <a class="btn btn-sm" href="<?= View::e(App::url($returnUrl)) ?>">キャンセル</a>
    <h2><?= $panel['mode'] === 'edit' ? '予約の編集' : '新規予約' ?><?php if (!empty($r['code'])): ?> <code><?= View::e($r['code']) ?></code><?php endif; ?></h2>
    <button class="btn btn-primary btn-sm">予約保存</button>
  </div>
  <?php foreach ($errors as $e): ?><div class="flash flash-warn"><?= View::e($e) ?></div><?php endforeach; ?>

  <div class="row3">
    <label>日付<input type="date" name="rsv_date" value="<?= View::e($val('rsv_date')) ?>" required></label>
    <label>開始<input type="time" name="start_time" value="<?= View::e($val('start_time')) ?>" step="300" required></label>
    <label>終了<input type="time" name="end_time" value="<?= View::e($val('end_time')) ?>" step="300" required></label>
  </div>
  <div class="row3">
    <label>大人<input type="number" name="adults" min="0" value="<?= View::e($val('adults')) ?>"></label>
    <label>子供<input type="number" name="children" min="0" value="<?= View::e($val('children')) ?>"></label>
    <label>幼児<input type="number" name="infants" min="0" value="<?= View::e($val('infants')) ?>"></label>
  </div>

  <fieldset><legend>会場・テーブル（複数可）</legend>
    <input type="search" class="venue-filter" placeholder="会場名で絞り込み">
    <div class="venue-picks">
    <?php foreach (Master::venues() as $v): ?>
      <?php if ($curShop !== $v['shop_id']): $curShop = $v['shop_id']; ?><div class="pick-group"><?= View::e($v['shop_name']) ?></div><?php endif; ?>
      <label class="pick"><input type="checkbox" name="venue_ids[]" value="<?= (int)$v['id'] ?>" <?= in_array((int)$v['id'], $venueIds, true) ? 'checked' : '' ?>><span><?= View::e($v['name']) ?></span></label>
    <?php endforeach; ?>
    </div>
    <label class="check"><input type="checkbox" name="allow_overlap" value="1"> 重複を許可（同じ会場・同じ時間に重ねて保存）</label>
  </fieldset>

  <fieldset><legend>顧客</legend>
    <input type="hidden" name="customer_id" value="<?= $custId ?: '' ?>" id="customerId">
    <div class="cust-current" id="custCurrent" <?= $custId ? '' : 'hidden' ?>><span id="custLabel"><?= View::e($custLabel) ?></span> <button type="button" class="linkbtn" id="custClear">変更</button></div>
    <div id="custSearchBox" <?= $custId ? 'hidden' : '' ?>>
      <input type="search" id="custSearch" placeholder="氏名・カナ・会社・電話で検索" autocomplete="off">
      <ul class="cust-results" id="custResults"></ul>
      <details class="newcust" <?= ($old && implode('', array_map(fn ($k) => (string)($old['nc_' . $k] ?? ''), ['last_name', 'company', 'tel'])) !== '') ? 'open' : '' ?>>
        <summary>新しい顧客として登録</summary>
        <div class="row2">
          <label>姓<input name="nc_last_name" value="<?= View::e($old['nc_last_name'] ?? '') ?>"></label>
          <label>名<input name="nc_first_name" value="<?= View::e($old['nc_first_name'] ?? '') ?>"></label>
          <label>セイ<input name="nc_last_kana" value="<?= View::e($old['nc_last_kana'] ?? '') ?>"></label>
          <label>メイ<input name="nc_first_kana" value="<?= View::e($old['nc_first_kana'] ?? '') ?>"></label>
        </div>
        <label>会社名<input name="nc_company" value="<?= View::e($old['nc_company'] ?? '') ?>"></label>
        <div class="row2">
          <label>電話<input name="nc_tel" type="tel" value="<?= View::e($old['nc_tel'] ?? '') ?>"></label>
          <label>メール<input name="nc_email" type="email" value="<?= View::e($old['nc_email'] ?? '') ?>"></label>
        </div>
      </details>
    </div>
  </fieldset>

  <label>グループ名（社内向け）<input name="group_name" value="<?= View::e($val('group_name')) ?>"></label>
  <label>看板名（サイネージ・帳票に出る名前。空ならグループ名）<input name="sign_name" value="<?= View::e($val('sign_name')) ?>"></label>
  <label class="check"><input type="checkbox" name="show_on_signage" value="1" <?= $showSignage ? 'checked' : '' ?>> サイネージに表示する</label>

  <div class="row3">
    <label>用途<?= View::select('purpose_id', Master::options('purpose'), $val('purpose_id'), '', '—') ?></label>
    <label>来店経路<?= View::select('source_id', Master::options('source'), $val('source_id'), '', '—') ?></label>
    <label>きっかけ<?= View::select('trigger_id', Master::options('trigger'), $val('trigger_id'), '', '—') ?></label>
  </div>
  <label>ステータス<?= View::select('status', Master::STATUSES, $val('status')) ?></label>

  <fieldset><legend>フラグ</legend>
    <div class="flag-picks">
    <?php foreach (Master::flags() as $f): ?>
      <label class="flagpick" style="--c: <?= View::e($f['color'] ?: '#888') ?>"><input type="checkbox" name="flag_ids[]" value="<?= (int)$f['id'] ?>" <?= in_array((int)$f['id'], $flagIds, true) ? 'checked' : '' ?>><span><?= View::e($f['name']) ?></span></label>
    <?php endforeach; ?>
    </div>
  </fieldset>

  <fieldset><legend>注文</legend>
    <table class="orders" id="orders">
      <thead><tr><th>メニュー</th><th>単価(税込)</th><th>数量</th><th></th></tr></thead>
      <tbody>
      <?php foreach (array_merge($orders, [['menu_item_id' => '', 'name' => '', 'unit_price' => '', 'qty' => 1]]) as $o): ?>
        <tr>
          <td><select class="order-menu" name="orders[menu_item_id][]"><option value="">（自由入力）</option>
            <?php foreach ($menu as $m): ?><option value="<?= (int)$m['id'] ?>" data-name="<?= View::e($m['name']) ?>" data-price="<?= (int)$m['price'] ?>" <?= (string)$o['menu_item_id'] === (string)$m['id'] ? 'selected' : '' ?>><?= View::e($m['category_name'] . '｜' . $m['name']) ?></option><?php endforeach; ?>
          </select><input name="orders[name][]" value="<?= View::e($o['name']) ?>" placeholder="品名"></td>
          <td><input name="orders[unit_price][]" value="<?= View::e($o['unit_price']) ?>" inputmode="numeric" class="w-price"></td>
          <td><input type="number" name="orders[qty][]" value="<?= View::e($o['qty']) ?>" min="1" class="w-qty"></td>
          <td><button type="button" class="linkbtn order-del">削除</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td><button type="button" class="btn btn-sm" id="orderAdd">＋ 行を追加</button></td><td colspan="3" class="num">合計 <b id="orderTotal">0</b> 円</td></tr></tfoot>
    </table>
  </fieldset>

  <label>予約メモ<textarea name="memo1" rows="5"><?= View::e($val('memo1')) ?></textarea></label>
  <label>予約メモ2<textarea name="memo2" rows="4"><?= View::e($val('memo2')) ?></textarea></label>

  <div class="panel-foot">
    <?php if (!empty($r['id'])): ?><a href="<?= App::url('/reservations/' . (int)$r['id']) ?>">予約詳細・操作履歴</a><?php endif; ?>
    <button class="btn btn-primary">予約保存</button>
  </div>
</form>
