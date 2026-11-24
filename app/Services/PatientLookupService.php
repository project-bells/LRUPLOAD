<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PatientLookupInterface;
use App\DTOs\PatientData;
use App\DTOs\PatientLookupResult;
use App\Enums\LookupStatus;
use App\Exceptions\PatientLookupException;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Throwable;

class PatientLookupService
{
    public function __construct(
        private readonly PatientLookupInterface $adapter,
        private readonly HnSecurityService $hnSecurity,
        private readonly AuditLogService $auditLog,
    ) {
    }

    /**
     * Perform a read-only HN lookup against SQL Server 2014 via PatientLookupInterface.
     * IMPORTANT: Does NOT store patient data into MySQL; only records a PDPA-safe Audit Log using hn_hash.
     */
    public function lookup(
        string $rawHn,
        User $actor,
        ?string $expectedPatientType = null,
        ?Request $request = null,
    ): PatientLookupResult {
        $normalizedHn = $this->hnSecurity->normalize($rawHn);

        if (! $this->hnSecurity->isValid($normalizedHn)) {
            $this->auditLog->record(
                action: 'patient_lookup_failed',
                user: $actor,
                outcome: 'failed',
                hnHash: $normalizedHn !== '' ? $this->hnSecurity->hash($normalizedHn) : null,
                afterData: ['reason' => LookupStatus::InvalidHn->value],
                request: $request,
            );

            return new PatientLookupResult(status: LookupStatus::InvalidHn);
        }

        $hnHash = $this->hnSecurity->hash($normalizedHn);
        $fetchedAt = now()->toIso8601String();

        try {
            $patientData = $this->resolvePatientData($normalizedHn, $hnHash);

            if ($patientData === null) {
                $this->auditLog->record(
                    action: 'patient_lookup_not_found',
                    user: $actor,
                    outcome: 'failed',
                    hnHash: $hnHash,
                    afterData: ['status' => LookupStatus::NotFound->value],
                    request: $request,
                );

                return new PatientLookupResult(status: LookupStatus::NotFound);
            }

            // Optional patient type filter if user specifically selected mother/child
            if (
                $expectedPatientType !== null &&
                $expectedPatientType !== '' &&
                $expectedPatientType !== 'auto' &&
                $patientData->patientType !== $expectedPatientType
            ) {
                $this->auditLog->record(
                    action: 'patient_lookup_not_found',
                    user: $actor,
                    outcome: 'failed',
                    hnHash: $hnHash,
                    afterData: [
                        'status' => LookupStatus::NotFound->value,
                        'filter_patient_type' => $expectedPatientType,
                    ],
                    request: $request,
                );

                return new PatientLookupResult(
                    status: LookupStatus::NotFound,
                    message: 'พบข้อมูล HN ดังกล่าว แต่ไม่ตรงกับประเภทผู้ป่วยที่เลือก กรุณาลองเลือก "อัตโนมัติ"',
                );
            }

            $this->auditLog->record(
                action: 'patient_lookup',
                user: $actor,
                outcome: 'success',
                hnHash: $hnHash,
                afterData: [
                    'status' => LookupStatus::Found->value,
                    'patient_type' => $patientData->patientType,
                ],
                request: $request,
            );

            return new PatientLookupResult(
                status: LookupStatus::Found,
                patient: $patientData,
                sourceFetchedAt: $fetchedAt,
            );
        } catch (PatientLookupException $e) {
            $errorDetail = $this->hnSecurity->redactInlineSecrets($e->getMessage());

            Log::error('Patient lookup failed with classified HIS exception.', [
                'lookup_status' => $e->lookupStatus->value,
                'hn_hash' => $hnHash,
                'user_id' => $actor->id,
                'error_detail' => $errorDetail,
            ]);

            $this->auditLog->record(
                action: 'patient_lookup_failed',
                user: $actor,
                outcome: 'failed',
                hnHash: $hnHash,
                afterData: [
                    'status' => $e->lookupStatus->value,
                    'reason' => $errorDetail,
                ],
                request: $request,
            );

            return new PatientLookupResult(
                status: $e->lookupStatus,
                message: $this->formatDetailedErrorMessage($e->lookupStatus, $errorDetail),
                errorDetail: $errorDetail,
            );
        } catch (Throwable $e) {
            $errorDetail = $this->hnSecurity->redactInlineSecrets($e->getMessage());

            Log::error('Unexpected error during patient lookup.', [
                'lookup_status' => LookupStatus::UnexpectedError->value,
                'hn_hash' => $hnHash,
                'user_id' => $actor->id,
                'exception_class' => $e::class,
                'error_detail' => $errorDetail,
            ]);

            $this->auditLog->record(
                action: 'patient_lookup_failed',
                user: $actor,
                outcome: 'failed',
                hnHash: $hnHash,
                afterData: [
                    'status' => LookupStatus::UnexpectedError->value,
                    'reason' => $errorDetail,
                ],
                request: $request,
            );

            return new PatientLookupResult(
                status: LookupStatus::UnexpectedError,
                message: $this->formatDetailedErrorMessage(LookupStatus::UnexpectedError, $errorDetail),
                errorDetail: $errorDetail,
            );
        }
    }

    private function resolvePatientData(string $normalizedHn, string $hnHash): ?PatientData
    {
        $cacheEnabled = (bool) config('patient_lookup.cache_enabled', false);
        if (! $cacheEnabled) {
            return $this->adapter->findByHn($normalizedHn);
        }

        // Cache key strictly uses hn_hash, never plaintext HN; payload is encrypted at rest
        $cacheKey = 'patient_lookup_enc:' . $hnHash;
        $cached = Cache::get($cacheKey);
        if (is_string($cached)) {
            $decoded = json_decode(Crypt::decryptString($cached), true);
            if (is_array($decoded)) {
                return PatientData::fromArray($decoded);
            }
        }

        $dto = $this->adapter->findByHn($normalizedHn);
        if ($dto !== null) {
            $ttl = max(5, (int) config('patient_lookup.cache_ttl_seconds', 30));
            Cache::put(
                $cacheKey,
                Crypt::encryptString((string) json_encode($dto->toArray())),
                now()->addSeconds($ttl)
            );
        }

        return $dto;
    }

    private function formatDetailedErrorMessage(LookupStatus $status, string $detail): string
    {
        $base = $status->userMessageTh();
        if ($detail === '') {
            return $base;
        }

        return match ($status) {
            LookupStatus::MappingError => "ไม่สามารถอ่านโครงสร้างข้อมูลผู้ป่วยจากระบบต้นทางได้: {$detail}",
            LookupStatus::ConnectionFailed => 'ไม่สามารถเชื่อมต่อระบบข้อมูลผู้ป่วยได้ในขณะนี้ กรุณาลองใหม่',
            LookupStatus::Timeout => 'ระบบข้อมูลผู้ป่วยตอบสนองล่าช้าเกินกำหนด กรุณาลองใหม่ในภายหลัง',
            LookupStatus::UnexpectedError => 'เกิดข้อผิดพลาดระหว่างค้นหาข้อมูลผู้ป่วย กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ',
            default => $base,
        };
    }
}
