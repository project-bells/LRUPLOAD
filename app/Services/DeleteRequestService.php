<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeleteRequestStatus;
use App\Models\DeleteRequest;
use App\Models\Document;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeleteRequestService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly FileStorageService $fileStorage,
    ) {
    }

    public function createRequest(Document $document, User $requester, string $reason, ?Request $request = null): DeleteRequest
    {
        $existingPending = DeleteRequest::query()
            ->where('document_id', $document->id)
            ->where('status', DeleteRequestStatus::Pending->value)
            ->first();

        if ($existingPending !== null) {
            throw new RuntimeException('เอกสารนี้มีคำขอลบที่กำลังรออนุมัติอยู่แล้ว');
        }

        $deleteRequest = DeleteRequest::query()->create([
            'document_id' => $document->id,
            'requested_by' => $requester->id,
            'request_reason' => $reason,
            'status' => DeleteRequestStatus::Pending->value,
        ]);

        $primaryPatient = $document->primaryPatient();

        $this->auditLog->record(
            action: 'request_delete',
            user: $requester,
            outcome: 'success',
            documentId: $document->id,
            hnHash: $primaryPatient?->hn_hash,
            afterData: [
                'delete_request_id' => $deleteRequest->id,
                'request_reason' => $reason,
            ],
            request: $request,
            departmentId: $document->department_id,
        );

        return $deleteRequest;
    }

    /**
     * Approve a delete request and perform a Soft Delete on the document.
     * NEVER removes physical files, revisions, patient snapshots, or audit logs.
     */
    public function approve(DeleteRequest $deleteRequest, User $reviewer, ?string $reviewNote = null, ?Request $request = null): Document
    {
        return DB::transaction(function () use ($deleteRequest, $reviewer, $reviewNote, $request): Document {
            /** @var DeleteRequest $lockedReq */
            $lockedReq = DeleteRequest::query()->whereKey($deleteRequest->id)->lockForUpdate()->firstOrFail();

            if ($lockedReq->status !== DeleteRequestStatus::Pending) {
                throw new RuntimeException('คำขอลบนี้ได้รับการดำเนินการไปแล้ว');
            }

            /** @var Document $document */
            $document = Document::withTrashed()->whereKey($lockedReq->document_id)->lockForUpdate()->firstOrFail();

            $lockedReq->status = DeleteRequestStatus::Approved;
            $lockedReq->reviewed_by = $reviewer->id;
            $lockedReq->review_note = $reviewNote;
            $lockedReq->reviewed_at = now();
            $lockedReq->save();

            $document->deleted_by = $reviewer->id;
            $document->delete_reason = $lockedReq->request_reason;
            $document->save();
            $document->delete(); // SoftDelete ONLY

            $primaryPatient = $document->primaryPatient();

            $this->auditLog->record(
                action: 'approve_delete',
                user: $reviewer,
                outcome: 'success',
                documentId: $document->id,
                hnHash: $primaryPatient?->hn_hash,
                afterData: [
                    'delete_request_id' => $lockedReq->id,
                    'requested_by' => $lockedReq->requested_by,
                    'review_note' => $reviewNote,
                ],
                request: $request,
                departmentId: $document->department_id,
            );

            $this->auditLog->record(
                action: 'soft_delete',
                user: $reviewer,
                outcome: 'success',
                documentId: $document->id,
                hnHash: $primaryPatient?->hn_hash,
                afterData: [
                    'delete_reason' => $lockedReq->request_reason,
                    'via_delete_request_id' => $lockedReq->id,
                ],
                request: $request,
                departmentId: $document->department_id,
            );

            return $document;
        });
    }

    public function reject(DeleteRequest $deleteRequest, User $reviewer, string $reviewNote, ?Request $request = null): DeleteRequest
    {
        return DB::transaction(function () use ($deleteRequest, $reviewer, $reviewNote, $request): DeleteRequest {
            /** @var DeleteRequest $lockedReq */
            $lockedReq = DeleteRequest::query()->whereKey($deleteRequest->id)->lockForUpdate()->firstOrFail();

            if ($lockedReq->status !== DeleteRequestStatus::Pending) {
                throw new RuntimeException('คำขอลบนี้ได้รับการดำเนินการไปแล้ว');
            }

            $lockedReq->status = DeleteRequestStatus::Rejected;
            $lockedReq->reviewed_by = $reviewer->id;
            $lockedReq->review_note = $reviewNote;
            $lockedReq->reviewed_at = now();
            $lockedReq->save();

            $document = Document::withTrashed()->find($lockedReq->document_id);
            $primaryPatient = $document?->primaryPatient();

            $this->auditLog->record(
                action: 'reject_delete',
                user: $reviewer,
                outcome: 'success',
                documentId: $lockedReq->document_id,
                hnHash: $primaryPatient?->hn_hash,
                afterData: [
                    'delete_request_id' => $lockedReq->id,
                    'review_note' => $reviewNote,
                ],
                request: $request,
                departmentId: $document?->department_id,
            );

            return $lockedReq;
        });
    }

    public function directSoftDelete(Document $document, User $actor, string $reason, ?Request $request = null): Document
    {
        return DB::transaction(function () use ($document, $actor, $reason, $request): Document {
            /** @var Document $lockedDoc */
            $lockedDoc = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

            $lockedDoc->deleted_by = $actor->id;
            $lockedDoc->delete_reason = $reason;
            $lockedDoc->save();
            $lockedDoc->delete();

            // Cancel any open pending delete requests on this document
            DeleteRequest::query()
                ->where('document_id', $lockedDoc->id)
                ->where('status', DeleteRequestStatus::Pending->value)
                ->update([
                    'status' => DeleteRequestStatus::Approved->value,
                    'reviewed_by' => $actor->id,
                    'review_note' => 'ดำเนินการ Soft Delete โดยหัวหน้า/ผู้ดูแลระบบ',
                    'reviewed_at' => now(),
                ]);

            $primaryPatient = $lockedDoc->primaryPatient();

            $this->auditLog->record(
                action: 'soft_delete',
                user: $actor,
                outcome: 'success',
                documentId: $lockedDoc->id,
                hnHash: $primaryPatient?->hn_hash,
                afterData: ['delete_reason' => $reason],
                request: $request,
                departmentId: $lockedDoc->department_id,
            );

            return $lockedDoc;
        });
    }

    public function restore(Document $document, User $actor, ?string $note = null, ?Request $request = null): Document
    {
        return DB::transaction(function () use ($document, $actor, $note, $request): Document {
            /** @var Document $lockedDoc */
            $lockedDoc = Document::onlyTrashed()->whereKey($document->id)->lockForUpdate()->firstOrFail();

            $lockedDoc->restore();
            $lockedDoc->deleted_by = null;
            $lockedDoc->delete_reason = null;
            $lockedDoc->save();

            $primaryPatient = $lockedDoc->primaryPatient();

            $this->auditLog->record(
                action: 'restore_document',
                user: $actor,
                outcome: 'success',
                documentId: $lockedDoc->id,
                hnHash: $primaryPatient?->hn_hash,
                afterData: ['restore_note' => $note],
                request: $request,
                departmentId: $lockedDoc->department_id,
            );

            return $lockedDoc;
        });
    }

    public function isPermanentDeleteEnabled(): bool
    {
        $setting = SystemSetting::query()->where('key', 'permanent_delete_enabled')->first();
        if ($setting !== null) {
            return $setting->value_plain === '1';
        }

        return (bool) config('documents.permanent_delete_enabled', false);
    }

    /**
     * Get configured Soft Deleted retention days (0 = disabled / manual only).
     */
    public function getSoftDeleteRetentionDays(): int
    {
        $setting = SystemSetting::query()->where('key', 'soft_delete_retention_days')->first();
        if ($setting !== null && is_numeric($setting->value_plain)) {
            return max(0, (int) $setting->value_plain);
        }

        return 0;
    }

    /**
     * Automatically permanently delete soft-deleted documents older than configured retention days.
     * Always preserves audit_logs and writes a system permanent_delete audit entry.
     */
    public function purgeExpiredSoftDeletedDocuments(): int
    {
        $days = $this->getSoftDeleteRetentionDays();
        if ($days <= 0) {
            return 0;
        }

        $cutoff = now()->subDays($days);
        $expiredDocs = Document::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->get();

        $purged = 0;
        foreach ($expiredDocs as $doc) {
            $this->executePermanentDelete(
                document: $doc,
                actor: null,
                reason: "ลบถาวรอัตโนมัติตามนโยบายเก็บเอกสารในถังเอกสาร ({$days} วัน)",
                source: 'scheduled_retention_purge',
                retentionDays: $days,
            );
            $purged++;
        }

        return $purged;
    }

    /**
     * Policy-gated Permanent Delete (Supervisor & Admin, requires password re-auth at controller level).
     * NEVER deletes related audit_logs!
     */
    public function permanentDelete(Document $document, User $actor, string $reason, ?Request $request = null): void
    {
        if (! $this->isPermanentDeleteEnabled()) {
            throw new RuntimeException('นโยบายระบบปิดใช้งานการลบเอกสารถาวร (Permanent Delete)');
        }

        $this->executePermanentDelete(
            document: $document,
            actor: $actor,
            reason: $reason,
            source: 'manual_permanent_delete',
            request: $request,
        );
    }

    private function executePermanentDelete(
        Document $document,
        ?User $actor,
        string $reason,
        string $source,
        ?int $retentionDays = null,
        ?Request $request = null,
    ): void {
        DB::transaction(function () use ($document, $actor, $reason, $source, $retentionDays, $request): void {
            /** @var Document $lockedDoc */
            $lockedDoc = Document::onlyTrashed()
                ->with(['revisions.storedFile', 'patients'])
                ->whereKey($document->id)
                ->lockForUpdate()
                ->firstOrFail();

            $primaryPatient = $lockedDoc->patients->first();
            $docId = $lockedDoc->id;
            $docNumber = $lockedDoc->document_number;
            $deptId = $lockedDoc->department_id;
            $filesToDelete = [];

            foreach ($lockedDoc->revisions as $rev) {
                if ($rev->storedFile !== null) {
                    $filesToDelete[] = [
                        'id' => $rev->storedFile->id,
                        'disk' => $rev->storedFile->storage_disk,
                        'path' => $rev->storedFile->storage_path,
                    ];
                }
            }

            // Delete document (cascades links, snapshots, revisions, delete_requests; audit_logs untouched)
            $lockedDoc->forceDelete();

            foreach ($filesToDelete as $fileInfo) {
                DB::table('stored_files')->where('id', $fileInfo['id'])->delete();
                $this->fileStorage->deletePrivateFile($fileInfo['disk'], $fileInfo['path']);
            }

            $afterData = [
                'document_number' => $docNumber,
                'permanent_delete_reason' => $reason,
                'source' => $source,
                'deleted_file_count' => count($filesToDelete),
            ];
            if ($retentionDays !== null) {
                $afterData['retention_days'] = $retentionDays;
            }

            $this->auditLog->record(
                action: 'permanent_delete',
                user: $actor,
                outcome: 'success',
                documentId: $docId,
                hnHash: $primaryPatient?->hn_hash,
                afterData: $afterData,
                request: $request,
                departmentId: $deptId,
            );
        });
    }
}
