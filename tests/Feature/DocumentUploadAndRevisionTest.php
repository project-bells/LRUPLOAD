<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RevisionStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\DocumentType;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\HnSecurityService;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadAndRevisionTest extends TestCase
{
    use RefreshDatabase;

    private Department $lrDepartment;
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
        $this->docType = DocumentType::query()->where('code', 'CONSENT')->firstOrFail();

        $hnSec = app(HnSecurityService::class);
        $this->patient = Patient::query()->create([
            'source_system' => 'his_sqlsrv_2014',
            'patient_type' => 'mother',
            'hn_encrypted' => '66-005555',
            'hn_hash' => $hnSec->hash('66-005555'),
            'title_name_encrypted' => 'นาง',
            'first_name_encrypted' => 'กนกวรรณ',
            'last_name_encrypted' => 'สุขใจ',
            'birth_date_encrypted' => '1994-03-15',
            'sex' => 'F',
            'source_fetched_at' => now(),
        ]);
    }

    private function createUserWithRole(string $roleName, ?Department $dept = null): User
    {
        $user = User::query()->create([
            'department_id' => ($dept ?? $this->lrDepartment)->id,
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

    private function fakeValidPdf(string $filename = 'consent.pdf', string $marker = 'V1'): UploadedFile
    {
        $pdfBytes = "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            . "2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\n"
            . "% {$marker}\n%%EOF\n";

        return UploadedFile::fake()->createWithContent($filename, $pdfBytes);
    }

    /** Test #10 & #13: Author can upload a valid PDF, review it, and confirm creating Rev 1 in private storage */
    public function test_author_can_upload_valid_pdf_and_confirm_document(): void
    {
        $author = $this->createUserWithRole('author');

        $stageResponse = $this->actingAs($author)->post(route('documents.stage'), [
            'patient_id' => $this->patient->id,
            'document_type_id' => $this->docType->id,
            'patient_owner_type' => 'mother',
            'title' => 'ใบยินยอมการคลอด',
            'document_date' => '2026-11-18',
            'confidentiality_level' => 'confidential',
            'file' => $this->fakeValidPdf('consent_v1.pdf', 'VERSION_ONE'),
        ]);

        $staged = session('staged_documents');
        $this->assertIsArray($staged);
        $token = (string) array_key_first($staged);
        $stageResponse->assertRedirect(route('documents.review', ['token' => $token]));

        // Document is NOT yet committed until user clicks Confirm
        $this->assertDatabaseCount('documents', 0);

        $reviewResponse = $this->actingAs($author)->get(route('documents.review', ['token' => $token]));
        $reviewResponse->assertOk();
        $reviewResponse->assertSee('ตรวจสอบความถูกต้องก่อนยืนยันบันทึก');

        $confirmResponse = $this->actingAs($author)->post(route('documents.confirm'), [
            'staging_token' => $token,
            'idempotency_token' => $staged[$token]['idempotency_token'],
        ]);

        $document = Document::query()->with(['currentRevision.storedFile', 'patientSnapshots'])->first();
        $this->assertNotNull($document);
        $confirmResponse->assertRedirect(route('documents.show', $document));

        $this->assertSame(1, $document->currentRevision->revision_number);
        $this->assertSame(RevisionStatus::Current, $document->currentRevision->status);
        $this->assertCount(1, $document->patientSnapshots);

        // File must exist in private_documents disk and temp file must be removed
        Storage::disk('private_documents')->assertExists($document->currentRevision->storedFile->storage_path);
        Storage::disk('tmp_uploads')->assertMissing($staged[$token]['file']['tmp_path']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'create_document',
            'document_id' => $document->id,
        ]);
    }

    /** Test #11: Malicious / executable files disguised as PDF or double-extension are rejected */
    public function test_malicious_executable_and_disguised_files_are_rejected(): void
    {
        $author = $this->createUserWithRole('author');

        // 1. PHP script renamed to .pdf
        $phpDisguised = UploadedFile::fake()->createWithContent('shell.pdf', "<?php system('whoami'); ?>");
        $resp1 = $this->actingAs($author)->post(route('documents.stage'), [
            'patient_id' => $this->patient->id,
            'document_type_id' => $this->docType->id,
            'patient_owner_type' => 'mother',
            'title' => 'Malicious 1',
            'document_date' => '2026-11-18',
            'confidentiality_level' => 'confidential',
            'file' => $phpDisguised,
        ]);
        $resp1->assertSessionHasErrors('file');

        // 2. Dangerous double extension (.php.pdf) even with PDF bytes
        $doubleExt = $this->fakeValidPdf('payload.php.pdf');
        $resp2 = $this->actingAs($author)->post(route('documents.stage'), [
            'patient_id' => $this->patient->id,
            'document_type_id' => $this->docType->id,
            'patient_owner_type' => 'mother',
            'title' => 'Malicious 2',
            'document_date' => '2026-11-18',
            'confidentiality_level' => 'confidential',
            'file' => $doubleExt,
        ]);
        $resp2->assertSessionHasErrors('file');

        // 3. Windows MZ executable renamed to .exe
        $exeFile = UploadedFile::fake()->createWithContent('virus.exe', "MZ\x90\x00\x03\x00\x00\x00");
        $resp3 = $this->actingAs($author)->post(route('documents.stage'), [
            'patient_id' => $this->patient->id,
            'document_type_id' => $this->docType->id,
            'patient_owner_type' => 'mother',
            'title' => 'Malicious 3',
            'document_date' => '2026-11-18',
            'confidentiality_level' => 'confidential',
            'file' => $exeFile,
        ]);
        $resp3->assertSessionHasErrors('file');
    }

    /** Test #12: Viewer cannot upload or create documents */
    public function test_viewer_cannot_upload_documents(): void
    {
        $viewer = $this->createUserWithRole('viewer');

        $this->actingAs($viewer)
            ->get(route('documents.create', $this->patient))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('documents.stage'), [
                'patient_id' => $this->patient->id,
                'document_type_id' => $this->docType->id,
                'patient_owner_type' => 'mother',
                'title' => 'Unauthorized',
                'document_date' => '2026-11-18',
                'confidentiality_level' => 'confidential',
                'file' => $this->fakeValidPdf(),
            ])
            ->assertForbidden();
    }

    /** Test #15 & #16: Adding Revision does not overwrite old file and maintains single current revision */
    public function test_adding_revision_preserves_old_file_and_enforces_single_current_revision(): void
    {
        $author = $this->createUserWithRole('author');

        // Create initial Document (Rev 1)
        $this->actingAs($author)->post(route('documents.stage'), [
            'patient_id' => $this->patient->id,
            'document_type_id' => $this->docType->id,
            'patient_owner_type' => 'mother',
            'title' => 'ใบบันทึกการคลอด',
            'document_date' => '2026-11-18',
            'confidentiality_level' => 'confidential',
            'file' => $this->fakeValidPdf('delivery_v1.pdf', 'ORIGINAL_V1_BYTES'),
        ]);
        $stagedDoc = session('staged_documents');
        $docToken = (string) array_key_first($stagedDoc);
        $this->actingAs($author)->post(route('documents.confirm'), [
            'staging_token' => $docToken,
            'idempotency_token' => $stagedDoc[$docToken]['idempotency_token'],
        ]);

        $document = Document::query()->with('currentRevision.storedFile')->firstOrFail();
        $rev1 = $document->currentRevision;
        $rev1Path = $rev1->storedFile->storage_path;
        $rev1Hash = $rev1->storedFile->sha256_hash;

        // Add Revision 2
        $this->actingAs($author)->post(route('documents.revisions.stage', $document), [
            'change_reason' => 'เพิ่มหน้าที่สแกนตกหล่นจำนวน 1 หน้า',
            'notes' => 'ตรวจสอบครบแล้ว',
            'file' => $this->fakeValidPdf('delivery_v2.pdf', 'UPDATED_V2_BYTES'),
        ]);
        $stagedRev = session('staged_revisions');
        $revToken = (string) array_key_first($stagedRev);

        $this->actingAs($author)->post(route('documents.revisions.confirm', $document), [
            'staging_token' => $revToken,
            'idempotency_token' => $stagedRev[$revToken]['idempotency_token'],
        ])->assertRedirect(route('documents.show', $document));

        $rev1->refresh();
        $rev2 = DocumentRevision::query()->where('document_id', $document->id)->where('revision_number', 2)->with('storedFile')->firstOrFail();

        // Test #15: Old file untouched and Rev 1 superseded
        $this->assertSame(RevisionStatus::Superseded, $rev1->status);
        $this->assertNull($rev1->is_current_flag);
        $this->assertNotNull($rev1->superseded_at);
        Storage::disk('private_documents')->assertExists($rev1Path);
        Storage::disk('private_documents')->assertExists($rev2->storedFile->storage_path);
        $this->assertNotSame($rev1Path, $rev2->storedFile->storage_path);
        $this->assertNotSame($rev1Hash, $rev2->storedFile->sha256_hash);

        // Test #16: Only ONE current revision exists, and DB constraint rejects a second current row
        $currentCount = DocumentRevision::query()
            ->where('document_id', $document->id)
            ->where('status', RevisionStatus::Current->value)
            ->count();
        $this->assertSame(1, $currentCount);

        $this->expectException(QueryException::class);
        DocumentRevision::query()->create([
            'document_id' => $document->id,
            'revision_number' => 3,
            'stored_file_id' => $rev2->stored_file_id,
            'status' => RevisionStatus::Current->value,
            'is_current_flag' => 1,
            'created_by' => $author->id,
        ]);
    }
}
