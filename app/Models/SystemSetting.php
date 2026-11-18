<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    protected $fillable = [
        'group_name',
        'key',
        'value_encrypted',
        'value_plain',
        'is_secret',
        'updated_by',
    ];

    protected $hidden = [
        'value_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'value_encrypted' => 'encrypted',
            'is_secret' => 'boolean',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function resolvedValue(): ?string
    {
        return $this->is_secret ? $this->value_encrypted : $this->value_plain;
    }
}
