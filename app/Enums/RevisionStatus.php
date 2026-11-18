<?php

declare(strict_types=1);

namespace App\Enums;

enum RevisionStatus: string
{
    case Current = 'current';
    case Superseded = 'superseded';
    case Withdrawn = 'withdrawn';

    public function labelTh(): string
    {
        return match ($this) {
            self::Current => 'ฉบับปัจจุบัน',
            self::Superseded => 'ถูกแทนที่แล้ว',
            self::Withdrawn => 'ถูกเพิกถอน',
        };
    }
}
