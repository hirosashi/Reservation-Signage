<?php
use App\Core\App;
use App\Core\View;
use App\Services\Customers;
use App\Services\Master;
/** @var array $r */
$editUrl = App::url($basePath ?? App::currentPath()) . '?' . http_build_query(array_merge($baseQuery ?? [], ['edit' => $r['id']]));
?>
<tr class="st-<?= View::e($r['status']) ?>">
  <td class="nowrap"><a href="<?= View::e($editUrl) ?>"><?= View::e($r['start_time']) ?>〜<?= View::e($r['end_time']) ?></a></td>
  <td class="num"><?= (int)$r['pax'] ?>名<?php if ($r['children'] || $r['infants']): ?><br><small>大<?= (int)$r['adults'] ?> 子<?= (int)$r['children'] ?> 幼<?= (int)$r['infants'] ?></small><?php endif; ?></td>
  <td><?= $r['venues'] ? View::e(implode('、', array_column($r['venues'], 'name'))) : '<span class="badge badge-warn">会場未定</span>' ?></td>
  <td><a href="<?= App::url('/reservations/' . $r['id']) ?>"><strong><?= View::e($r['group_name'] ?: '—') ?></strong></a>
    <?php if ($r['customer']): ?><br><small><?= View::e(Customers::displayName($r['customer'])) ?> <?= View::e($r['customer']['tel']) ?></small><?php endif; ?>
    <?php if ($r['sign_name']): ?><br><small class="muted">看板：<?= View::e($r['sign_name']) ?></small><?php endif; ?></td>
  <td class="memo-cell">
    <?php foreach ($r['flags'] as $f): ?><span class="flag" style="--c: <?= View::e($f['color'] ?: '#888') ?>"><?= View::e($f['name']) ?></span><?php endforeach; ?>
    <?php if ($r['orders']): ?><div class="small">注文 <?= View::yen($r['order_total']) ?>（<?= View::e(implode('・', array_column($r['orders'], 'name'))) ?>）</div><?php endif; ?>
    <?php if ($r['memo1']): ?><div class="small memo-clip"><?= View::e($r['memo1']) ?></div><?php endif; ?>
  </td>
  <td class="nowrap"><code><?= View::e($r['code']) ?></code></td>
  <td><span class="status status-<?= View::e($r['status']) ?>"><?= View::e(Master::STATUSES[$r['status']] ?? $r['status']) ?></span>
    <?php if (!(int)$r['show_on_signage']): ?><br><small class="muted">サイネージ非表示</small><?php endif; ?></td>
</tr>
