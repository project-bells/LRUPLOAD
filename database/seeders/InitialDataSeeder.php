<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BrandingSetting;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class InitialDataSeeder extends Seeder
{
    public function run(): void
    {
        Department::query()->updateOrCreate(
            ['code' => 'LR'],
            [
                'name_th' => 'แผนกห้องคลอด',
                'name_en' => 'Labor Room Department',
                'is_active' => true,
            ]
        );

        $documentTypes = [
            ['code' => 'CONSENT', 'name_th' => 'ใบยินยอม', 'name_en' => 'Consent Form', 'sort_order' => 1],
            ['code' => 'DELIVERY', 'name_th' => 'เอกสารการคลอด', 'name_en' => 'Delivery Record', 'sort_order' => 2],
            ['code' => 'MATERNAL', 'name_th' => 'เอกสารมารดา', 'name_en' => 'Maternal Record', 'sort_order' => 3],
            ['code' => 'NEWBORN', 'name_th' => 'เอกสารทารกแรกเกิด', 'name_en' => 'Newborn Record', 'sort_order' => 4],
            ['code' => 'LAB_RESULT', 'name_th' => 'ผลตรวจ', 'name_en' => 'Laboratory & Diagnostic Result', 'sort_order' => 5],
            ['code' => 'TREATMENT', 'name_th' => 'เอกสารการรักษา', 'name_en' => 'Treatment Record', 'sort_order' => 6],
            ['code' => 'REFERRAL', 'name_th' => 'เอกสารส่งต่อ', 'name_en' => 'Referral Document', 'sort_order' => 7],
            ['code' => 'OTHER', 'name_th' => 'เอกสารอื่น ๆ', 'name_en' => 'Other Documents', 'sort_order' => 8],
        ];

        foreach ($documentTypes as $type) {
            DocumentType::query()->updateOrCreate(
                ['code' => $type['code']],
                [
                    'name_th' => $type['name_th'],
                    'name_en' => $type['name_en'],
                    'sort_order' => $type['sort_order'],
                    'is_active' => true,
                ]
            );
        }

        if (BrandingSetting::query()->count() === 0) {
            BrandingSetting::query()->create([
                'hospital_name_th' => 'โรงพยาบาลเอกชล',
                'hospital_name_en' => 'Aikchol Hospital',
                'department_name_th' => 'แผนกห้องคลอด',
                'department_name_en' => 'Labor Room Department',
                'logo_path' => null,
                'primary_color' => '#004F85',
                'secondary_color' => '#3EC0C8',
                'accent_color' => '#FBB435',
            ]);
        }

        $defaultSettings = [
            ['group_name' => 'security', 'key' => 'permanent_delete_enabled', 'value_plain' => '1', 'is_secret' => false],
            ['group_name' => 'security', 'key' => 'soft_delete_retention_days', 'value_plain' => '0', 'is_secret' => false],
            ['group_name' => 'security', 'key' => 'max_file_size_kb', 'value_plain' => '20480', 'is_secret' => false],
        ];

        foreach ($defaultSettings as $setting) {
            SystemSetting::query()->firstOrCreate(
                ['key' => $setting['key']],
                [
                    'group_name' => $setting['group_name'],
                    'value_plain' => $setting['value_plain'],
                    'is_secret' => $setting['is_secret'],
                ]
            );
        }
    }
}
