<?php use App\Core\App; use App\Core\Csrf; use App\Core\View; use App\Services\Master; ?>
<h1><?= View::e($title) ?></h1>
<?php foreach ($errors as $e): ?><div class="flash flash-warn"><?= View::e($e) ?></div><?php endforeach; ?>
<form method="post" action="<?= App::url('/customers/save') ?>" class="card form narrow">
  <?= Csrf::field() ?><input type="hidden" name="id" value="<?= View::e($c['id'] ?? '') ?>">
  <div class="row2">
  <?php foreach ($fields as $f => $label): if ($f === 'memo') continue; ?>
    <label><?= View::e($label) ?>
      <?php if ($f === 'gender'): ?><?= View::select('gender', ['男性' => '男性', '女性' => '女性', 'その他' => 'その他'], $c['gender'] ?? '', '', '—') ?>
      <?php else: ?><input name="<?= $f ?>" value="<?= View::e($c[$f] ?? '') ?>" <?= $f === 'email' ? 'type="email"' : ($f === 'tel' ? 'type="tel"' : '') ?>><?php endif; ?>
    </label>
  <?php endforeach; ?>
  </div>
  <label>顧客メモ<textarea name="memo" rows="5"><?= View::e($c['memo'] ?? '') ?></textarea></label>
  <fieldset><legend>タグ</legend><?php foreach (Master::options('customer_tag') as $id => $n): ?><label class="check inline"><input type="checkbox" name="tag_ids[]" value="<?= $id ?>" <?= in_array($id, $tagIds, true) ? 'checked' : '' ?>> <?= View::e($n) ?></label><?php endforeach; ?></fieldset>
  <button class="btn btn-primary">保存</button>
  <?php if (!empty($c['id'])): ?><a href="<?= App::url('/customers/' . (int)$c['id']) ?>">戻る</a><?php endif; ?>
</form>
