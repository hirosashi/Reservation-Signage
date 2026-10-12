<?php
declare(strict_types=1);
/*
 * 初期セットアップ（CLI専用）: テーブル作成 → 初期マスタ・管理者を投入（空のときだけ）
 *   php tools/install.php <src のパス> <admin の初期パスワード>
 * 会場・フラグ・店舗の初期データは db/seed.json（無ければ最小限のサンプル）
 */
use App\Core\Clock;
use App\Core\Db;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$src = rtrim($argv[1] ?? '', '/');
$pw = (string)($argv[2] ?? '');
if ($src === '' || !is_file($src . '/app/bootstrap.php') || strlen($pw) < 8) {
    fwrite(STDERR, "usage: php tools/install.php <src> <admin password (8+ chars)>\n");
    exit(1);
}
require $src . '/app/bootstrap.php';

$sql = (string)file_get_contents(__DIR__ . '/../db/schema.sql');
if (Db::driver() === 'sqlite') {
    $dir = dirname((string)App\Core\App::config()['db']['path']);
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    $sql = str_replace('INT AUTO_INCREMENT PRIMARY KEY', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    $sql = preg_replace("/\\s*COMMENT\\s*'[^']*'/u", '', $sql);
    $sql = preg_replace('/\\)\\s*ENGINE=[^;]*;/', ');', (string)$sql);
    $sql = str_replace('CREATE INDEX ', 'CREATE INDEX IF NOT EXISTS ', (string)$sql);
}
foreach (array_filter(array_map('trim', explode(';', (string)preg_replace('/^--.*$/m', '', (string)$sql)))) as $stmt) {
    try {
        Db::conn()->exec($stmt);
    } catch (PDOException $e) {
        if (!str_contains($e->getMessage(), 'Duplicate key name')) {
            throw $e;
        }
    }
}
echo "schema ok\n";

$now = Clock::nowStr();
if ((int)Db::value('SELECT COUNT(*) FROM users') === 0) {
    Db::exec('INSERT INTO users (login_id, name, password_hash, role, created_at, updated_at) VALUES (?,?,?,?,?,?)', ['admin', 'システム管理者', password_hash($pw, PASSWORD_DEFAULT), 'admin', $now, $now]);
    echo "admin created\n";
}

$seedFile = __DIR__ . '/../db/seed.json';
$seed = is_file($seedFile) ? json_decode((string)file_get_contents($seedFile), true) : null;
if (!is_array($seed)) {
    $seed = ['shops' => [['name' => '宴会場', 'slug' => '', 'venues' => [['name' => '会場A', 'building' => '本館1F', 'report_name' => '会場A']]]], 'flags' => [], 'options' => []];
}

if ((int)Db::value('SELECT COUNT(*) FROM shops') === 0) {
    foreach ($seed['shops'] as $i => $s) {
        Db::exec('INSERT INTO shops (name, slug, sort_no) VALUES (?,?,?)', [$s['name'], $s['slug'] ?? null, ($i + 1) * 10]);
        $shopId = Db::lastId();
        foreach ($s['venues'] ?? [] as $j => $v) {
            Db::exec(
                'INSERT INTO venues (shop_id, name, signage_name, building, report_name, cap_min, cap_max, sort_no) VALUES (?,?,?,?,?,?,?,?)',
                [$shopId, $v['name'], $v['signage_name'] ?? null, $v['building'] ?? null, $v['report_name'] ?? null, $v['cap_min'] ?? null, $v['cap_max'] ?? null, ($j + 1) * 10]
            );
        }
    }
    echo "shops/venues ok\n";
}
if ((int)Db::value('SELECT COUNT(*) FROM flags') === 0) {
    foreach ($seed['flags'] ?? [] as $i => $f) {
        Db::exec('INSERT INTO flags (name, color, hide_on_signage, sort_no) VALUES (?,?,?,?)', [$f['name'], $f['color'] ?? null, (int)($f['hide_on_signage'] ?? 0), ($i + 1) * 10]);
    }
    echo "flags ok\n";
}
if ((int)Db::value('SELECT COUNT(*) FROM options') === 0) {
    $opts = $seed['options'] ?? [];
    $opts += [
        'purpose' => ['会議', '宴会', '婚礼', '法事', '会食', '誕生日', '接待', 'その他'],
        'source' => ['電話', 'メール', '来館', 'Web', '旅行会社', 'その他'],
        'trigger' => ['リピーター', '紹介', '検索', 'チラシ・広告', 'その他'],
        'customer_tag' => ['VIP', '法人', 'リピーター', '要注意'],
    ];
    foreach ($opts as $kind => $names) {
        foreach ($names as $i => $n) {
            Db::exec('INSERT INTO options (kind, name, sort_no) VALUES (?,?,?)', [$kind, $n, ($i + 1) * 10]);
        }
    }
    echo "options ok\n";
}
if ((int)Db::value('SELECT COUNT(*) FROM settings') === 0) {
    foreach (['signage_title' => '本日のご案内', 'signage_refresh_sec' => '60', 'hotel_name' => 'HOTEL NEW ITAYA'] as $k => $v) {
        Db::exec('INSERT INTO settings (name, value) VALUES (?,?)', [$k, $v]);
    }
}
echo "done\n";
