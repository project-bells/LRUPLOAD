<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmRevisionRequest;
use App\Http\Requests\StageRevisionRequest;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentRevisionService;
use App\Services\FileStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DocumentRevisionController extends Controller
{
    public function __construct(
        private readonly FileStorageService $fileStorage,
        private readonly DocumentRevisionService $revisionService,
    ) {
    }

    public function index(Document $document): View
    {
        $this->authorize('view', $document);

        $document->load(['documentType', 'patients', 'revisions.storedFile', 'revisions.creator']);

        return view('documents.revisions.index', [
            'document' => $document,
        ]);
    }

    public function create(Document $document): View
    {
        $this->authorize('createRevision', $document);

        $document->load(['documentType', 'patients', 'currentRevision.storedFile']);

        return view('documents.revisions.create', [
            'document' => $document,
        ]);
    }

    public function stage(StageRevisionRequest $request, Document $document): RedirectResponse
    {
        $this->authorize('createRevision', $document);

        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $stagedFile = $this->fileStorage->stageUpload($request->file('file'), $user);
        $stagingToken = (string) $stagedFile['token'];
        $idempotencyToken = (string) Str::uuid();

        $request->session()->put("staged_revisions.{$stagingToken}", [
            'user_id' => $user->id,
            'document_id' => $document->id,
            'change_reason' => (string) $validated['change_reason'],
            'notes' => $validated['notes'] ?? null,
            'file' => $stagedFile,
            'idempotency_token' => $idempotencyToken,
            'created_at' => now()->getTimestamp(),
        ]);

        return redirect()->route('documents.revisions.review', [
            'document' => $document->id,
            'token' => $stagingToken,
        ]);
    }

    public function review(Request $request, Document $document, string $token): View|RedirectResponse
    {
        $this->authorize('createRevision', $document);

        /** @var User $user */
        $user = $request->user();
        $staged = $request->session()->get("staged_revisions.{$token}");

        if (
            ! is_array($staged) ||
            (int) ($staged['user_id'] ?? 0) !== $user->id ||
            (int) ($staged['document_id'] ?? 0) !== $document->id
        ) {
            return redirect()->route('documents.revisions.create', $document)
                ->withErrors(['file' => 'รายการตรวจสอบ Revision หมดอายุหรือไม่ถูกต้อง กรุณาอัปโหลดใหม่']);
        }

        $document->load(['documentType', 'patients', 'currentRevision.storedFile']);

        $streamUrl = URL::temporarySignedRoute(
            'documents.staged.stream',
            now()->addMinutes((int) config('documents.preview_url_ttl_minutes', 10)),
            ['token' => $token],
            false
        );

        return view('documents.revisions.review', [
            'document' => $document,
            'stagingToken' => $token,
            'idempotencyToken' => (string) $staged['idempotency_token'],
            'changeReason' => (string) $staged['change_reason'],
            'notes' => $staged['notes'],
            'file' => $staged['file'],
            'streamUrl' => $streamUrl,
        ]);
    }

    public function confirm(ConfirmRevisionRequest $request, Document $document): RedirectResponse
    {
        $this->authorize('createRevision', $document);

        /** @var User $user */
        $user = $request->user();
        $stagingToken = (string) $request->validated('staging_token');
        $idempotencyToken = (string) $request->validated('idempotency_token');

        $staged = $request->session()->pull("staged_revisions.{$stagingToken}");

        if (
            ! is_array($staged) ||
            (int) ($staged['user_id'] ?? 0) !== $user->id ||
            (int) ($staged['document_id'] ?? 0) !== $document->id ||
            ! hash_equals((string) ($staged['idempotency_token'] ?? ''), $idempotencyToken)
        ) {
            return redirect()->route('documents.show', $document)
                ->withErrors(['idempotency_token' => 'Revision นี้ถูกบันทึกไปแล้วหรือหมดอายุ']);
        }

        $revision = $this->revisionService->addRevisionFromStagedUpload(
            document: $document,
            stagedFile: $staged['file'],
            changeReason: (string) $staged['change_reason'],
            notes: $staged['notes'],
            actor: $user,
            request: $request,
        );

        return redirect()->route('documents.show', $document)
            ->with('status', "สร้าง Revision {$revision->revision_number} เรียบร้อยแล้ว (Revision เดิมถูกเก็บเป็นประวัติแบบ Superseded)");
    }
}
