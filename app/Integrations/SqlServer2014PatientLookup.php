<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Contracts\PatientLookupInterface;
use App\DTOs\PatientData;
use App\Exceptions\PatientLookupException;
use App\Services\HnSecurityService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;
use Throwable;

final class SqlServer2014PatientLookup implements PatientLookupInterface
{
    /**
     * Common Thai & English title prefixes ordered longest-first to avoid partial matches.
     *
     * @var array<int, string>
     */
    private const TITLE_PREFIXES = [
        'ทารกเพศหญิง',
        'ทารกเพศชาย',
        'ทารกหญิง',
        'ทารกชาย',
        'เด็กหญิง',
        'เด็กชาย',
        'นางสาว',
        'Newborn',
        'Master',
        'Miss',
        'Mrs.',
        'Baby',
        'ทารก',
        'น.ส.',
        'ด.ญ.',
        'ด.ช.',
        'ทพญ.',
        'Ms.',
        'Mr.',
        'Mrs',
        'Dr.',
        'นาง',
        'นาย',
        'พญ.',
        'นพ.',
        'ทพ.',
    ];

    /**
     * Title prefixes that indicate a newborn / child patient.
     *
     * @var array<int, string>
     */
    private const CHILD_PREFIXES = [
        'ทารกเพศหญิง',
        'ทารกเพศชาย',
        'ทารกหญิง',
        'ทารกชาย',
        'เด็กหญิง',
        'เด็กชาย',
        'ทารก',
        'ด.ญ.',
        'ด.ช.',
        'Newborn',
        'Baby',
        'Master',
    ];

    public function __construct(
        private readonly HnSecurityService $hnSecurity,
    ) {
    }

