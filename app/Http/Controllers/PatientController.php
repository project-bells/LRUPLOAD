<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PatientType;
use App\Models\Patient;
use App\Models\User;
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
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Patient::class);

        $query = Patient::query()->withCount('documents')->latest('updated_at');

        $type = $request->string('patient_type')->toString();
        if (in_array($type, ['mother', 'child'], true)) {
            $query->where('patient_type', $type);
        }

        $patients = $query->paginate(15)->withQueryString();

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

        $normalized = $this->hnSecurity->normalize((string) $validated['hn']);
        if (! $this->hnSecurity->isValid($normalized)) {
            return back()->withErrors(['hn' => 'รูปแบบ HN ไม่ถูกต้อง']);
        }

        $hash = $this->hnSecurity->hash($normalized);
        $patient = Patient::query()->where('hn_hash', $hash)->first();

        if ($patient === null) {
            return back()->withErrors(['hn' => 'ไม่พบประวัติผู้ป่วย HN นี้ในระบบเอกสาร (MySQL)']);
        }

        return redirect()->route('patients.show', $patient);
    }

    public function show(Patient $patient): View
    {
        $this->authorize('view', $patient);

        $patient->load([
            'mothers',
            'children',
            'documents' => static fn ($q) => $q->with(['documentType', 'currentRevision.storedFile', 'creator'])->latest(),
        ]);

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

        $validated = $request->validate([
            'related_patient_id' => ['required', 'integer', 'exists:patients,id', 'different:' . $patient->id],
        ], [
            'related_patient_id.required' => 'กรุณาเลือกผู้ป่วยที่ต้องการเชื่อมความสัมพันธ์',
        ]);

        $related = Patient::query()->findOrFail((int) $validated['related_patient_id']);

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
