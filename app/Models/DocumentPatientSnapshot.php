<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class DocumentPatientSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'patient_id',
        'snapshot_data_encrypted',
        'source_fetched_at',
        'created_at',
    ];

    protected $hidden = [
        'snapshot_data_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_data_encrypted' => 'encrypted:array',
            'source_fetched_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new LogicException('DocumentPatientSnapshot records are immutable and cannot be modified.');
        });
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSnapshotDataAttribute(): array
    {
        return is_array($this->snapshot_data_encrypted) ? $this->snapshot_data_encrypted : [];
    }
}
