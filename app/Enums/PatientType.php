<?php

declare(strict_types=1);

namespace App\Enums;

enum PatientType: string
{
    case Mother = 'mother';
    case Child = 'child';

    public function labelTh(): string
    {
        return match ($this) {
            self::Mother => 'มารดา',
            self::Child => 'ทารก',
        };
    }
}
