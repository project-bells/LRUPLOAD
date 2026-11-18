<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoredFile extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'original_name',
        'stored_name',
        'storage_disk',
        'storage_path',
        'mime_type',
        'extension',
        'file_size',
        'sha256_hash',
        'scan_status',
        'uploaded_by',
        'created_at',
    ];

    protected $hidden = [
        'storage_path',
        'stored_name',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isPdf(): bool
    {
        return strtolower($this->extension) === 'pdf' || $this->mime_type === 'application/pdf';
    }

    public function isImage(): bool
    {
        return in_array(strtolower($this->extension), ['jpg', 'jpeg', 'png'], true)
            || str_starts_with($this->mime_type, 'image/');
    }

    public function formattedSize(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }
}