    public function findByHn(string $hn): ?PatientData
    {
        if (! (bool) config('patient_lookup.enabled', false)) {
            throw PatientLookupException::connectionFailed('PATIENT_DB_ENABLED is false');
        }

        $connectionName = (string) config('patient_lookup.connection', 'patient_sqlsrv');
        $host = (string) config("database.connections.{$connectionName}.host", '');
        $database = (string) config("database.connections.{$connectionName}.database", '');

        if ($host === '' || $database === '') {
            throw PatientLookupException::connectionFailed('SQL Server 2014 connection host/database is not configured');
        }

        try {
            $connection = DB::connection($connectionName);

            // Step 1: Lookup patient UID and core fields from [dbo].[Patient] by PASID (HN) using strict Parameter Binding
            $patientRow = $connection->selectOne(
                'SELECT TOP 1 [UID] AS patient_uid, [PASID] AS pasid, [Forename] AS forename, [MiddleName] AS middle_name, [Surname] AS surname, [BirthDttm] AS birth_date, [StatusFlag] AS status_flag, [TITLEUID] AS title_uid FROM [dbo].[Patient] WHERE [PASID] = ? ORDER BY CASE WHEN [StatusFlag] = \'A\' THEN 0 ELSE 1 END, [UID] DESC',
                [$hn]
            );

            if ($patientRow === null) {
                return null;
            }

            $patientArr = (array) $patientRow;
            $patientUid = (int) ($patientArr['patient_uid'] ?? $patientArr['UID'] ?? 0);

            if ($patientUid <= 0) {
                throw PatientLookupException::mappingError("รหัสประจำตัวผู้ป่วย (UID) ในตาราง [dbo].[Patient] มีค่าไม่ถูกต้องหรือไม่พบ (HN: {$hn})");
            }

            // Step 2a: Fetch full patient name with title via hospital scalar function (Parameter Binding)
            $fullName = null;
            try {
                $nameRow = $connection->selectOne(
                    'SELECT dbo.fGetPatientNameWithTitle(?) AS patient_name',
                    [$patientUid]
                );
                if ($nameRow !== null) {
                    $nameArr = (array) $nameRow;
                    $fullName = trim((string) ($nameArr['patient_name'] ?? $nameArr['PatientName'] ?? ''));
                }
            } catch (Throwable) {
                // Scalar function might fail or lack permission; fallback to dbo.Patient columns below
                $fullName = null;
            }

            // Step 2b: Fallback to dbo.Patient columns (Forename, MiddleName, Surname, TITLEUID) if scalar function returned empty
            // (e.g. when patient has StatusFlag != 'A' or scalar function is unpermitted)
            if ($fullName === null || $fullName === '') {
                $forename = trim((string) ($patientArr['forename'] ?? ''));
                $middleName = trim((string) ($patientArr['middle_name'] ?? ''));
                $surname = trim((string) ($patientArr['surname'] ?? ''));
                $titleUid = (int) ($patientArr['title_uid'] ?? 0);

                $titleDesc = '';
                if ($titleUid > 0) {
                    try {
                        $titleRow = $connection->selectOne('SELECT dbo.fGetRfValDescription(?) AS title_desc', [$titleUid]);
                        if ($titleRow !== null) {
                            $titleArr = (array) $titleRow;
                            $titleDesc = trim((string) ($titleArr['title_desc'] ?? ''));
                        }
                    } catch (Throwable) {
                        $titleDesc = '';
                    }
                }

                $combinedName = trim(($titleDesc !== '' ? "{$titleDesc} " : '') . "{$forename} " . ($middleName !== '' ? "{$middleName} " : '') . "{$surname}");
                if ($combinedName !== '') {
                    $fullName = $combinedName;
                }
            }

            if ($fullName === null || $fullName === '') {
                $statusFlag = (string) ($patientArr['status_flag'] ?? '');
                throw PatientLookupException::mappingError("ไม่พบข้อมูลชื่อ-นามสกุลของผู้ป่วยในระบบ HIS (HN: {$hn}, UID: {$patientUid}, StatusFlag: '{$statusFlag}') เนื่องจากฟังก์ชัน dbo.fGetPatientNameWithTitle คืนค่าว่าง และคอลัมน์ Forename/Surname ใน [dbo].[Patient] ว่างเปล่า");
            }

            // Step 2c: Fetch optional National ID (masked immediately in mapRowToDto).
            // Gracefully degrades to null if the read-only SQL login does not have EXECUTE on fGetAHCPatientNationalID.
            $rawNationalId = null;
            try {
                $idRow = $connection->selectOne(
                    'SELECT dbo.fGetAHCPatientNationalID(?) AS national_id',
                    [$patientUid]
                );
                if ($idRow !== null) {
                    $idArr = (array) $idRow;
                    $rawNationalId = $idArr['national_id'] ?? $idArr['IDCARD'] ?? null;
                }
            } catch (QueryException|PDOException) {
                $rawNationalId = null;
            }

            $birthDate = null;
            if (! empty($patientArr['birth_date'])) {
                try {
                    $birthDate = \Carbon\Carbon::parse((string) $patientArr['birth_date'])->format('Y-m-d');
                } catch (Throwable) {
                    $birthDate = (string) $patientArr['birth_date'];
                }
            }

            return $this->mapRowToDto([
                'hn' => $patientArr['pasid'] ?? $patientArr['PASID'] ?? $hn,
                'patient_name' => $fullName,
                'national_id' => $rawNationalId,
                'birth_date' => $birthDate,
            ]);
        } catch (PatientLookupException $e) {
            throw $e;
        } catch (QueryException|PDOException $e) {
            throw $this->classifyDatabaseException($e);
        } catch (Throwable $e) {
            throw PatientLookupException::unexpected(
                $this->hnSecurity->redactInlineSecrets($e->getMessage()),
                $e
            );
        }
    }

    /**
     * Map raw SQL Server 2014 result columns to the normalized PatientData DTO.
     *
     * @param array<string, mixed> $row
     */
    public function mapRowToDto(array $row): PatientData
    {
        $hn = $this->ensureUtf8(trim((string) ($row['hn'] ?? '')));
        $fullName = $this->ensureUtf8(trim((string) ($row['patient_name'] ?? '')));

        if ($hn === '' || $fullName === '') {
            throw PatientLookupException::mappingError('Required mapped fields (hn, patient_name) are missing');
        }

        [$titleName, $firstName, $lastName, $inferredType] = $this->parsePatientNameWithTitle($fullName);

        $rawType = isset($row['patient_type']) ? strtolower(trim((string) $row['patient_type'])) : '';
        $patientType = in_array($rawType, ['mother', 'child'], true) ? $rawType : $inferredType;

        $rawNationalId = isset($row['national_id']) ? $this->ensureUtf8(trim((string) $row['national_id'])) : null;

        return new PatientData(
            hn: $this->hnSecurity->normalize($hn),
            patientType: $patientType,
            titleName: $titleName,
            firstName: $firstName,
            lastName: $lastName,
            birthDate: isset($row['birth_date']) ? trim((string) $row['birth_date']) : null,
            sex: isset($row['sex']) ? trim((string) $row['sex']) : null,
            nationalIdMasked: $this->hnSecurity->maskNationalId($rawNationalId),
            admissionNumber: isset($row['admission_number']) ? trim((string) $row['admission_number']) : null,
            ward: isset($row['ward']) ? trim((string) $row['ward']) : null,
            motherHn: isset($row['mother_hn']) && trim((string) $row['mother_hn']) !== ''
                ? $this->hnSecurity->normalize((string) $row['mother_hn'])
                : null,
            childHn: isset($row['child_hn']) && trim((string) $row['child_hn']) !== ''
                ? $this->hnSecurity->normalize((string) $row['child_hn'])
                : null,
            sourceUpdatedAt: isset($row['source_updated_at']) ? trim((string) $row['source_updated_at']) : null,
        );
    }

