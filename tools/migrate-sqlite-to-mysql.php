<?php
declare(strict_types=1);
/*
 * SQLite の全データを MySQL へ移す（CLI専用）。config の db は mysql にしておくこと。
 *   php tools/migrate-sqlite-to-mysql.php <src のパス> <SQLite ファイル>
 * MySQL 側にテーブルを作成し、ID を保ったまま全行をコピーする。移行先にデータがあれば中止する。
 */
use App\Core\Db;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$src = rtrim($argv[1] ?? '', '/');
$sqlitePath = (string)($argv[2] ?? '');
if ($src === '' || !is_file($src . '/app/bootstrap.php') || !is_file($sqlitePath)) {
    fwrite(STDERR, "usage: php tools/migrate-sqlite-to-mysql.php <src> <sqlite file>\n");
    exit(1);
}
require $src . '/app/bootstrap.php';
if (Db::driver() !== 'mysql') {
    fwrite(STDERR, "config の db.driver が mysql ではありません\n");
    exit(1);
}

$schema = (string)file_get_contents(__DIR__ . '/../db/schema.sql');
foreach (array_filter(array_map('trim', explode(';', (string)preg_replace('/^--.*$/m', '', $schema)))) as $stmt) {
    try {
        Db::conn()->exec($stmt);
    } catch (PDOException $e) {
        if (!str_contains($e->getMessage(), 'Duplicate key name')) {
            throw $e;
        }
    }
}
preg_match_all('/CREATE TABLE IF NOT EXISTS (\w+)/', $schema, $m);
$tables = $m[1];

$lite = new PDO('sqlite:' . $sqlitePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
foreach ($tables as $t) {
    if ((int)Db::value("SELECT COUNT(*) FROM `$t`") > 0) {
        fwrite(STDERR, "移行先の $t にデータがあるため中止しました\n");
        exit(1);
    }
}

$pdo = Db::conn();
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->beginTransaction();
try {
    foreach ($tables as $t) {
        $cols = array_column(Db::rows("SHOW COLUMNS FROM `$t`"), 'Field');
        $liteCols = array_column($lite->query("PRAGMA table_info($t)")->fetchAll(), 'name');
        $use = array_values(array_intersect($cols, $liteCols));
        if ($use === []) {
            continue;
        }
        $ins = $pdo->prepare("INSERT INTO `$t` (`" . implode('`, `', $use) . '`) VALUES (' . implode(',', array_fill(0, count($use), '?')) . ')');
        $n = 0;
        foreach ($lite->query('SELECT ' . implode(', ', array_map(fn ($c) => '"' . $c . '"', $use)) . " FROM $t") as $row) {
            $ins->execute(array_values($row));
            $n++;
        }
        echo "$t: $n\n";
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
} finally {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}
echo "done\n";
