<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandingSetting extends Model
{
    protected $fillable = [
        'hospital_name_th',
        'hospital_name_en',
        'department_name_th',
        'department_name_en',
        'logo_path',
        'primary_color',
        'secondary_color',
        'accent_color',
        'updated_by',
    ];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function current(): self
    {
        try {
            $setting = static::query()->first();
            if ($setting !== null) {
                return $setting;
            }
        } catch (\Throwable) {
            // Fallback before migrations have run
        }

        return new self([
            'hospital_name_th' => config('branding.hospital_name_th', 'โรงพยาบาลเอกชล'),
            'hospital_name_en' => config('branding.hospital_name_en', 'Aikchol Hospital'),
            'department_name_th' => config('branding.department_name_th', 'แผนกห้องคลอด'),
            'department_name_en' => config('branding.department_name_en', 'Labor Room Department'),
            'logo_path' => null,
            'primary_color' => config('branding.colors.primary', '#004F85'),
            'secondary_color' => config('branding.colors.secondary', '#3EC0C8'),
            'accent_color' => config('branding.colors.accent', '#FBB435'),
        ]);
    }
}
