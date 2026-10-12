<?php use App\Core\App; use App\Core\View; ?>
<h1>設定</h1>
<div class="tiles">
<?php foreach ($defs as $t => $d): ?><a class="tile" href="<?= App::url('/settings') ?>?t=<?= $t ?>"><?= View::e($d['label']) ?></a><?php endforeach; ?>
<a class="tile" href="<?= App::url('/screens') ?>">サイネージ設定</a>
</div>
