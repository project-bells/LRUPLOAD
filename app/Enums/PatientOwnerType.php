<?php

declare(strict_types=1);

namespace App\Enums;

enum PatientOwnerType: string
{
    case Mother = 'mother';
    case Child = 'child';
    case MotherAndChild = 'mother_and_child';

    public function labelTh(): string
    {
        return match ($this) {
            self::Mother => 'เอกสารของมารดา',
            self::Child => 'เอกสารของทารก',
            self::MotherAndChild => 'เอกสารเกี่ยวข้องทั้งมารดาและทารก',
        };
    }
}
