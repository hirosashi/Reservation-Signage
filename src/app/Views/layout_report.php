<?php use App\Core\App; use App\Core\View; ?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex,nofollow">
<title><?= View::e($title ?? '') ?></title>
<link rel="stylesheet" href="<?= App::url('assets/css/report.css') ?>?v=1">
</head>
<body><?= $content ?></body>
</html>
