<?php use App\Core\App; use App\Core\Csrf; use App\Core\View; ?>
<div class="login-box">
  <h1><?= View::e(App::config()['site']['name'] ?? '予約管理') ?></h1>
  <?php if ($error !== ''): ?><div class="flash flash-warn"><?= View::e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= App::url('/login') ?>">
    <?= Csrf::field() ?>
    <label>ログインID<input name="login_id" value="<?= View::e($login_id ?? '') ?>" autocomplete="username" required autofocus></label>
    <label>パスワード<input type="password" name="password" autocomplete="current-password" required></label>
    <button class="btn btn-primary btn-block">ログイン</button>
  </form>
</div>
