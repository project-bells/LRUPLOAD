<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Integrations\SqlServer2014PatientLookup;
use App\Services\HnSecurityService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class SqlServer2014PatientLookupTest extends TestCase
{
    public function test_finds_patient_using_dbo_patient_pasid_uid_and_scalar_functions_with_parameter_binding(): void
    {
        config([
            'patient_lookup.enabled' => true,
            'patient_lookup.connection' => 'patient_sqlsrv',
            'database.connections.patient_sqlsrv.host' => '127.0.0.1',
            'database.connections.patient_sqlsrv.database' => 'HIS_TEST',
        ]);

        $mockConn = Mockery::mock(ConnectionInterface::class);

        $mockConn->shouldReceive('selectOne')
            ->once()
            ->with(
                'SELECT TOP 1 [UID] AS patient_uid, [PASID] AS pasid FROM [dbo].[Patient] WHERE [PASID] = ?',
                ['5621477']
            )
            ->andReturn((object) ['patient_uid' => 160461, 'pasid' => '5621477']);

        $mockConn->shouldReceive('selectOne')
            ->once()
            ->with(
                'SELECT dbo.fGetPatientNameWithTitle(?) AS patient_name',
                [160461]
            )
            ->andReturn((object) [
                'patient_name' => 'นางสุภาพร ใจดี',
            ]);

        $mockConn->shouldReceive('selectOne')
            ->once()
            ->with(
                'SELECT dbo.fGetAHCPatientNationalID(?) AS national_id',
                [160461]
            )
            ->andReturn((object) [
                'national_id' => '1234567890678',
            ]);

        DB::partialMock()
            ->shouldReceive('connection')
            ->once()
            ->with('patient_sqlsrv')
            ->andReturn($mockConn);

        $adapter = new SqlServer2014PatientLookup(new HnSecurityService());
        $dto = $adapter->findByHn('5621477');

        $this->assertNotNull($dto);
        $this->assertSame('5621477', $dto->hn);
        $this->assertSame('mother', $dto->patientType);
        $this->assertSame('นาง', $dto->titleName);
        $this->assertSame('สุภาพร', $dto->firstName);
        $this->assertSame('ใจดี', $dto->lastName);
        $this->assertSame('นางสุภาพร ใจดี', $dto->displayName());
        $this->assertSame('1-2345-XXXXX-67-8', $dto->nationalIdMasked);
    }

    public function test_parses_thai_newborn_and_spaced_names_cleanly(): void
    {
        $adapter = new SqlServer2014PatientLookup(new HnSecurityService());

        $childDto = $adapter->mapRowToDto([
            'hn' => '6900112',
            'patient_name' => 'ด.ญ. น้องเอกชล ทดสอบ',
            'national_id' => '1100200300405',
        ]);

        $this->assertSame('child', $childDto->patientType);
        $this->assertSame('ด.ญ.', $childDto->titleName);
        $this->assertSame('น้องเอกชล', $childDto->firstName);
        $this->assertSame('ทดสอบ', $childDto->lastName);
        $this->assertSame('ด.ญ.น้องเอกชล ทดสอบ', $childDto->displayName());
        $this->assertSame('1-1002-XXXXX-40-5', $childDto->nationalIdMasked);
    }
}
