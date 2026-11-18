<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PatientType;
use App\Models\Patient;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\HnSecurityService;
use App\Services\PatientSnapshotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function __construct(
        private readonly HnSecurityService $hnSecurity,
        private readonly PatientSnapshotService $snapshotService,
        private readonly AuditLogService $auditLog,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Patient::class);

        /** @var User $user */
        $user = $request->user();
        $isSystemWide = $user->hasPermission('audit.view_all') || $user->hasRole('administrator') || $user->department_id === null;

        $query = Patient::query()
            ->withCount(['documents' => static function ($q) use ($isSystemWide, $user): void {
                if (! $isSystemWide) {
                    $q->where('department_id', $user->department_id);
                }
            }])
            ->latest('updated_at');

        // Scope patients to current department (allow newly selected patients with 0 documents)
        if (! $isSystemWide) {
            $deptId = $user->department_id;
            $query->where(static function ($q) use ($deptId): void {
                $q->whereDoesntHave('documents', static fn ($dq) => $dq->withTrashed())
                    ->orWhereHas('documents', static fn ($dq) => $dq->withTrashed()->where('department_id', $deptId));
            });
        }

        $type = $request->string('patient_type')->toString();
        if (in_array($type, ['mother', 'child'], true)) {
            $query->where('patient_type', $type);
        }

        $patients = $query->paginate(20)->withQueryString();

        return view('patients.index', [
            'patients' => $patients,
            'selectedType' => $type,
        ]);
    }

    /**
     * POST /patients/search-local — Search existing MySQL patient snapshots by HN hash (works even when SQL Server is down).
     */
    public function searchLocal(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Patient::class);

        $validated = $request->validate([
            'hn' => ['required', 'string', 'max:30'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $normalized = $this->hnSecurity->normalize((string) $validated['hn']);
        if (! $this->hnSecurity->isValid($normalized)) {
            return back()->withErrors(['hn' => 'รูปแบบ HN ไม่ถูกต้อง']);
        }

        $hash = $this->hnSecurity->hash($normalized);
        $patient = Patient::query()->where('hn_hash', $hash)->first();

        if ($patient === null || ! $user->can('view', $patient)) {
            $this->auditLog->record(
                action: 'patient_lookup_not_found',
                user: $user,
                outcome: 'failed',
                hnHash: $hash,
                afterData: ['source' => 'local_mysql'],
                request: $request,
            );

            return back()->withErrors(['hn' => 'ไม่พบประวัติผู้ป่วย HN นี้ในระบบเอกสาร (MySQL)']);
        }

        $this->auditLog->record(
            action: 'view_patient',
            user: $user,
            outcome: 'success',
            hnHash: $patient->hn_hash,
            afterData: ['source' => 'local_mysql', 'patient_id' => $patient->id],
            request: $request,
        );

        return redirect()->route('patients.show', $patient);
    }

    public function show(Request $request, Patient $patient): View
    {
        $this->authorize('view', $patient);

        /** @var User $user */
        $user = $request->user();
        $isSystemWide = $user->hasPermission('audit.view_all') || $user->hasRole('administrator') || $user->department_id === null;

        $patient->load([
            'mothers',
            'children',
            'documents' => static function ($q) use ($isSystemWide, $user): void {
                if (! $isSystemWide) {
                    $q->where('department_id', $user->department_id);
                }
                $q->with(['documentType', 'currentRevision.storedFile', 'creator'])->latest();
            },
        ]);

        $this->auditLog->record(
            action: 'view_patient',
            user: $user,
            outcome: 'success',
            hnHash: $patient->hn_hash,
            afterData: ['patient_id' => $patient->id],
            request: $request,
        );

        return view('patients.show', [
            'patient' => $patient,
        ]);
    }

    public function relationships(Patient $patient): View
    {
        $this->authorize('view', $patient);

        $patient->load(['mothers', 'children']);

        $candidateType = $patient->patient_type === PatientType::Mother
            ? PatientType::Child->value
            : PatientType::Mother->value;

        $candidates = Patient::query()
            ->where('patient_type', $candidateType)
            ->where('id', '!=', $patient->id)
            ->latest('updated_at')
            ->limit(100)
            ->get();

        return view('patients.relationships', [
            'patient' => $patient,
            'candidates' => $candidates,
        ]);
    }

    public function storeRelationship(Request $request, Patient $patient): RedirectResponse
    {
        $this->authorize('linkRelationship', Patient::class);
        $this->authorize('view', $patient);

        $validated = $request->validate([
            'related_patient_id' => ['required', 'integer', 'exists:patients,id', 'different:' . $patient->id],
        ], [
            'related_patient_id.required' => 'กรุณาเลือกผู้ป่วยที่ต้องการเชื่อมความสัมพันธ์',
        ]);

        $related = Patient::query()->findOrFail((int) $validated['related_patient_id']);
        $this->authorize('view', $related);

        if ($patient->patient_type === $related->patient_type) {
            return back()->withErrors(['related_patient_id' => 'ต้องเชื่อมความสัมพันธ์ระหว่างมารดากับทารกเท่านั้น']);
        }

        $mother = $patient->patient_type === PatientType::Mother ? $patient : $related;
        $child = $patient->patient_type === PatientType::Child ? $patient : $related;

        /** @var User $user */
        $user = $request->user();
        $this->snapshotService->linkMotherAndChild($mother, $child, $user, 'manual_verified', $request);

        return redirect()->route('patients.relationships', $patient)
            ->with('status', 'บันทึกความสัมพันธ์มารดาและทารกพร้อมผู้ยืนยันเรียบร้อยแล้ว');
    }
}
