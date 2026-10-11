<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function e(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** 数値表示。null/空は空文字、それ以外は桁区切り */
    public static function num(mixed $v, int $dec = 0): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        return number_format((float)$v, $dec, '.', ',');
    }

    /** 小数は必要な桁だけ表示（0.07 → 0.07、3.0 → 3） */
    public static function dec(mixed $v, int $maxDec = 4): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        $s = number_format((float)$v, $maxDec, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        return $s === '' || $s === '-0' ? '0' : $s;
    }

    public static function yen(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        return '¥' . number_format((float)$v, 0, '.', ',');
    }

    public static function pct(mixed $v, int $dec = 1): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        return self::dec((float)$v * 100, $dec) . '%';
    }

    public static function dt(?string $s): string
    {
        return Clock::dt($s);
    }

    public static function d(?string $s): string
    {
        return Clock::d($s);
    }

    /** @param array<string, mixed> $vars */
    public static function render(string $template, array $vars = [], string $layout = 'layout'): void
    {
        $content = self::partial($template, $vars);
        $vars['content'] = $content;
        echo self::partial($layout, $vars);
    }

    /** @param array<string, mixed> $vars */
    public static function partial(string $template, array $vars = []): string
    {
        $file = dirname(__DIR__) . '/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('view not found: ' . $template);
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } finally {
            $out = (string)ob_get_clean();
        }
        return $out;
    }

    /** @param array<int|string, string> $options */
    public static function select(string $name, array $options, mixed $selected, string $attrs = '', string $empty = ''): string
    {
        $h = '<select name="' . self::e($name) . '" ' . $attrs . '>';
        if ($empty !== '') {
            $h .= '<option value="">' . self::e($empty) . '</option>';
        }
        foreach ($options as $k => $label) {
            $sel = ((string)$k === (string)$selected) ? ' selected' : '';
            $h .= '<option value="' . self::e($k) . '"' . $sel . '>' . self::e($label) . '</option>';
        }
        return $h . '</select>';
    }
}
