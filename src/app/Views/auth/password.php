<?php use App\Core\App; use App\Core\Csrf; use App\Core\View; ?>
<h1>パスワード変更</h1>
<?php foreach ($errors as $e): ?><div class="flash flash-warn"><?= View::e($e) ?></div><?php endforeach; ?>
<form method="post" action="<?= App::url('/password') ?>" class="card form narrow">
  <?= Csrf::field() ?>
  <label>現在のパスワード<input type="password" name="current" required autocomplete="current-password"></label>
  <label>新しいパスワード（8文字以上）<input type="password" name="new" required minlength="8" autocomplete="new-password"></label>
  <label>新しいパスワード（確認）<input type="password" name="confirm" required minlength="8" autocomplete="new-password"></label>
  <button class="btn btn-primary">変更する</button>
</form>
