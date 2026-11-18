<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RevisionStatus;
use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class DocumentRevisionService
{
    public function __construct(
        private readonly FileStorageService $fileStorage,
        private readonly AuditLogService $auditLog,
    ) {
    }

    /**
     * Add a new immutable Revision without overwriting or deleting any existing file.
     * Uses DB transaction + row lock (lockForUpdate) + DB unique constraint on is_current_flag
     * to guarantee at most ONE current revision per document under concurrent requests.
     *
     * @param array<string, mixed> $stagedFile
     */
    public function addRevisionFromStagedUpload(
        Document $document,
        array $stagedFile,
        string $changeReason,
        ?string $notes,
        User $actor,
        ?Request $request = null,
    ): DocumentRevision {
        $promotedDisk = null;
        $promotedPath = null;

        try {
            $newRevision = DB::transaction(function () use (
                $document,
                $stagedFile,
                $changeReason,
                $notes,
                $actor,
                $request,
                &$promotedDisk,
                &$promotedPath
            ): DocumentRevision {
                /** @var Document $lockedDoc */
                $lockedDoc = Document::query()
                    ->whereKey($document->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedDoc->trashed()) {
                    throw new RuntimeException('ไม่สามารถเพิ่ม Revision ให้เอกสารที่อยู่ในถังเอกสารได้');
                }

                /** @var DocumentRevision|null $currentRevision */
                $currentRevision = DocumentRevision::query()
                    ->where('document_id', $lockedDoc->id)
                    ->where('status', RevisionStatus::Current->value)
                    ->lockForUpdate()
                    ->first();

                $maxRevNumber = (int) DocumentRevision::query()
                    ->where('document_id', $lockedDoc->id)
                    ->max('revision_number');

                $nextRevNumber = $maxRevNumber + 1;

                // Supersede previous current revision BEFORE inserting the new current revision
                if ($currentRevision !== null) {
                    $currentRevision->status = RevisionStatus::Superseded;
                    $currentRevision->is_current_flag = null;
                    $currentRevision->superseded_at = now();
                    $currentRevision->save();
                }

                // Promote new file into private storage (never overwrites old file)
                $storedFile = $this->fileStorage->promoteStagedFile($stagedFile, $actor);
                $promotedDisk = $storedFile->storage_disk;
                $promotedPath = $storedFile->storage_path;

                $revision = DocumentRevision::query()->create([
                    'document_id' => $lockedDoc->id,
                    'revision_number' => $nextRevNumber,
                    'stored_file_id' => $storedFile->id,
                    'previous_revision_id' => $currentRevision?->id,
                    'status' => RevisionStatus::Current->value,
                    'is_current_flag' => 1,
                    'change_reason' => $changeReason,
                    'notes' => $notes,
                    'created_by' => $actor->id,
                    'superseded_at' => null,
                ]);

                $primaryPatient = $lockedDoc->primaryPatient();

                $this->auditLog->record(
                    action: 'create_revision',
                    user: $actor,
                    outcome: 'success',
                    documentId: $lockedDoc->id,
                    revisionId: $revision->id,
                    hnHash: $primaryPatient?->hn_hash,
                    beforeData: $currentRevision !== null ? [
                        'previous_revision_id' => $currentRevision->id,
                        'previous_revision_number' => $currentRevision->revision_number,
                    ] : null,
                    afterData: [
                        'revision_number' => $revision->revision_number,
                        'change_reason' => $changeReason,
                        'sha256_hash' => $storedFile->sha256_hash,
                    ],
                    request: $request,
                    departmentId: $lockedDoc->department_id,
                );

                return $revision;
            });

            $this->fileStorage->deleteStagedFile((string) ($stagedFile['tmp_path'] ?? ''));

            return $newRevision;
        } catch (Throwable $e) {
            if ($promotedDisk !== null && $promotedPath !== null) {
                $this->fileStorage->deletePrivateFile($promotedDisk, $promotedPath);
            }

            throw $e;
        }
    }
}