    /**
     * Split full patient name from dbo.fGetPatientNameWithTitle() into [titleName, firstName, lastName, inferredType].
     *
     * @return array{0: ?string, 1: string, 2: string, 3: string}
     */
    public function parsePatientNameWithTitle(string $fullName): array
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($fullName)) ?? trim($fullName);
        $titleName = null;
        $remainder = $normalized;
        $inferredType = 'mother';

        foreach (self::TITLE_PREFIXES as $prefix) {
            if (mb_stripos($normalized, $prefix, 0, 'UTF-8') === 0) {
                $prefixLen = mb_strlen($prefix, 'UTF-8');
                $afterPrefix = mb_substr($normalized, $prefixLen, null, 'UTF-8');

                // For ASCII prefixes like Mr./Mrs./Baby, require space or dot boundary
                if (preg_match('/^[A-Za-z.]+$/', $prefix) === 1 && $afterPrefix !== '' && ! str_starts_with($afterPrefix, ' ')) {
                    continue;
                }

                $titleName = mb_substr($normalized, 0, $prefixLen, 'UTF-8');
                $remainder = ltrim($afterPrefix);

                foreach (self::CHILD_PREFIXES as $childPrefix) {
                    if (mb_strtolower($titleName, 'UTF-8') === mb_strtolower($childPrefix, 'UTF-8')) {
                        $inferredType = 'child';
                        break;
                    }
                }
                break;
            }
        }

        if ($remainder === '') {
            return [$titleName, $normalized, '', $inferredType];
        }

        $parts = preg_split('/\s+/u', $remainder, 2) ?: [$remainder];
        $firstName = trim((string) ($parts[0] ?? ''));
        $lastName = trim((string) ($parts[1] ?? ''));

        if ($firstName === '') {
            $firstName = $normalized;
        }

        return [$titleName, $firstName, $lastName, $inferredType];
    }

    private function ensureUtf8(string $value): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = @iconv('Windows-874', 'UTF-8//IGNORE', $value);

        return is_string($converted) && $converted !== '' ? $converted : $value;
    }

    private function classifyDatabaseException(Throwable $e): PatientLookupException
    {
        // Use underlying PDOException message when available so Laravel's QueryException
        // never logs bound HN parameters, raw SQL text, or host/database details.
        $rawMessage = ($e instanceof QueryException && $e->getPrevious() !== null)
            ? $e->getPrevious()->getMessage()
            : $e->getMessage();

        // Strip Laravel's "(Connection: ..., SQL: ...)" suffix if present
        $stripped = (string) preg_replace('/\s*\(Connection:.*$/s', '', $rawMessage);
        $safeMessage = $this->hnSecurity->redactInlineSecrets(trim($stripped));
        $message = strtolower($safeMessage);

        if (
            str_contains($message, 'timeout') ||
            str_contains($message, 'timed out') ||
            str_contains($message, 'hyt00') ||
            str_contains($message, 'hyt01')
        ) {
            return PatientLookupException::timeout($safeMessage, $e);
        }

        if (
            str_contains($message, 'could not find driver') ||
            str_contains($message, 'login failed') ||
            str_contains($message, 'unable to connect') ||
            str_contains($message, 'connection refused') ||
            str_contains($message, 'certificate') ||
            str_contains($message, 'ssl provider') ||
            str_contains($message, 'network') ||
            str_contains($message, '08001') ||
            str_contains($message, '08s01')
        ) {
            return PatientLookupException::connectionFailed($safeMessage, $e);
        }

        return PatientLookupException::unexpected($safeMessage, $e);
    }
}
