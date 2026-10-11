<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/** MySQL（本番想定）と SQLite（準備サイト・ローカル）の両方で動く SQL だけを使う */
final class Db
{
    private static ?PDO $pdo = null;

    public static function driver(): string
    {
        return (string)(App::config()['db']['driver'] ?? 'mysql');
    }

    public static function conn(): PDO
    {
        if (self::$pdo === null) {
            $c = App::config()['db'];
            $opts = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];
            if (self::driver() === 'sqlite') {
                self::$pdo = new PDO('sqlite:' . $c['path'], null, null, $opts);
                self::$pdo->exec('PRAGMA foreign_keys = ON');
                self::$pdo->exec('PRAGMA busy_timeout = 3000');
            } else {
                $opts[PDO::ATTR_EMULATE_PREPARES] = false;
                $dsn = "mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4";
                self::$pdo = new PDO($dsn, $c['user'], $c['pass'], $opts);
                self::$pdo->exec("SET time_zone = '+09:00'");
            }
        }
        return self::$pdo;
    }

    /** @param array<int|string, mixed> $params @return array<int, array<string, mixed>> */
    public static function rows(string $sql, array $params = []): array
    {
        $st = self::conn()->prepare($sql);
        $st->execute($params);
        /** @var array<int, array<string, mixed>> $r */
        $r = $st->fetchAll();
        return $r;
    }

    /** @param array<int|string, mixed> $params @return array<string, mixed>|null */
    public static function row(string $sql, array $params = []): ?array
    {
        $st = self::conn()->prepare($sql);
        $st->execute($params);
        $r = $st->fetch();
        return $r === false ? null : $r;
    }

    /** @param array<int|string, mixed> $params */
    public static function value(string $sql, array $params = []): mixed
    {
        $st = self::conn()->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === false ? null : $v;
    }

    /** @param array<int|string, mixed> $params */
    public static function exec(string $sql, array $params = []): int
    {
        $st = self::conn()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    public static function lastId(): int
    {
        return (int)self::conn()->lastInsertId();
    }

    /** @param callable():T $fn @return T @template T */
    public static function tx(callable $fn): mixed
    {
        $pdo = self::conn();
        $pdo->beginTransaction();
        try {
            $r = $fn();
            $pdo->commit();
            return $r;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
