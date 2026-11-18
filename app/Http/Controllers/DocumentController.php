<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ConfidentialityLevel;
use App\Enums\PatientOwnerType;
use App\Enums\PatientType;
use App\Http\Requests\ConfirmDocumentRequest;
use App\Http\Requests\StageDocumentRequest;
use App\Http\Requests\UpdateDocumentMetadataRequest;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Patient;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\DocumentService;
use App\Services\FileStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller
{
    public function __construct(
        private readonly FileStorageService $fileStorage,
        private readonly DocumentService $documentService,
        private readonly AuditLogService $auditLog,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Document::class);

        /** @var User $user */
        $user = $request->user();

        $query = Document::query()
            ->with(['documentType', 'currentRevision.storedFile', 'patients', 'creator'])
            ->latest();

        if (! $user->hasPermission('audit.view_all') && ! $user->hasRole('administrator') && $user->department_id !== null) {
            $query->where('department_id', $user->department_id);
        }

        if ($request->filled('document_type_id')) {
            $query->where('document_type_id', (int) $request->input('document_type_id'));
        }

        if ($request->filled('owner_type')) {
            $query->where('patient_owner_type', (string) $request->input('owner_type'));
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(static function ($q) use ($term): void {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('document_number', 'like', "%{$term}%")
                    ->orWhere('reference_number', 'like', "%{$term}%");
            });
        }

        return view('documents.index', [
            'documents' => $query->paginate(20)->withQueryString(),
            'documentTypes' => DocumentType::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function create(Patient $patient): View
    {
        $this->authorize('create', Document::class);

        $patient->load(['mothers', 'children']);
        $relatedPatients = $patient->patient_type === PatientType::Mother
            ? $patient->children
            : $patient->mothers;

        return view('documents.create', [
            'patient' => $patient,
            'relatedPatients' => $relatedPatients,
            'documentTypes' => DocumentType::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'ownerTypes' => PatientOwnerType::cases(),
            'confidentialityLevels' => ConfidentialityLevel::cases(),
        ]);
    }

    /**
     * Step 1: Validate metadata + file, stage into temporary private storage, redirect to Review Before Save.
     */
    public function stage(StageDocumentRequest $request): RedirectResponse
    {
        $this->authorize('create', Document::class);

        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $stagedFile = $this->fileStorage->stageUpload($request->file('file'), $user);
        $stagingToken = (string) $stagedFile['token'];
        $idempotencyToken = (string) Str::uuid();

        $request->session()->put("staged_documents.{$stagingToken}", [
            'user_id' => $user->id,
            'patient_id' => (int) $validated['patient_id'],
            'related_patient_id' => isset($validated['related_patient_id']) ? (int) $validated['related_patient_id'] : null,
            'metadata' => [
                'document_type_id' => (int) $validated['document_type_id'],
                'patient_owner_type' => (string) $validated['patient_owner_type'],
                'title' => (string) $validated['title'],
                'document_date' => (string) $validated['document_date'],
                'received_date' => $validated['received_date'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'description' => $validated['description'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'confidentiality_level' => (string) $validated['confidentiality_level'],
            ],
            'file' => $stagedFile,
            'idempotency_token' => $idempotencyToken,
            'created_at' => now()->getTimestamp(),
        ]);

        return redirect()->route('documents.review', ['token' => $stagingToken]);
    }

    /**
     * Step 2: Review Before Save screen (Aikchol Hospital Logo + Patient Info + Metadata + File Preview).
     */
    public function review(Request $request, string $token): View|RedirectResponse
    {
        $this->authorize('create', Document::class);

        /** @var User $user */
        $user = $request->user();
        $staged = $request->session()->get("staged_documents.{$token}");

        if (! is_array($staged) || (int) ($staged['user_id'] ?? 0) !== $user->id) {
            return redirect()->route('patients.lookup.index')
                ->withErrors(['file' => 'ไม่พบข้อมูลเอกสารที่รอตรวจสอบ หรือรายการหมดอายุแล้ว กรุณาดำเนินการใหม่']);
        }

        $patient = Patient::query()->with(['mothers', 'children'])->findOrFail((int) $staged['patient_id']);
        $relatedPatient = isset($staged['related_patient_id']) && $staged['related_patient_id'] !== null
            ? Patient::query()->find((int) $staged['related_patient_id'])
            : null;
        $documentType = DocumentType::query()->findOrFail((int) $staged['metadata']['document_type_id']);

        $streamUrl = URL::temporarySignedRoute(
            'documents.staged.stream',
            now()->addMinutes((int) config('documents.preview_url_ttl_minutes', 10)),
            ['token' => $token],
            false
        );

        return view('documents.review', [
            'stagingToken' => $token,
            'idempotencyToken' => (string) $staged['idempotency_token'],
            'patient' => $patient,
            'relatedPatient' => $relatedPatient,
            'documentType' => $documentType,
            'metadata' => $staged['metadata'],
            'file' => $staged['file'],
            'streamUrl' => $streamUrl,
        ]);
    }

    /**
     * Authenticated & signed stream for staged temporary file preview on Review Before Save page.
     */
    public function streamStaged(Request $request, string $token): BinaryFileResponse
    {
        if (! $request->hasValidSignature(false) && ! $request->hasValidSignature()) {
            abort(403, 'ลิงก์ดูตัวอย่างหมดอายุหรือไม่ถูกต้อง');
        }

        /** @var User $user */
        $user = $request->user();
        $staged = $request->session()->get("staged_documents.{$token}")
            ?? $request->session()->get("staged_revisions.{$token}");

        if (! is_array($staged) || (int) ($staged['user_id'] ?? 0) !== $user->id) {
            abort(403, 'คุณไม่มีสิทธิ์เปิดดูไฟล์ชั่วคราวนี้');
        }

        $file = $staged['file'];
        $absPath = $this->fileStorage->resolveStagedAbsolutePath((string) $file['tmp_path']);

        return response()->file($absPath, [
            'Content-Type' => (string) $file['mime_type'],
            'Content-Disposition' => 'inline; filename="' . basename((string) $file['original_name']) . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Step 3: Confirm & Save atomically with idempotency token protection against duplicate submission.
     */
    public function confirm(ConfirmDocumentRequest $request): RedirectResponse
    {
        $this->authorize('create', Document::class);

        /** @var User $user */
        $user = $request->user();
        $stagingToken = (string) $request->validated('staging_token');
        $idempotencyToken = (string) $request->validated('idempotency_token');

        // Atomic pull from session prevents duplicate submission / double-click race
        $staged = $request->session()->pull("staged_documents.{$stagingToken}");

        if (
            ! is_array($staged) ||
            (int) ($staged['user_id'] ?? 0) !== $user->id ||
            ! hash_equals((string) ($staged['idempotency_token'] ?? ''), $idempotencyToken)
        ) {
            return redirect()->route('documents.index')
                ->withErrors(['idempotency_token' => 'รายการนี้ถูกบันทึกไปแล้วหรือหมดอายุ กรุณาตรวจสอบรายการเอกสาร']);
        }

        $primaryPatient = Patient::query()->findOrFail((int) $staged['patient_id']);
        $linkedPatients = [];
        if (isset($staged['related_patient_id']) && $staged['related_patient_id'] !== null) {
            $related = Patient::query()->find((int) $staged['related_patient_id']);
            if ($related !== null) {
                $linkedPatients[] = [
                    'patient' => $related,
                    'relationship_type' => $related->patient_type?->value ?? 'related',
                ];
            }
        }

        $document = $this->documentService->createDocumentFromStagedUpload(
            primaryPatient: $primaryPatient,
            metadata: $staged['metadata'],
            stagedFile: $staged['file'],
            actor: $user,
            linkedPatients: $linkedPatients,
            request: $request,
        );

        return redirect()->route('documents.show', $document)
            ->with('status', "บันทึกเอกสารเลขที่ {$document->document_number} (Revision 1) เรียบร้อยแล้ว");
    }

    public function show(Request $request, Document $document): View
    {
        $this->authorize('view', $document);

        /** @var User $user */
        $user = $request->user();

        $document->load([
            'documentType',
            'department',
            'creator',
            'patients',
            'patientSnapshots',
            'currentRevision.storedFile',
            'currentRevision.creator',
            'revisions.storedFile',
            'revisions.creator',
            'pendingDeleteRequest.requester',
        ]);

        $primaryPatient = $document->patients->first();

        $this->auditLog->record(
            action: 'view_document',
            user: $user,
            outcome: 'success',
            documentId: $document->id,
            revisionId: $document->currentRevision?->id,
            hnHash: $primaryPatient?->hn_hash,
            request: $request,
            departmentId: $document->department_id,
        );

        $previewUrls = [];
        $ttlMinutes = (int) config('documents.preview_url_ttl_minutes', 10);
        foreach ($document->revisions as $rev) {
            $previewUrls[$rev->id] = URL::temporarySignedRoute(
                'documents.revisions.stream',
                now()->addMinutes($ttlMinutes),
                ['document' => $document->id, 'revision' => $rev->id],
                false
            );
        }

        return view('documents.show', [
            'document' => $document,
            'previewUrls' => $previewUrls,
        ]);
    }

    public function editMetadata(Document $document): View
    {
        $this->authorize('updateMetadata', $document);

        $document->load(['patients', 'documentType']);

        return view('documents.edit-metadata', [
            'document' => $document,
            'documentTypes' => DocumentType::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'confidentialityLevels' => ConfidentialityLevel::cases(),
        ]);
    }

    public function updateMetadata(UpdateDocumentMetadataRequest $request, Document $document): RedirectResponse
    {
        $this->authorize('updateMetadata', $document);

        /** @var User $user */
        $user = $request->user();

        $this->documentService->updateMetadata($document, $request->validated(), $user, $request);

        return redirect()->route('documents.show', $document)
            ->with('status', 'บันทึกการแก้ไขข้อมูลเอกสาร (Metadata) เรียบร้อยแล้ว');
    }
}
