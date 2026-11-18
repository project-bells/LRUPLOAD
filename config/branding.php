<?php

return [
    'hospital_name_th' => env('BRANDING_HOSPITAL_NAME_TH', 'โรงพยาบาลเอกชล'),
    'hospital_name_en' => env('BRANDING_HOSPITAL_NAME_EN', 'Aikchol Hospital'),
    'system_name_th' => env('BRANDING_SYSTEM_NAME_TH', 'ระบบจัดเก็บเอกสารผู้ป่วย แผนกห้องคลอด โรงพยาบาลเอกชล'),
    'system_name_en' => env('BRANDING_SYSTEM_NAME_EN', 'Aikchol Hospital Labor Room Document Management System'),
    'department_name_th' => env('BRANDING_DEPARTMENT_NAME_TH', 'แผนกห้องคลอด'),
    'department_name_en' => env('BRANDING_DEPARTMENT_NAME_EN', 'Labor Room Department'),

    // Default logo asset path under public/ (unmodified official Aikchol Hospital logo)
    'default_logo_path' => 'images/aikchol-logo.png',

    // Brand colors extracted directly from the Aikchol Hospital official logo
    'colors' => [
        'primary' => '#004F85',
        'primary_dark' => '#003B63',
        'primary_soft' => '#EBF4FA',
        'secondary' => '#3EC0C8',
        'secondary_dark' => '#1F8E96',
        'secondary_soft' => '#EAF8F9',
        'accent' => '#FBB435',
        'accent_dark' => '#9A6000',
        'accent_soft' => '#FFF8E6',
    ],
];
