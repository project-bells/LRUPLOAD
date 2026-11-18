<?php

declare(strict_types=1);

namespace App\Enums;

enum DeleteRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function labelTh(): string
    {
        return match ($this) {
            self::Pending => 'รออนุมัติ',
            self::Approved => 'อนุมัติแล้ว',
            self::Rejected => 'ปฏิเสธ',
            self::Cancelled => 'ยกเลิก',
        };
    }
}
