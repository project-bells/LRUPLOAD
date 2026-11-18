<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RevisionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRevision extends Model
{
    protected $fillable = [
        'document_id',
        'revision_number',
        'stored_file_id',
        'previous_revision_id',
        'status',
        'is_current_flag',
        'change_reason',
        'notes',
        'created_by',
        'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'status' => RevisionStatus::class,
            'is_current_flag' => 'integer',
            'superseded_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function storedFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class);
    }

    public function previousRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'previous_revision_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCurrent(): bool
    {
        return $this->status === RevisionStatus::Current;
    }
}
