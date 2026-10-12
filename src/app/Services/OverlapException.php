<?php
declare(strict_types=1);

namespace App\Services;

/** 同じ会場・時間帯に有効な予約があるときに投げる */
final class OverlapException extends \RuntimeException
{
    /** @param array<int, string> $clashes */
    public function __construct(public readonly array $clashes)
    {
        parent::__construct('venue overlap');
    }
}
