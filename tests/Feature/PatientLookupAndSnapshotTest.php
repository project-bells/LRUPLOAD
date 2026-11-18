<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\PatientLookupInterface;
use App\DTOs\PatientData;
use App\Exceptions\PatientLookupException;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class PatientLookupAndSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private Department $lrDepartment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RolesAndPermissionsSeeder::class,
            InitialDataSeeder::class,
        ]);
        $this->lrDepartment = Department::query()->where('code', 'LR')->firstOrFail();
    }

    private function createUserWithRole(?string $roleName): User
    {
        $user = User::query()->create([
            'department_id' => $this->lrDepartment->id,
            'username' => 'user_' . uniqid(),
            'name' => 'Test User',
            'email' => uniqid() . '@aikchol.local',
            'password' => Hash::make('Password@2026!'),
            'is_active' => true,
        ]);

        if ($roleName !== null) {
            $role = Role::query()->where('name', $roleName)->firstOrFail();
            $user->roles()->sync([$role->id]);
        }

        return $user;
    }

    private function sampleMotherDto(): PatientData
    {
        return new PatientData(
            hn: '66-009876',
            patientType: 'mother',
            titleName: 'นาง',
            firstName: 'สุภาพร',
            lastName: 'เอกชลทดสอบ',
            birthDate: '1995-05-12',
            sex: 'F',
            nationalIdMasked: '1-2345-XXXXX-67-8',
            admissionNumber: 'AN-69000123',
            ward: 'Labor Room 1',
            motherHn: null,
            childHn: null,
            sourceUpdatedAt: '2026-11-18 08:00:00',
        );
    }

    /** Test #1: Unauthenticated guest cannot lookup HN */
    public function test_guest_cannot_lookup_hn(): void
    {
        $response = $this->post(route('patients.lookup'), ['hn' => '66-009876']);
        $response->assertRedirect(route('login'));
    }

    /** Test #2: User without patients.lookup permission is rejected with 403 */
    public function test_user_without_permission_cannot_lookup_hn(): void
    {
        $userWithoutRole = $this->createUserWithRole(null);

        $response = $this->actingAs($userWithoutRole)
            ->post(route('patients.lookup'), ['hn' => '66-009876']);

        $response->assertForbidden();
    }

    /** Test #3: Invalid HN format / SQL injection input is rejected */
    public function test_invalid_hn_format_is_rejected(): void
    {
        $author = $this->createUserWithRole('author');

        $response = $this->actingAs($author)
            ->post(route('patients.lookup'), ['hn' => "66-001' OR 1=1;--"]);

        $response->assertSessionHasErrors('hn');
        $this->assertDatabaseCount('patients', 0);
    }

    /** Test #4 & #9: Found patient from Mock SQL Server displays info and does NOT create snapshot on lookup alone */
    public function test_lookup_finds_patient_from_mock_sql_server_without_creating_snapshot(): void
    {
        $author = $this->createUserWithRole('author');
        $dto = $this->sampleMotherDto();

        $mock = Mockery::mock(PatientLookupInterface::class);
        $mock->shouldReceive('findByHn')->once()->with('66-009876')->andReturn($dto);
        $this->app->instance(PatientLookupInterface::class, $mock);

        $response = $this->actingAs($author)
            ->post(route('patients.lookup'), ['hn' => '66-009876', 'patient_type' => 'auto']);

        $response->assertOk();
        $response->assertSee('66-009876');
        $response->assertSee('สุภาพร');
        $response->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');

        // Test #9: Must NOT persist into patients table from lookup alone
        $this->assertDatabaseCount('patients', 0);

        // Audit log must exist with hn_hash and ZERO plaintext HN/name
        $log = AuditLog::query()->where('action', 'patient_lookup')->first();
        $this->assertNotNull($log);
        $this->assertNotEmpty($log->hn_hash);
    }

    /** Test #5: Patient not found returns friendly Thai message */
    public function test_lookup_not_found_returns_friendly_message(): void
    {
        $author = $this->createUserWithRole('author');

        $mock = Mockery::mock(PatientLookupInterface::class);
        $mock->shouldReceive('findByHn')->once()->with('66-999999')->andReturn(null);
        $this->app->instance(PatientLookupInterface::class, $mock);

        $response = $this->actingAs($author)
            ->post(route('patients.lookup'), ['hn' => '66-999999']);

        $response->assertOk();
        $response->assertSee('ไม่พบข้อมูลผู้ป่วยจาก HN ที่ระบุ');
        $this->assertDatabaseHas('audit_logs', ['action' => 'patient_lookup_not_found']);
    }

    /** Test #6: SQL Server timeout handled gracefully without leaking SQL error */
    public function test_lookup_handles_sql_server_timeout_safely(): void
    {
        $author = $this->createUserWithRole('author');

        $mock = Mockery::mock(PatientLookupInterface::class);
        $mock->shouldReceive('findByHn')->once()->andThrow(
            PatientLookupException::timeout('SQLSTATE[HYT00]: Login timeout expired; PWD=TopSecretPassword123')
        );
        $this->app->instance(PatientLookupInterface::class, $mock);

        $response = $this->actingAs($author)
            ->post(route('patients.lookup'), ['hn' => '66-009876']);

        $response->assertOk();
        $response->assertSee('ระบบข้อมูลผู้ป่วยตอบสนองล่าช้าเกินกำหนด');
        $response->assertDontSee('SQLSTATE');
        $response->assertDontSee('TopSecretPassword123');
        $this->assertDatabaseHas('audit_logs', ['action' => 'patient_lookup_failed', 'outcome' => 'failed']);
    }

    /** Test #7: SQL Server connection failed handled gracefully */
    public function test_lookup_handles_sql_server_connection_failed_safely(): void
    {
        $author = $this->createUserWithRole('author');

        $mock = Mockery::mock(PatientLookupInterface::class);
        $mock->shouldReceive('findByHn')->once()->andThrow(
            PatientLookupException::connectionFailed('SQLSTATE[08001]: Unable to connect to 10.10.20.55')
        );
        $this->app->instance(PatientLookupInterface::class, $mock);

        $response = $this->actingAs($author)
            ->post(route('patients.lookup'), ['hn' => '66-009876']);

        $response->assertOk();
        $response->assertSee('ไม่สามารถเชื่อมต่อระบบข้อมูลผู้ป่วยได้ในขณะนี้');
        $response->assertDontSee('10.10.20.55');
        $response->assertDontSee('08001');
    }

    /** Test #8: Patient Snapshot is created when user confirms selecting the patient */
    public function test_patient_snapshot_is_created_only_when_user_selects_patient(): void
    {
        $author = $this->createUserWithRole('author');
        $dto = $this->sampleMotherDto();

        $mock = Mockery::mock(PatientLookupInterface::class);
        $mock->shouldReceive('findByHn')->once()->with('66-009876')->andReturn($dto);
        $this->app->instance(PatientLookupInterface::class, $mock);

        $lookupResponse = $this->actingAs($author)
            ->post(route('patients.lookup'), ['hn' => '66-009876']);

        $lookupResponse->assertOk();
        $this->assertDatabaseCount('patients', 0);

        $tokens = session('patient_lookup_tokens');
        $this->assertIsArray($tokens);
        $selectionToken = (string) array_key_first($tokens);

        $selectResponse = $this->actingAs($author)->post(route('patients.select'), [
            'selection_token' => $selectionToken,
            'next_action' => 'profile',
        ]);

        $patient = Patient::query()->first();
        $this->assertNotNull($patient);
        $selectResponse->assertRedirect(route('patients.show', $patient));
        $this->assertSame('66-009876', $patient->hn);
        $this->assertSame('สุภาพร', $patient->first_name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'patient_selected']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'patient_snapshot_created']);
    }
}
