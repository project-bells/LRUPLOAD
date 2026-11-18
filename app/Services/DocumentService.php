<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RevisionStatus;
use App\Models\Document;
use App\Models\DocumentPatientLink;
use App\Models\DocumentRevision;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class DocumentService
{
    public function __construct(
        private readonly FileStorageService $fileStorage,
        private readonly PatientSnapshotService $snapshotService,
        private readonly AuditLogService $auditLog,
    ) {
    }

    /**
     * Create a document, link patient(s), freeze immutable snapshot(s),
     * move staged file to private storage, create Revision 1, and write Audit Log atomically.
     *
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $stagedFile
     * @param array<int, array{patient: Patient, relationship_type: string}> $linkedPatients
     */
    public function createDocumentFromStagedUpload(
        Patient $primaryPatient,
        array $metadata,
        array $stagedFile,
        User $actor,
        array $linkedPatients = [],
        ?Request $request = null,
    ): Document {
        $promotedDisk = null;
        $promotedPath = null;

        try {
            $document = DB::transaction(function () use (
                $primaryPatient,
                $metadata,
                $stagedFile,
                $actor,
                $linkedPatients,
                $request,
                &$promotedDisk,
                &$promotedPath
            ): Document {
                $documentNumber = $this->generateDocumentNumber();

                $document = Document::query()->create([
                    'document_number' => $documentNumber,
                    'department_id' => $actor->department_id,
                    'title' => (string) $metadata['title'],
                    'document_type_id' => (int) $metadata['document_type_id'],
                    'patient_owner_type' => (string) $metadata['patient_owner_type'],
                    'document_date' => (string) $metadata['document_date'],
                    'received_date' => $metadata['received_date'] ?? null,
                    'reference_number' => $metadata['reference_number'] ?? null,
                    'description' => $metadata['description'] ?? null,
                    'notes' => $metadata['notes'] ?? null,
                    'confidentiality_level' => (string) ($metadata['confidentiality_level'] ?? 'confidential'),
                    'created_by' => $actor->id,
                ]);

                // Link primary patient and capture immutable snapshot evidence
                $primaryRelType = $primaryPatient->patient_type?->value ?? 'primary';
                DocumentPatientLink::query()->create([
                    'document_id' => $document->id,
                    'patient_id' => $primaryPatient->id,
                    'relationship_type' => $primaryRelType,
                    'created_at' => now(),
                ]);
                $this->snapshotService->captureDocumentSnapshot($document, $primaryPatient);

                // Link any additional related patient (e.g. mother + child)
                foreach ($linkedPatients as $item) {
                    $relPatient = $item['patient'];
                    if ($relPatient->id === $primaryPatient->id) {
                        continue;
                    }

                    DocumentPatientLink::query()->firstOrCreate([
                        'document_id' => $document->id,
                        'patient_id' => $relPatient->id,
                        'relationship_type' => (string) $item['relationship_type'],
                    ], [
                        'created_at' => now(),
                    ]);

                    $this->snapshotService->captureDocumentSnapshot($document, $relPatient);
                }

                // Promote file from temporary staging into private storage
                $storedFile = $this->fileStorage->promoteStagedFile($stagedFile, $actor);
                $promotedDisk = $storedFile->storage_disk;
                $promotedPath = $storedFile->storage_path;

                // Create Revision 1
                $revision = DocumentRevision::query()->create([
                    'document_id' => $document->id,
                    'revision_number' => 1,
                    'stored_file_id' => $storedFile->id,
                    'previous_revision_id' => null,
                    'status' => RevisionStatus::Current->value,
                    'is_current_flag' => 1,
                    'change_reason' => 'จัดเก็บเอกสารครั้งแรก (Initial Revision)',
                    'notes' => $metadata['notes'] ?? null,
                    'created_by' => $actor->id,
                    'superseded_at' => null,
                ]);

                $this->auditLog->record(
                    action: 'create_document',
                    user: $actor,
                    outcome: 'success',
                    documentId: $document->id,
                    revisionId: $revision->id,
                    hnHash: $primaryPatient->hn_hash,
                    afterData: [
                        'document_number' => $document->document_number,
                        'document_type_id' => $document->document_type_id,
                        'patient_owner_type' => $document->patient_owner_type?->value ?? $metadata['patient_owner_type'],
                        'revision_number' => 1,
                        'sha256_hash' => $storedFile->sha256_hash,
                    ],
                    request: $request,
                    departmentId: $document->department_id,
                );

                return $document;
            });

            // Only delete temp file AFTER transaction commits cleanly
            $this->fileStorage->deleteStagedFile((string) ($stagedFile['tmp_path'] ?? ''));

            return $document;
        } catch (Throwable $e) {
            if ($promotedDisk !== null && $promotedPath !== null) {
                $this->fileStorage->deletePrivateFile($promotedDisk, $promotedPath);
            }

            throw $e;
        }
    }

    /**
     * Edit document metadata only. Patient HN/identity remains read-only and immutable.
     *
     * @param array<string, mixed> $validated
     */
    public function updateMetadata(Document $document, array $validated, User $actor, ?Request $request = null): Document
    {
        $before = [
            'title' => $document->title,
            'document_type_id' => $document->document_type_id,
            'document_date' => $document->document_date?->format('Y-m-d'),
            'received_date' => $document->received_date?->format('Y-m-d'),
            'reference_number' => $document->reference_number,
            'description' => $document->description,
            'notes' => $document->notes,
            'confidentiality_level' => $document->confidentiality_level?->value,
        ];

        $document->fill([
            'title' => (string) $validated['title'],
            'document_type_id' => (int) $validated['document_type_id'],
            'document_date' => (string) $validated['document_date'],
            'received_date' => $validated['received_date'] ?? null,
            'reference_number' => $validated['reference_number'] ?? null,
            'description' => $validated['description'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'confidentiality_level' => (string) $validated['confidentiality_level'],
        ]);
        $document->save();

        $primaryPatient = $document->primaryPatient();

        $this->auditLog->record(
            action: 'edit_metadata',
            user: $actor,
            outcome: 'success',
            documentId: $document->id,
            hnHash: $primaryPatient?->hn_hash,
            beforeData: $before,
            afterData: [
                'title' => $document->title,
                'document_type_id' => $document->document_type_id,
                'document_date' => $document->document_date?->format('Y-m-d'),
                'received_date' => $document->received_date?->format('Y-m-d'),
                'reference_number' => $document->reference_number,
                'confidentiality_level' => $document->confidentiality_level?->value,
            ],
            request: $request,
            departmentId: $document->department_id,
        );

        return $document;
    }

    private function generateDocumentNumber(): string
    {
        $prefix = 'LR-' . now()->format('Ym') . '-';
        $random = strtoupper(Str::random(6));
        $candidate = $prefix . $random;

        while (Document::withTrashed()->where('document_number', $candidate)->exists()) {
            $candidate = $prefix . strtoupper(Str::random(6));
        }

        return $candidate;
    }
}
