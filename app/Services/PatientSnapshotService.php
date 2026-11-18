<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\PatientData;
use App\Models\Document;
use App\Models\DocumentPatientSnapshot;
use App\Models\Patient;
use App\Models\PatientRelationship;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PatientSnapshotService
{
    public function __construct(
        private readonly HnSecurityService $hnSecurity,
        private readonly AuditLogService $auditLog,
    ) {
    }

    /**
     * Create or update a Patient Snapshot in MySQL ONLY when the user selects the patient
     * or confirms saving a document.
     */
    public function upsertFromDto(
        PatientData $dto,
        User $actor,
        ?string $sourceFetchedAt = null,
        ?Request $request = null,
        bool $logSelection = false,
    ): Patient {
        $sourceSystem = (string) config('patient_lookup.source_system', 'his_sqlsrv_2014');
        $normalizedHn = $this->hnSecurity->normalize($dto->hn);
        $hnHash = $this->hnSecurity->hash($normalizedHn);

        $motherHn = $dto->motherHn !== null && trim($dto->motherHn) !== ''
            ? $this->hnSecurity->normalize($dto->motherHn)
            : null;
        $motherHnHash = $motherHn !== null ? $this->hnSecurity->hash($motherHn) : null;

        $existing = Patient::query()
            ->where('source_system', $sourceSystem)
            ->where('hn_hash', $hnHash)
            ->first();

        $isNew = $existing === null;
        $patient = $existing ?? new Patient();

        $patient->fill([
            'source_system' => $sourceSystem,
            'patient_type' => $dto->patientType,
            'hn_encrypted' => $normalizedHn,
            'hn_hash' => $hnHash,
            'title_name_encrypted' => $dto->titleName,
            'first_name_encrypted' => $dto->firstName,
            'last_name_encrypted' => $dto->lastName,
            'birth_date_encrypted' => $dto->birthDate,
            'sex' => $dto->sex,
            'national_id_masked' => $dto->nationalIdMasked,
            'admission_number_encrypted' => $dto->admissionNumber,
            'ward' => $dto->ward,
            'mother_hn_encrypted' => $motherHn,
            'mother_hn_hash' => $motherHnHash,
            'source_updated_at' => $dto->sourceUpdatedAt !== null ? Carbon::parse($dto->sourceUpdatedAt) : null,
            'source_fetched_at' => $sourceFetchedAt !== null ? Carbon::parse($sourceFetchedAt) : now(),
        ]);
        $patient->save();

        // Automatically link mother-child ONLY if HIS explicitly provided mother_hn and mother exists in MySQL
        if ($motherHnHash !== null && $dto->patientType === 'child') {
            $mother = Patient::query()
                ->where('source_system', $sourceSystem)
                ->where('hn_hash', $motherHnHash)
                ->first();

            if ($mother !== null && $mother->id !== $patient->id) {
                PatientRelationship::query()->firstOrCreate(
                    [
                        'mother_patient_id' => $mother->id,
                        'child_patient_id' => $patient->id,
                    ],
                    [
                        'relationship_source' => 'his_sqlsrv',
                        'verified_by' => $actor->id,
                        'verified_at' => now(),
                    ]
                );
            }
        }

        if ($logSelection) {
            $this->auditLog->record(
                action: 'patient_selected',
                user: $actor,
                outcome: 'success',
                hnHash: $hnHash,
                afterData: ['patient_id' => $patient->id, 'patient_type' => $dto->patientType],
                request: $request,
            );
        }

        $this->auditLog->record(
            action: $isNew ? 'patient_snapshot_created' : 'patient_snapshot_updated',
            user: $actor,
            outcome: 'success',
            hnHash: $hnHash,
            afterData: ['patient_id' => $patient->id, 'patient_type' => $dto->patientType],
            request: $request,
        );

        return $patient;
    }

    /**
     * Explicitly link a mother and child patient with verifier tracking and Audit Log.
     */
    public function linkMotherAndChild(
        Patient $mother,
        Patient $child,
        User $verifier,
        string $source = 'manual_verified',
        ?Request $request = null,
    ): PatientRelationship {
        $relationship = PatientRelationship::query()->updateOrCreate(
            [
                'mother_patient_id' => $mother->id,
                'child_patient_id' => $child->id,
            ],
            [
                'relationship_source' => $source,
                'verified_by' => $verifier->id,
                'verified_at' => now(),
            ]
        );

        $this->auditLog->record(
            action: 'patient_snapshot_updated',
            user: $verifier,
            outcome: 'success',
            hnHash: $mother->hn_hash,
            afterData: [
                'relationship_id' => $relationship->id,
                'mother_patient_id' => $mother->id,
                'child_patient_id' => $child->id,
                'relationship_source' => $source,
            ],
            request: $request,
        );

        return $relationship;
    }

    /**
     * Freeze an immutable encrypted patient snapshot attached to a specific document.
     */
    public function captureDocumentSnapshot(Document $document, Patient $patient): DocumentPatientSnapshot
    {
        return DocumentPatientSnapshot::query()->create([
            'document_id' => $document->id,
            'patient_id' => $patient->id,
            'snapshot_data_encrypted' => $patient->toSnapshotPayload(),
            'source_fetched_at' => $patient->source_fetched_at ?? now(),
            'created_at' => now(),
        ]);
    }
}
