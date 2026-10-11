<?php use App\Core\App; use App\Core\View; ?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= View::e($title ?? '') ?> | <?= View::e(App::config()['site']['name'] ?? '予約管理') ?></title>
<link rel="stylesheet" href="<?= App::url('assets/css/app.css') ?>?v=1">
</head>
<body class="plain"><?= $content ?></body>
</html>
