<?php

declare(strict_types=1);

namespace App\Enums;

enum ConfidentialityLevel: string
{
    case Normal = 'normal';
    case Confidential = 'confidential';
    case StrictlyConfidential = 'strictly_confidential';

    public function labelTh(): string
    {
        return match ($this) {
            self::Normal => 'ทั่วไป (ภายในหน่วยงาน)',
            self::Confidential => 'ลับ (ข้อมูลผู้ป่วย)',
            self::StrictlyConfidential => 'ลับมาก (จำกัดสิทธิ์เฉพาะ)',
        };
    }
}
