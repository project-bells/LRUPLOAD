<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PermanentDeleteRequest;
use App\Http\Requests\ReviewDeleteRequest;
use App\Http\Requests\StoreDeleteRequest;
use App\Models\DeleteRequest;
use App\Models\Document;
use App\Models\User;
use App\Services\DeleteRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class DeleteRequestController extends Controller
{
    public function __construct(
        private readonly DeleteRequestService $deleteRequestService,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', DeleteRequest::class);

        $status = $request->string('status', 'pending')->toString();
        $query = DeleteRequest::query()
            ->with(['document.documentType', 'document.patients', 'requester', 'reviewer'])
            ->latest();

        if (in_array($status, ['pending', 'approved', 'rejected', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        return view('delete-requests.index', [
            'deleteRequests' => $query->paginate(15)->withQueryString(),
            'selectedStatus' => $status,
        ]);
    }

    public function create(Document $document): View
    {
        $this->authorize('requestDelete', $document);

        $document->load(['documentType', 'patients', 'currentRevision.storedFile', 'pendingDeleteRequest']);

        return view('delete-requests.create', [
            'document' => $document,
        ]);
    }

    public function store(StoreDeleteRequest $request, Document $document): RedirectResponse
    {
        $this->authorize('requestDelete', $document);

        /** @var User $user */
        $user = $request->user();

        try {
            $this->deleteRequestService->createRequest(
                document: $document,
                requester: $user,
                reason: (string) $request->validated('request_reason'),
                request: $request,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['request_reason' => $e->getMessage()]);
        }

        return redirect()->route('documents.show', $document)
            ->with('status', 'ส่งคำขอลบเอกสารเรียบร้อยแล้ว อยู่ระหว่างรอหัวหน้าหน่วยงานหรือผู้ดูแลระบบพิจารณา');
    }

    public function review(ReviewDeleteRequest $request, DeleteRequest $deleteRequest): RedirectResponse
    {
        $this->authorize('review', $deleteRequest);

        /** @var User $user */
        $user = $request->user();
        $decision = (string) $request->validated('decision');
        $note = $request->validated('review_note');

        try {
            if ($decision === 'approve') {
                $this->deleteRequestService->approve($deleteRequest, $user, is_string($note) ? $note : null, $request);
                $message = 'อนุมัติคำขอลบและย้ายเอกสารเข้าสู่ถังเอกสาร (Soft Delete) เรียบร้อยแล้ว';
            } else {
                $this->deleteRequestService->reject($deleteRequest, $user, (string) $note, $request);
                $message = 'ปฏิเสธคำขอลบเอกสารเรียบร้อยแล้ว';
            }
        } catch (RuntimeException $e) {
            return back()->withErrors(['decision' => $e->getMessage()]);
        }

        return redirect()->route('delete-requests.index')->with('status', $message);
    }

    public function directSoftDelete(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('softDelete', $document);

        $validated = $request->validate([
            'delete_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'delete_reason.required' => 'กรุณาระบุเหตุผลในการย้ายเอกสารเข้าถังเอกสาร',
        ]);

        /** @var User $user */
        $user = $request->user();
        $this->deleteRequestService->directSoftDelete($document, $user, (string) $validated['delete_reason'], $request);

        return redirect()->route('documents.trash')
            ->with('status', 'ย้ายเอกสารเข้าถังเอกสาร (Soft Delete) เรียบร้อยแล้ว ไฟล์และประวัติทั้งหมดยังคงถูกเก็บรักษาไว้');
    }

    public function trash(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->hasAnyPermission(['documents.restore', 'documents.soft_delete', 'documents.permanent_delete'])) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงถังเอกสาร');
        }

        $query = Document::onlyTrashed()
            ->with(['documentType', 'patients', 'deleter', 'creator'])
            ->latest('deleted_at');

        if (! $user->hasPermission('audit.view_all') && ! $user->hasRole('administrator') && $user->department_id !== null) {
            $query->where('department_id', $user->department_id);
        }

        return view('documents.trash', [
            'documents' => $query->paginate(20),
            'permanentDeleteEnabled' => $this->deleteRequestService->isPermanentDeleteEnabled(),
            'retentionDays' => $this->deleteRequestService->getSoftDeleteRetentionDays(),
        ]);
    }

    public function restore(Request $request, int $documentId): RedirectResponse
    {
        $document = Document::onlyTrashed()->findOrFail($documentId);
        $this->authorize('restore', $document);

        /** @var User $user */
        $user = $request->user();
        $this->deleteRequestService->restore($document, $user, $request->string('note')->toString() ?: null, $request);

        return redirect()->route('documents.show', $document)
            ->with('status', 'กู้คืนเอกสารเรียบร้อยแล้ว');
    }

    public function permanentDelete(PermanentDeleteRequest $request, int $documentId): RedirectResponse
    {
        $document = Document::onlyTrashed()->findOrFail($documentId);
        $this->authorize('forceDelete', $document);

        /** @var User $user */
        $user = $request->user();

        try {
            $this->deleteRequestService->permanentDelete(
                document: $document,
                actor: $user,
                reason: (string) $request->validated('reason'),
                request: $request,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return redirect()->route('documents.trash')
            ->with('status', 'ดำเนินการลบเอกสารถาวรเรียบร้อยแล้ว (Audit Log ที่เกี่ยวข้องยังคงถูกเก็บรักษาไว้)');
    }
}
