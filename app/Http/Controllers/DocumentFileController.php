<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RevisionStatus;
use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\FileStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentFileController extends Controller
{
    public function __construct(
        private readonly FileStorageService $fileStorage,
        private readonly AuditLogService $auditLog,
    ) {
    }

    /**
     * Full-screen / standalone PDF.js & Image preview page (optimized for mobile & desktop deep links).
     */
    public function preview(Request $request, Document $document, DocumentRevision $revision): View
    {
        $this->authorize('preview', $document);
        $this->assertRevisionBelongsToDocument($document, $revision, $request->user());

        $document->load(['documentType', 'patients', 'currentRevision']);
        $revision->load(['storedFile', 'creator']);

        $streamUrl = URL::temporarySignedRoute(
            'documents.revisions.stream',
            now()->addMinutes((int) config('documents.preview_url_ttl_minutes', 10)),
            ['document' => $document->id, 'revision' => $revision->id],
            false
        );

        return view('documents.preview', [
            'document' => $document,
            'revision' => $revision,
            'streamUrl' => $streamUrl,
        ]);
    }

    /**
     * Authorized short-lived signed stream endpoint consumed by PDF.js or Image Viewer.
     * Never exposes real storage path and forbids caching.
     */
    public function stream(Request $request, Document $document, DocumentRevision $revision): BinaryFileResponse
    {
        if (! $request->hasValidSignature(false) && ! $request->hasValidSignature()) {
            abort(403, 'ลิงก์ดูตัวอย่างหมดอายุ กรุณารีเฟรชหน้าจอ');
        }

        $this->authorize('preview', $document);

        /** @var User $user */
        $user = $request->user();
        $this->assertRevisionBelongsToDocument($document, $revision, $user);

        $revision->loadMissing('storedFile');
        $storedFile = $revision->storedFile;
        if ($storedFile === null) {
            abort(404, 'ไม่พบไฟล์เอกสาร');
        }

        $absPath = $this->fileStorage->resolveAbsolutePath($storedFile);
        $primaryPatient = $document->primaryPatient();

        $this->auditLog->record(
            action: 'preview_document',
            user: $user,
            outcome: 'success',
            documentId: $document->id,
            revisionId: $revision->id,
            hnHash: $primaryPatient?->hn_hash,
            request: $request,
            departmentId: $document->department_id,
        );

        return response()->file($absPath, [
            'Content-Type' => $storedFile->mime_type,
            'Content-Disposition' => 'inline; filename="' . basename($storedFile->original_name) . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Authorized file download endpoint (strictly checks documents.download permission).
     */
    public function download(Request $request, Document $document, DocumentRevision $revision): BinaryFileResponse
    {
        $this->authorize('download', $document);

        /** @var User $user */
        $user = $request->user();
        $this->assertRevisionBelongsToDocument($document, $revision, $user);

        $revision->loadMissing('storedFile');
        $storedFile = $revision->storedFile;
        if ($storedFile === null) {
            abort(404, 'ไม่พบไฟล์เอกสาร');
        }

        $absPath = $this->fileStorage->resolveAbsolutePath($storedFile);
        $primaryPatient = $document->primaryPatient();

        $this->auditLog->record(
            action: 'download_document',
            user: $user,
            outcome: 'success',
            documentId: $document->id,
            revisionId: $revision->id,
            hnHash: $primaryPatient?->hn_hash,
            request: $request,
            departmentId: $document->department_id,
        );

        return response()->download($absPath, basename($storedFile->original_name), [
            'Content-Type' => $storedFile->mime_type,
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function assertRevisionBelongsToDocument(Document $document, DocumentRevision $revision, ?User $user): void
    {
        if ((int) $revision->document_id !== (int) $document->id) {
            abort(404, 'ไม่พบ Revision ในเอกสารนี้');
        }

        // Viewing non-current historical revisions requires revisions.view_all permission
        if (
            $revision->status !== RevisionStatus::Current &&
            ($user === null || ! $user->hasPermission('revisions.view_all'))
        ) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงไฟล์ Revision เก่า');
        }
    }
}
