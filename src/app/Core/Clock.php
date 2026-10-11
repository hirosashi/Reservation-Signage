<?php
declare(strict_types=1);

namespace App\Core;

/** 業務コードの時刻は全てこのクラス経由（Asia/Tokyo） */
final class Clock
{
    private static ?\DateTimeImmutable $fixed = null;

    public static function init(): void
    {
        date_default_timezone_set('Asia/Tokyo');
    }

    public static function fix(?\DateTimeImmutable $dt): void
    {
        self::$fixed = $dt;
    }

    public static function now(): \DateTimeImmutable
    {
        return self::$fixed ?? new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tokyo'));
    }

    public static function nowStr(): string
    {
        return self::now()->format('Y-m-d H:i:s');
    }

    public static function today(): string
    {
        return self::now()->format('Y-m-d');
    }

    public static function parse(string $s): ?\DateTimeImmutable
    {
        $s = trim($s);
        if ($s === '') {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $s, new \DateTimeZone('Asia/Tokyo'));
        if ($dt === false) {
            $dt = \DateTimeImmutable::createFromFormat('!Y/m/d', $s, new \DateTimeZone('Asia/Tokyo'));
        }
        return $dt === false ? null : $dt;
    }

    public static function dt(?string $s): string
    {
        if ($s === null || $s === '') {
            return '';
        }
        $dt = new \DateTimeImmutable($s, new \DateTimeZone('Asia/Tokyo'));
        return $dt->format('Y/m/d H:i');
    }

    public static function d(?string $s): string
    {
        if ($s === null || $s === '') {
            return '';
        }
        $dt = new \DateTimeImmutable($s, new \DateTimeZone('Asia/Tokyo'));
        return $dt->format('Y/m/d');
    }

    public static function jp(?string $s): string
    {
        if ($s === null || $s === '') {
            return '';
        }
        $dt = new \DateTimeImmutable($s, new \DateTimeZone('Asia/Tokyo'));
        return $dt->format('Y年n月j日');
    }
}
