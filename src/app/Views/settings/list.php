<?php use App\Core\App; use App\Core\Csrf; use App\Core\View; ?>
<div class="pagehead"><h1><a href="<?= App::url('/settings') ?>">設定</a> › <?= View::e($d['label']) ?></h1><a class="btn btn-sm btn-primary" href="?t=<?= $t ?>&new=1">＋ 追加</a></div>
<?php foreach ($errors as $e): ?><div class="flash flash-warn"><?= View::e($e) ?></div><?php endforeach; ?>
<?php if ($edit !== null): ?>
<form method="post" action="<?= App::url('/settings/save') ?>?t=<?= $t ?>" class="card form narrow">
  <?= Csrf::field() ?><input type="hidden" name="id" value="<?= View::e($edit['id'] ?? '') ?>">
  <?php foreach ($d['fields'] as $f => $def): [$label, $type] = $def; $v = $edit[$f] ?? ($type === 'bool' && !isset($edit['id']) ? 1 : ''); ?>
    <?php if ($type === 'bool'): ?><label class="check"><input type="checkbox" name="<?= $f ?>" value="1" <?= (int)$v === 1 ? 'checked' : '' ?>> <?= View::e($label) ?></label>
    <?php else: ?><label><?= View::e($label) ?><?php if (!empty($def[2])): ?> <span class="req">必須</span><?php endif; ?>
      <?php if ($type === 'select'): ?><?= View::select($f, $def[3], $v, '', '—') ?>
      <?php elseif ($type === 'password'): ?><input type="password" name="<?= $f ?>" autocomplete="new-password" minlength="8">
      <?php elseif ($type === 'color'): ?><input type="color" name="<?= $f ?>" value="<?= View::e($v ?: '#888888') ?>">
      <?php else: ?><input name="<?= $f ?>" value="<?= View::e($v) ?>" <?= $type === 'int' ? 'type="number"' : '' ?>><?php endif; ?></label><?php endif; ?>
  <?php endforeach; ?>
  <button class="btn btn-primary">保存</button> <a href="?t=<?= $t ?>">閉じる</a>
</form>
<?php endif; ?>
<table class="table">
  <thead><tr><?php foreach ($d['fields'] as $f => $def): if ($def[1] === 'password') continue; ?><th><?= View::e(preg_replace('/（.*$/u', '', $def[0])) ?></th><?php endforeach; ?><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $row): ?><tr class="<?= isset($row['is_active']) && !(int)$row['is_active'] ? 'inactive' : '' ?>">
    <?php foreach ($d['fields'] as $f => $def): if ($def[1] === 'password') continue; $v = $row[$f] ?? ''; ?>
      <td><?php if ($def[1] === 'bool'): ?><?= (int)$v ? '○' : '' ?><?php elseif ($def[1] === 'select'): ?><?= View::e($def[3][$v] ?? '') ?><?php elseif ($def[1] === 'color'): ?><span class="flag" style="--c: <?= View::e($v ?: '#888') ?>"><?= View::e($v) ?></span><?php else: ?><?= View::e($v) ?><?php endif; ?></td>
    <?php endforeach; ?>
    <td><a href="?t=<?= $t ?>&id=<?= (int)$row['id'] ?>">編集</a></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
