<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\PatientData;
use App\Enums\LookupStatus;
use App\Http\Requests\PatientLookupRequest;
use App\Models\Patient;
use App\Models\User;
use App\Services\HnSecurityService;
use App\Services\PatientLookupService;
use App\Services\PatientSnapshotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PatientLookupController extends Controller
{
    public function __construct(
        private readonly PatientLookupService $lookupService,
        private readonly PatientSnapshotService $snapshotService,
        private readonly HnSecurityService $hnSecurity,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('lookup', Patient::class);

        return view('patients.lookup', [
            'result' => null,
            'existingSnapshot' => null,
            'selectionToken' => null,
            'searchedPatientType' => 'auto',
            'hisEnabled' => (bool) config('patient_lookup.enabled', false),
        ]);
    }

    /**
     * POST /patients/lookup — CSRF protected, never puts HN in URL or Browser History.
     * Does NOT create or update any patient snapshot in MySQL.
     */
    public function lookup(PatientLookupRequest $request): View
    {
        $this->authorize('lookup', Patient::class);

        /** @var User $user */
        $user = $request->user();
        $hn = (string) $request->validated('hn');
        $patientType = (string) ($request->validated('patient_type') ?? 'auto');

        $result = $this->lookupService->lookup($hn, $user, $patientType, $request);

        $hnHash = $this->hnSecurity->isValid($hn) ? $this->hnSecurity->hash($hn) : null;
        $existingSnapshot = $hnHash !== null
            ? Patient::query()->with(['mothers', 'children'])->where('hn_hash', $hnHash)->first()
            : null;

        $selectionToken = null;
        if ($result->isFound() && $result->patient !== null) {
            $selectionToken = (string) Str::uuid();
            // Store verified lookup DTO encrypted in server session until user clicks "เลือกผู้ป่วย"
            $request->session()->put("patient_lookup_tokens.{$selectionToken}", [
                'payload' => Crypt::encryptString((string) json_encode($result->patient->toArray())),
                'fetched_at' => $result->sourceFetchedAt ?? now()->toIso8601String(),
                'user_id' => $user->id,
                'expires_at' => now()->addMinutes(15)->getTimestamp(),
            ]);
        }

        return view('patients.lookup', [
            'result' => $result,
            'existingSnapshot' => $existingSnapshot,
            'selectionToken' => $selectionToken,
            'searchedPatientType' => $patientType,
            'hisEnabled' => (bool) config('patient_lookup.enabled', false),
        ]);
    }

    /**
     * POST /patients/select — Persists Patient Snapshot to MySQL ONLY when user explicitly selects the patient.
     */
    public function select(Request $request): RedirectResponse
    {
        $this->authorize('lookup', Patient::class);

        $validated = $request->validate([
            'selection_token' => ['required', 'uuid'],
            'patient_type' => ['nullable', 'string', 'in:mother,child'],
            'next_action' => ['nullable', 'string', 'in:profile,create_document'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $token = (string) $validated['selection_token'];
        $entry = $request->session()->pull("patient_lookup_tokens.{$token}");

        if (
            ! is_array($entry) ||
            (int) ($entry['user_id'] ?? 0) !== $user->id ||
            (int) ($entry['expires_at'] ?? 0) < now()->getTimestamp()
        ) {
            return redirect()->route('patients.lookup.index')
                ->withErrors(['hn' => 'ข้อมูลการค้นหาหมดอายุ กรุณาค้นหา HN ใหม่อีกครั้ง']);
        }

        $decoded = json_decode(Crypt::decryptString((string) $entry['payload']), true);
        if (! is_array($decoded)) {
            return redirect()->route('patients.lookup.index')
                ->withErrors(['hn' => LookupStatus::UnexpectedError->userMessageTh()]);
        }

        // Allow user to explicitly confirm mother/child when HIS does not provide a dedicated type column
        if (! empty($validated['patient_type'])) {
            $decoded['patient_type'] = (string) $validated['patient_type'];
        }

        $dto = PatientData::fromArray($decoded);
        $patient = $this->snapshotService->upsertFromDto(
            dto: $dto,
            actor: $user,
            sourceFetchedAt: (string) ($entry['fetched_at'] ?? now()->toIso8601String()),
            request: $request,
            logSelection: true,
        );

        if (($validated['next_action'] ?? 'profile') === 'create_document' && $user->hasPermission('documents.create')) {
            return redirect()->route('documents.create', ['patient' => $patient->id])
                ->with('status', 'เลือกผู้ป่วยและบันทึก Patient Snapshot เรียบร้อยแล้ว');
        }

        return redirect()->route('patients.show', $patient)
            ->with('status', 'เลือกผู้ป่วยและบันทึกข้อมูล Snapshot เรียบร้อยแล้ว');
    }
}
