<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Patient;
use App\Models\PatientRelationship;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\FileStorageService;
use App\Services\HnSecurityService;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PatientDeleteTest extends TestCase
{
    use RefreshDatabase;

    private Department $lrDepartment;
    private DocumentType $docType;
    private HnSecurityService $hnSecurity;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('tmp_uploads');
        Storage::fake('private_documents');

        $this->seed([
            RolesAndPermissionsSeeder::class,
            InitialDataSeeder::class,
        ]);

        $this->lrDepartment = Department::query()->where('code', 'LR')->firstOrFail();
        $this->docType = DocumentType::query()->where('code', 'DELIVERY')->firstOrFail();
        $this->hnSecurity = app(HnSecurityService::class);
    }

    private function createUserWithRole(string $roleName): User
    {
        $user = User::query()->create([
            'department_id' => $this->lrDepartment->id,
            'username' => $roleName . '_' . uniqid(),
            'name' => ucfirst($roleName) . ' User',
            'email' => uniqid() . '@aikchol.local',
            'password' => Hash::make('Password@2026!'),
            'is_active' => true,
        ]);

        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function createPatient(string $hn, string $type = 'mother'): Patient
    {
        return Patient::query()->create([
            'source_system' => 'his_sqlsrv_2014',
            'patient_type' => $type,
            'hn_encrypted' => $hn,
            'hn_hash' => $this->hnSecurity->hash($hn),
            'title_name_encrypted' => $type === 'mother' ? 'นาง' : 'ด.ช.',
            'first_name_encrypted' => 'ทดสอบ',
            'last_name_encrypted' => 'โรงพยาบาลเอกชล',
            'birth_date_encrypted' => '1995-01-01',
            'sex' => $type === 'mother' ? 'F' : 'M',
            'source_fetched_at' => now(),
        ]);
    }

    private function attachDocument(Patient $patient, User $author): Document
    {
        $pdfBytes = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n";
        $file = UploadedFile::fake()->createWithContent('sample_doc.pdf', $pdfBytes);

        $fileSvc = app(FileStorageService::class);
        $staged = $fileSvc->stageUpload($file, $author);

        return app(DocumentService::class)->createDocumentFromStagedUpload(
            primaryPatient: $patient,
            metadata: [
                'document_type_id' => $this->docType->id,
                'patient_owner_type' => $patient->patient_type->value,
                'title' => 'เอกสารทดสอบ ' . $patient->hn,
                'document_date' => '2026-11-20',
                'confidentiality_level' => 'confidential',
            ],
            stagedFile: $staged,
            actor: $author,
        );
    }

    public function test_supervisor_can_delete_patient_with_zero_documents(): void
    {
        $supervisor = $this->createUserWithRole('supervisor');
        $patient = $this->createPatient('66-000101');

        $this->actingAs($supervisor)
            ->delete(route('patients.destroy', $patient))
            ->assertRedirect(route('patients.index'))
            ->assertSessionHas('status');

        $this->assertModelMissing($patient);
    }

    public function test_administrator_can_delete_patient_with_zero_documents(): void
    {
        $admin = $this->createUserWithRole('administrator');
        $patient = $this->createPatient('66-000102');

        $this->actingAs($admin)
            ->delete(route('patients.destroy', $patient))
            ->assertRedirect(route('patients.index'))
            ->assertSessionHas('status');

        $this->assertModelMissing($patient);
    }

    public function test_author_can_delete_patient_with_zero_documents(): void
    {
        $author = $this->createUserWithRole('author');
        $patient = $this->createPatient('66-000103');

        $this->actingAs($author)
            ->delete(route('patients.destroy', $patient))
            ->assertRedirect(route('patients.index'))
            ->assertSessionHas('status');

        $this->assertModelMissing($patient);
    }

    public function test_viewer_cannot_delete_patient(): void
    {
        $viewer = $this->createUserWithRole('viewer');
        $patient = $this->createPatient('66-000103b');

        $this->actingAs($viewer)
            ->delete(route('patients.destroy', $patient))
            ->assertForbidden();

        $this->assertModelExists($patient);
    }

    public function test_guest_cannot_delete_patient(): void
    {
        $patient = $this->createPatient('66-000104');

        $this->delete(route('patients.destroy', $patient))
            ->assertRedirect(route('login'));

        $this->assertModelExists($patient);
    }

    public function test_cannot_delete_patient_with_active_documents(): void
    {
        $supervisor = $this->createUserWithRole('supervisor');
        $author = $this->createUserWithRole('author');
        $patient = $this->createPatient('66-000105');

        $this->attachDocument($patient, $author);
        $this->assertSame(1, $patient->documents()->count());

        $this->actingAs($supervisor)
            ->delete(route('patients.destroy', $patient))
            ->assertForbidden();

        $this->assertModelExists($patient);
    }

    public function test_cannot_delete_patient_with_soft_deleted_documents(): void
    {
        $supervisor = $this->createUserWithRole('supervisor');
        $author = $this->createUserWithRole('author');
        $patient = $this->createPatient('66-000106');

        $doc = $this->attachDocument($patient, $author);
        $doc->delete(); // Soft delete into trash

        $this->assertSame(0, $patient->documents()->count());
        $this->assertSame(1, $patient->documents()->withTrashed()->count());

        // Policy must reject because patient has 1 document in trash (strictly 0 required)
        $this->actingAs($supervisor)
            ->delete(route('patients.destroy', $patient))
            ->assertForbidden();

        $this->assertModelExists($patient);
    }

    public function test_deleting_patient_cleans_up_mother_child_relationship(): void
    {
        $supervisor = $this->createUserWithRole('supervisor');
        $mother = $this->createPatient('66-000107', 'mother');
        $child = $this->createPatient('66-000108', 'child');

        PatientRelationship::query()->create([
            'mother_patient_id' => $mother->id,
            'child_patient_id' => $child->id,
            'relationship_source' => 'manual_verified',
            'verified_by' => $supervisor->id,
            'verified_at' => now(),
        ]);

        $this->assertDatabaseHas('patient_relationships', [
            'mother_patient_id' => $mother->id,
            'child_patient_id' => $child->id,
        ]);

        // Supervisor deletes child (0 documents)
        $this->actingAs($supervisor)
            ->delete(route('patients.destroy', $child))
            ->assertRedirect(route('patients.index'));

        $this->assertModelMissing($child);
        $this->assertModelExists($mother);
        $this->assertDatabaseMissing('patient_relationships', [
            'child_patient_id' => $child->id,
        ]);
    }

    public function test_audit_log_records_patient_deletion_without_plaintext_hn(): void
    {
        $supervisor = $this->createUserWithRole('supervisor');
        $patient = $this->createPatient('66-000109');
        $rawHn = $patient->hn;
        $hnHash = $patient->hn_hash;

        $this->actingAs($supervisor)
            ->delete(route('patients.destroy', $patient))
            ->assertRedirect(route('patients.index'));

        /** @var AuditLog $log */
        $log = AuditLog::query()
            ->where('action', 'delete_patient')
            ->where('hn_hash', $hnHash)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('success', $log->outcome);
        $this->assertSame($supervisor->id, $log->user_id);

        // Security check: Plaintext HN must NEVER appear in audit log fields
        $this->assertStringNotContainsString($rawHn, json_encode($log->before_data));
        $this->assertStringNotContainsString($rawHn, json_encode($log->after_data));
        $this->assertNull($log->getRawOriginal('hn_hash') !== null && str_contains($log->hn_hash, $rawHn) ? true : null);
    }

    public function test_ui_renders_delete_button_only_when_zero_documents(): void
    {
        $supervisor = $this->createUserWithRole('supervisor');
        $author = $this->createUserWithRole('author');

        $emptyPatient = $this->createPatient('66-000110');
        $patientWithDoc = $this->createPatient('66-000111');
        $this->attachDocument($patientWithDoc, $author);

        // On index page:
        $indexResponse = $this->actingAs($supervisor)->get(route('patients.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('action="' . route('patients.destroy', $emptyPatient) . '"', false);
        $indexResponse->assertDontSee('action="' . route('patients.destroy', $patientWithDoc) . '"', false);

        // On show page:
        $emptyShowResponse = $this->actingAs($supervisor)->get(route('patients.show', $emptyPatient));
        $emptyShowResponse->assertOk();
        $emptyShowResponse->assertSee('action="' . route('patients.destroy', $emptyPatient) . '"', false);
        $emptyShowResponse->assertSee('ลบข้อมูลผู้ป่วย (0 ฉบับ)');

        $docShowResponse = $this->actingAs($supervisor)->get(route('patients.show', $patientWithDoc));
        $docShowResponse->assertOk();
        $docShowResponse->assertDontSee('action="' . route('patients.destroy', $patientWithDoc) . '"', false);
        $docShowResponse->assertDontSee('ลบข้อมูลผู้ป่วย (0 ฉบับ)');
    }
}
