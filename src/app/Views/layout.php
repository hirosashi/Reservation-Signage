<?php
use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;

$u = Auth::user();
$path = App::currentPath();
$date = $date ?? \App\Core\Clock::today();
$flash = Session::pullFlash();
$nav = [
    ['/', 'ホーム'], ['/chart', 'チャート'], ['/list', 'リスト'], ['/calendar', 'カレンダー'],
    ['/customers', '顧客検索'], ['/screens', 'サイネージ'],
];
$dt = new DateTimeImmutable($date);
$wd = ['日', '月', '火', '水', '木', '金', '土'][(int)$dt->format('w')];
$q = $_GET;
unset($q['edit'], $q['new'], $q['time'], $q['venue']);
$dateUrl = function (string $d) use ($path, $q): string {
    $q['date'] = $d;
    unset($q['ym']);
    return App::url($path) . '?' . http_build_query($q);
};
$panelUrl = App::url($path) . '?' . http_build_query($q + ['new' => '1']);
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= View::e($title ?? '') ?> | <?= View::e(App::config()['site']['name'] ?? '予約管理') ?></title>
<link rel="stylesheet" href="<?= App::url('assets/css/app.css') ?>?v=1">
</head>
<body class="<?= !empty($panel) ? 'has-panel' : '' ?>">
<header class="topbar">
  <a class="brand" href="<?= App::url('/') ?>"><?= View::e(App::config()['site']['hotel'] ?? '') ?></a>
  <?php if ($u): ?>
  <div class="datebar">
    <a class="btn btn-sm" href="<?= View::e($dateUrl(\App\Core\Clock::today())) ?>">本日</a>
    <a class="btn btn-sm" href="<?= View::e($dateUrl(\App\Core\Clock::now()->modify('+1 day')->format('Y-m-d'))) ?>">明日</a>
    <a class="btn btn-sm" href="<?= View::e($dateUrl($dt->modify('-1 day')->format('Y-m-d'))) ?>" aria-label="前日">◀</a>
    <form method="get" action="<?= App::url($path) ?>" class="datepick">
      <?php foreach ($q as $k => $v): if ($k === 'date' || is_array($v)) continue; ?><input type="hidden" name="<?= View::e($k) ?>" value="<?= View::e($v) ?>"><?php endforeach; ?>
      <input type="date" name="date" value="<?= View::e($date) ?>" onchange="this.form.submit()">
    </form>
    <span class="date-label <?= $wd === '日' ? 'sun' : ($wd === '土' ? 'sat' : '') ?>"><?= $dt->format('n月j日') ?>（<?= $wd ?>）</span>
    <a class="btn btn-sm" href="<?= View::e($dateUrl($dt->modify('+1 day')->format('Y-m-d'))) ?>" aria-label="翌日">▶</a>
  </div>
  <div class="userbox">
    <span><?= View::e($u['name']) ?></span>
    <a href="<?= App::url('/password') ?>">パスワード</a>
    <form method="post" action="<?= App::url('/logout') ?>"><?= Csrf::field() ?><button class="linkbtn">ログアウト</button></form>
  </div>
  <?php endif; ?>
</header>
<div class="shell">
  <nav class="sidenav">
    <a class="nav-new" href="<?= View::e($panelUrl) ?>">＋ 予約フォーム</a>
    <?php foreach ($nav as [$href, $label]): $on = $href === '/' ? $path === '/' : str_starts_with($path, $href); ?>
      <a class="<?= $on ? 'on' : '' ?>" href="<?= App::url($href) ?>?date=<?= View::e($date) ?>"><?= View::e($label) ?></a>
    <?php endforeach; ?>
    <a href="<?= App::url('/report') ?>?date=<?= View::e($date) ?>" target="_blank" rel="noopener">帳票出力</a>
    <?php if (Auth::isAdmin()): ?><a class="<?= str_starts_with($path, '/settings') ? 'on' : '' ?>" href="<?= App::url('/settings') ?>">設定</a><?php endif; ?>
  </nav>
  <main class="main">
    <?php foreach ($flash as $f): ?><div class="flash flash-<?= View::e($f['kind']) ?>"><?= View::e($f['message']) ?></div><?php endforeach; ?>
    <?= $content ?>
  </main>
  <?php if (!empty($panel)): ?>
  <aside class="panel"><?= View::partial('reservations/panel', ['panel' => $panel, 'returnUrl' => $returnUrl ?? '/']) ?></aside>
  <?php endif; ?>
</div>
<script>window.APP_BASE = <?= json_encode(App::basePath()) ?>;</script>
<script src="<?= App::url('assets/js/app.js') ?>?v=1"></script>
</body>
</html>
