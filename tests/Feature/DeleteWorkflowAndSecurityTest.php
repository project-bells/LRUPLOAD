<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\PatientLookupInterface;
use App\DTOs\PatientData;
use App\Models\AuditLog;
use App\Models\DeleteRequest;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Patient;
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
use Illuminate\Support\Facades\URL;
use LogicException;
use Mockery;
use Tests\TestCase;

class DeleteWorkflowAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Department $lrDepartment;
    private Department $otherDepartment;
    private DocumentType $docType;
    private Patient $patient;

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
        $this->otherDepartment = Department::query()->create([
            'code' => 'OPD',
            'name_th' => 'แผนกผู้ป่วยนอก',
            'name_en' => 'OPD',
            'is_active' => true,
        ]);

        $this->docType = DocumentType::query()->where('code', 'DELIVERY')->firstOrFail();

        $hnSec = app(HnSecurityService::class);
        $this->patient = Patient::query()->create([
            'source_system' => 'his_sqlsrv_2014',
            'patient_type' => 'mother',
            'hn_encrypted' => '66-007777',
            'hn_hash' => $hnSec->hash('66-007777'),
            'title_name_encrypted' => 'นาง',
            'first_name_encrypted' => 'พรพิมล',
            'last_name_encrypted' => 'ทดสอบความปลอดภัย',
            'birth_date_encrypted' => '1992-08-20',
            'sex' => 'F',
            'source_fetched_at' => now(),
        ]);
    }

    private function createUserWithRole(?string $roleName, ?Department $dept = null): User
    {
        $user = User::query()->create([
            'department_id' => ($dept ?? $this->lrDepartment)->id,
            'username' => ($roleName ?? 'norole') . '_' . uniqid(),
            'name' => ucfirst($roleName ?? 'NoRole') . ' User',
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

    private function createDocument(User $author): Document
    {
        $pdfBytes = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n";
        $file = UploadedFile::fake()->createWithContent('labor_doc.pdf', $pdfBytes);

        $fileSvc = app(FileStorageService::class);
        $staged = $fileSvc->stageUpload($file, $author);

        return app(DocumentService::class)->createDocumentFromStagedUpload(
            primaryPatient: $this->patient,
            metadata: [
                'document_type_id' => $this->docType->id,
                'patient_owner_type' => 'mother',
                'title' => 'บันทึกการคลอดทดสอบ',
                'document_date' => '2026-11-18',
                'confidentiality_level' => 'confidential',
            ],
            stagedFile: $staged,
            actor: $author,
        );
    }

    /** Test #14, #17, #18, #19, #20: Author cannot self-delete; Supervisor approves Soft Delete without removing file; Restore works; Audit logs recorded & immutable */
    public function test_delete_request_soft_delete_preserves_files_and_supports_restore(): void
    {
        $author = $this->createUserWithRole('author');
        $supervisor = $this->createUserWithRole('supervisor');
        $document = $this->createDocument($author);
        $document->load('currentRevision.storedFile');
        $filePath = $document->currentRevision->storedFile->storage_path;

        // Test #14: Author cannot directly soft delete
        $this->actingAs($author)
            ->post(route('documents.soft-delete', $document), ['delete_reason' => 'ลองลบเอง'])
            ->assertForbidden();

        // Author submits delete request
        $this->actingAs($author)
            ->post(route('documents.delete-request.store', $document), [
                'request_reason' => 'เอกสารอัปโหลดซ้ำกับฉบับก่อนหน้า',
            ])
            ->assertRedirect(route('documents.show', $document));

        $deleteRequest = DeleteRequest::query()->firstOrFail();
        $this->assertDatabaseHas('audit_logs', ['action' => 'request_delete', 'document_id' => $document->id]);

        // Test #14 (cont.): Author cannot approve delete request
        $this->actingAs($author)
            ->post(route('delete-requests.review', $deleteRequest), ['decision' => 'approve'])
            ->assertForbidden();

        // Test #17: Supervisor approves Soft Delete
        $this->actingAs($supervisor)
            ->post(route('delete-requests.review', $deleteRequest), [
                'decision' => 'approve',
                'review_note' => 'ตรวจสอบแล้ว อนุมัติย้ายเข้าถังเอกสาร',
            ])
            ->assertRedirect(route('delete-requests.index'));

        $this->assertSoftDeleted('documents', ['id' => $document->id]);

        // Test #18: Soft Delete MUST NOT remove physical file, revision, patient, or audit logs
        Storage::disk('private_documents')->assertExists($filePath);
        $this->assertDatabaseHas('document_revisions', ['document_id' => $document->id]);
        $this->assertDatabaseHas('patients', ['id' => $this->patient->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'approve_delete', 'document_id' => $document->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'soft_delete', 'document_id' => $document->id]);

        // Test #19: Supervisor restores document
        $this->actingAs($supervisor)
            ->post(route('documents.restore', $document->id))
            ->assertRedirect(route('documents.show', $document));

        $this->assertNotSoftDeleted('documents', ['id' => $document->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'restore_document', 'document_id' => $document->id]);

        // Test #20: AuditLog is strictly append-only (cannot update or delete)
        $anyLog = AuditLog::query()->firstOrFail();
        $this->expectException(LogicException::class);
        $anyLog->delete();
    }

    /** Test #21 & #22: Preview checks permission AND prevents cross-department IDOR access */
    public function test_preview_checks_permission_and_prevents_unauthorized_patient_file_access(): void
    {
        $author = $this->createUserWithRole('author', $this->lrDepartment);
        $document = $this->createDocument($author);
        $revision = $document->currentRevision()->firstOrFail();

        $signedStreamUrl = URL::temporarySignedRoute(
            'documents.revisions.stream',
            now()->addMinutes(5),
            ['document' => $document->id, 'revision' => $revision->id]
        );

        // Test #21: Authenticated user without documents.preview cannot preview or stream
        $noPermUser = $this->createUserWithRole(null, $this->lrDepartment);
        $this->actingAs($noPermUser)
            ->get(route('documents.revisions.preview', [$document, $revision]))
            ->assertForbidden();
        $this->actingAs($noPermUser)
            ->get($signedStreamUrl)
            ->assertForbidden();

        // Test #22: User from another department (OPD) cannot open LR patient document or file stream (Anti-IDOR)
        $opdViewer = $this->createUserWithRole('viewer', $this->otherDepartment);
        $this->actingAs($opdViewer)
            ->get(route('documents.show', $document))
            ->assertForbidden();
        $this->actingAs($opdViewer)
            ->get(route('documents.revisions.preview', [$document, $revision]))
            ->assertForbidden();
        $this->actingAs($opdViewer)
            ->get($signedStreamUrl)
            ->assertForbidden();

        // Authorized LR Viewer CAN preview and stream (and logs preview_document)
        $lrViewer = $this->createUserWithRole('viewer', $this->lrDepartment);
        $this->actingAs($lrViewer)
            ->get($signedStreamUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'preview_document',
            'user_id' => $lrViewer->id,
            'document_id' => $document->id,
            'revision_id' => $revision->id,
        ]);
    }

    /** Test #23: Plaintext HN and patient name NEVER appear in Audit Logs */
    public function test_plaintext_hn_and_patient_name_never_appear_in_audit_logs(): void
    {
        $author = $this->createUserWithRole('author');

        $dto = new PatientData(
            hn: '66-007777',
            patientType: 'mother',
            titleName: 'นาง',
            firstName: 'พรพิมล',
            lastName: 'ทดสอบความปลอดภัย',
            birthDate: '1992-08-20',
            sex: 'F',
            nationalIdMasked: '1-2345-XXXXX-67-8',
            admissionNumber: 'AN-99001',
            ward: 'Labor Room',
            motherHn: null,
            childHn: null,
            sourceUpdatedAt: null,
        );

        $mock = Mockery::mock(PatientLookupInterface::class);
        $mock->shouldReceive('findByHn')->once()->with('66-007777')->andReturn($dto);
        $this->app->instance(PatientLookupInterface::class, $mock);

        $this->actingAs($author)->post(route('patients.lookup'), ['hn' => '66-007777'])->assertOk();
        $this->createDocument($author);

        $allLogs = AuditLog::query()->get();
        $this->assertGreaterThan(0, $allLogs->count());

        $serializedLogs = json_encode($allLogs->toArray(), JSON_UNESCAPED_UNICODE);
        $this->assertIsString($serializedLogs);
        $this->assertStringNotContainsString('66-007777', $serializedLogs);
        $this->assertStringNotContainsString('พรพิมล', $serializedLogs);
        $this->assertStringNotContainsString('ทดสอบความปลอดภัย', $serializedLogs);
    }
}
