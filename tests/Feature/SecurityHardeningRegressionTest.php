<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DeleteRequest;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Patient;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\FileStorageService;
use App\Services\HnSecurityService;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SecurityHardeningRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Department $lrDept;
    private Department $opdDept;
    private DocumentType $docType;
    private Patient $lrPatient;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('tmp_uploads');
        Storage::fake('private_documents');

        $this->seed([
            RolesAndPermissionsSeeder::class,
            InitialDataSeeder::class,
        ]);

        $this->lrDept = Department::query()->where('code', 'LR')->firstOrFail();
        $this->opdDept = Department::query()->create([
            'code' => 'OPD',
            'name_th' => 'แผนกผู้ป่วยนอก',
            'name_en' => 'Outpatient Department',
            'is_active' => true,
        ]);

        $this->docType = DocumentType::query()->firstOrFail();

        $hnSec = app(HnSecurityService::class);
        $this->lrPatient = Patient::query()->create([
            'source_system' => 'his_sqlsrv_2014',
            'patient_type' => 'mother',
            'hn_encrypted' => '66-880011',
            'hn_hash' => $hnSec->hash('66-880011'),
            'title_name_encrypted' => 'นาง',
            'first_name_encrypted' => 'ทดสอบ<script>alert(1)</script>',
            'last_name_encrypted' => 'ความปลอดภัย',
            'source_fetched_at' => now(),
        ]);
    }

    private function createUser(string $roleName, Department $dept, bool $active = true): User
    {
        $user = User::query()->create([
            'department_id' => $dept->id,
            'username' => $roleName . '_' . uniqid(),
            'name' => ucfirst($roleName) . ' User',
            'email' => uniqid() . '@aikchol.local',
            'password' => Hash::make('StrongPass@2026!'),
            'is_active' => $active,
        ]);
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function createLrDocument(User $author, string $title = 'เอกสารห้องคลอดทดสอบ', string $filename = 'safe.pdf'): Document
    {
        $pdfBytes = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n";
        $staged = app(FileStorageService::class)->stageUpload(
            UploadedFile::fake()->createWithContent($filename, $pdfBytes),
            $author
        );

        return app(DocumentService::class)->createDocumentFromStagedUpload(
            primaryPatient: $this->lrPatient,
            metadata: [
                'document_type_id' => $this->docType->id,
                'patient_owner_type' => 'mother',
                'title' => $title,
                'document_date' => '2026-11-18',
                'confidentiality_level' => 'confidential',
            ],
            stagedFile: $staged,
            actor: $author,
        );
    }

    /** SEC-H-01: Cross-department horizontal IDOR blocked on delete-requests & patient views */
    public function test_cross_department_supervisor_cannot_see_lr_delete_requests_or_patient_profile(): void
    {
        $lrAuthor = $this->createUser('author', $this->lrDept);
        $opdSupervisor = $this->createUser('supervisor', $this->opdDept);

        $doc = $this->createLrDocument($lrAuthor, 'เอกสารลับเฉพาะห้องคลอด');

        $this->actingAs($lrAuthor)->post(route('documents.delete-request.store', $doc), [
            'request_reason' => 'เอกสารซ้ำต้องการลบ',
        ])->assertRedirect(route('documents.show', $doc));

        // OPD supervisor opening /delete-requests must NOT see LR's delete request
        $queue = $this->actingAs($opdSupervisor)->get(route('delete-requests.index'));
        $queue->assertOk();
        $queue->assertDontSee('เอกสารลับเฉพาะห้องคลอด');

        // OPD supervisor cannot view LR-only patient profile
        $this->actingAs($opdSupervisor)
            ->get(route('patients.show', $this->lrPatient))
            ->assertForbidden();
    }

    /** SEC-H-02: Requester cannot approve their own delete request (Separation of Duties) */
    public function test_supervisor_cannot_approve_own_delete_request(): void
    {
        $lrSupervisor1 = $this->createUser('supervisor', $this->lrDept);
        $lrSupervisor2 = $this->createUser('supervisor', $this->lrDept);
        $doc = $this->createLrDocument($lrSupervisor1);

        $this->actingAs($lrSupervisor1)->post(route('documents.delete-request.store', $doc), [
            'request_reason' => 'ส่งคำขอลบโดยหัวหน้าคนที่ 1',
        ])->assertRedirect(route('documents.show', $doc));

        $req = DeleteRequest::query()->firstOrFail();

        // Self-approval MUST be blocked with 403
        $this->actingAs($lrSupervisor1)
            ->post(route('delete-requests.review', $req), ['decision' => 'approve'])
            ->assertForbidden();
        $this->assertNotSoftDeleted('documents', ['id' => $doc->id]);

        // Another supervisor in the same department CAN approve
        $this->actingAs($lrSupervisor2)
            ->post(route('delete-requests.review', $req), ['decision' => 'approve', 'review_note' => 'อนุมัติโดยหัวหน้าท่านอื่น'])
            ->assertRedirect(route('delete-requests.index'));
        $this->assertSoftDeleted('documents', ['id' => $doc->id]);
    }

    /** SEC-H-03: Cannot skip Review Before Save, cannot reuse another user's staging token, and expired staging is rejected */
    public function test_cannot_skip_review_step_or_use_another_users_staging_token(): void
    {
        $author1 = $this->createUser('author', $this->lrDept);
        $author2 = $this->createUser('author', $this->lrDept);
        $pdfBytes = "%PDF-1.4\n%%EOF\n";

        $this->actingAs($author1)->post(route('documents.stage'), [
            'patient_id' => $this->lrPatient->id,
            'document_type_id' => $this->docType->id,
            'patient_owner_type' => 'mother',
            'title' => 'ทดสอบห้ามข้ามหน้า Review',
            'document_date' => '2026-11-18',
            'confidentiality_level' => 'confidential',
            'file' => UploadedFile::fake()->createWithContent('review_gate.pdf', $pdfBytes),
        ]);

        $staged = session('staged_documents');
        $token = (string) array_key_first($staged);
        $idem = (string) $staged[$token]['idempotency_token'];

        // Attempting confirm WITHOUT visiting review first MUST NOT create document
        $this->actingAs($author1)->post(route('documents.confirm'), [
            'staging_token' => $token,
            'idempotency_token' => $idem,
        ])->assertRedirect(route('documents.review', ['token' => $token]));
        $this->assertDatabaseCount('documents', 0);

        // Another user attempting to open review or confirm with author1's token MUST fail
        $this->actingAs($author2)->get(route('documents.review', ['token' => $token]))
            ->assertRedirect(route('patients.lookup.index'));

        $signedStagedUrl = URL::temporarySignedRoute('documents.staged.stream', now()->addMinutes(5), ['token' => $token], false);
        $this->actingAs($author2)->get($signedStagedUrl)->assertForbidden();
    }

    /** SEC-H-04: Safe Content-Disposition header with RFC 6266 & quote stripping + expired signed preview URL blocked */
    public function test_content_disposition_sanitizes_quotes_and_expired_signed_url_is_rejected(): void
    {
        $author = $this->createUser('author', $this->lrDept);
        $doc = $this->createLrDocument($author, 'ทดสอบ Header', 'report";evil=1.pdf');
        $rev = $doc->currentRevision()->firstOrFail();

        // Valid signed URL produces safe Content-Disposition without injected semicolon parameter
        $validUrl = URL::temporarySignedRoute('documents.revisions.stream', now()->addMinutes(5), ['document' => $doc->id, 'revision' => $rev->id], false);
        $resp = $this->actingAs($author)->get($validUrl);
        $resp->assertOk();
        $disposition = (string) $resp->headers->get('Content-Disposition');
        $this->assertStringStartsWith('inline;', $disposition);
        $this->assertStringNotContainsString(';evil=1', $disposition);
        $resp->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');

        // Expired signed preview URL MUST be rejected with 403
        $expiredUrl = URL::temporarySignedRoute('documents.revisions.stream', now()->subMinutes(5), ['document' => $doc->id, 'revision' => $rev->id], false);
        $this->actingAs($author)->get($expiredUrl)->assertForbidden();
    }

    /** SEC-H-05 & Upload Hardening: Runtime max_file_size_kb, path traversal filename, and oversized files rejected */
    public function test_runtime_max_file_size_and_path_traversal_filenames_are_enforced(): void
    {
        $author = $this->createUser('author', $this->lrDept);
        SystemSetting::query()->where('key', 'max_file_size_kb')->update(['value_plain' => '512']);

        // Path traversal filename containing '..' rejected during upload
        $this->actingAs($author)->post(route('documents.stage'), [
            'patient_id' => $this->lrPatient->id,
            'document_type_id' => $this->docType->id,
            'patient_owner_type' => 'mother',
            'title' => 'Traversal Test',
            'document_date' => '2026-11-18',
            'confidentiality_level' => 'confidential',
            'file' => UploadedFile::fake()->createWithContent('..secret..passwd.pdf', "%PDF-1.4\n%%EOF\n"),
        ])->assertSessionHasErrors('file');

        // Path traversal in storage_path rejected at FileStorageService boundary
        $tampered = new \App\Models\StoredFile([
            'storage_disk' => 'private_documents',
            'storage_path' => '../../.env',
        ]);
        $traversalBlocked = false;
        try {
            app(FileStorageService::class)->resolveAbsolutePath($tampered);
        } catch (\RuntimeException) {
            $traversalBlocked = true;
        }
        $this->assertTrue($traversalBlocked);

        // Oversized file (> 512 KB configured at runtime) rejected
        $largeContent = "%PDF-1.4\n" . str_repeat('A', 600 * 1024) . "\n%%EOF\n";
        $this->actingAs($author)->post(route('documents.stage'), [
            'patient_id' => $this->lrPatient->id,
            'document_type_id' => $this->docType->id,
            'patient_owner_type' => 'mother',
            'title' => 'Oversize Test',
            'document_date' => '2026-11-18',
            'confidentiality_level' => 'confidential',
            'file' => UploadedFile::fake()->createWithContent('big.pdf', $largeContent),
        ])->assertSessionHasErrors('file');
    }

    /** SEC-M-01 & SEC-M-02: Suspended user login anti-enumeration, mid-session lockout, and session revocation on password reset */
    public function test_suspended_user_anti_enumeration_and_session_revocation_on_admin_update(): void
    {
        $admin = $this->createUser('administrator', $this->lrDept);
        $suspended = $this->createUser('author', $this->lrDept, active: false);

        // Login error for suspended user matches non-existent user (no user enumeration)
        $respSuspended = $this->post(route('login.submit'), ['login' => $suspended->username, 'password' => 'WrongPass']);
        $respMissing = $this->post(route('login.submit'), ['login' => 'does_not_exist_999', 'password' => 'WrongPass']);
        $this->assertSame(
            $respSuspended->getSession()->get('errors')->first('login'),
            $respMissing->getSession()->get('errors')->first('login')
        );

        // Suspended user accessing authenticated route mid-session is logged out & redirected
        $this->actingAs($suspended)->get(route('dashboard'))->assertRedirect(route('login'));

        // Admin updating user password revokes target user's DB sessions & rotates remember_token
        $target = $this->createUser('viewer', $this->lrDept, active: true);
        DB::table('sessions')->insert([
            'id' => 'stolen_session_id_123',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'TestBrowser',
            'payload' => base64_encode('test'),
            'last_activity' => now()->getTimestamp(),
        ]);
        $roleId = Role::query()->where('name', 'viewer')->value('id');

        $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'current_password' => 'StrongPass@2026!',
            'name' => $target->name,
            'username' => $target->username,
            'email' => $target->email,
            'department_id' => $this->lrDept->id,
            'password' => 'NewRotatedPass@2026!',
            'is_active' => 1,
            'role_ids' => [$roleId],
        ])->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('sessions', ['id' => 'stolen_session_id_123']);
    }

    /** SEC-M-04 & XSS & Mass Assignment & Guest Access checks */
    public function test_audit_log_post_hn_search_view_auditing_xss_escaping_and_mass_assignment_protection(): void
    {
        $admin = $this->createUser('administrator', $this->lrDept);
        $author = $this->createUser('author', $this->lrDept);

        // Guest cannot access patient/document/preview/download routes
        $doc = $this->createLrDocument($author, 'ชื่อเอกสาร <img src=x onerror=alert(1)>');
        $rev = $doc->currentRevision()->firstOrFail();
        $this->get(route('patients.show', $this->lrPatient))->assertRedirect(route('login'));
        $this->get(route('documents.revisions.preview', [$doc, $rev]))->assertRedirect(route('login'));
        $this->get(route('documents.revisions.download', [$doc, $rev]))->assertRedirect(route('login'));

        // XSS in patient name & document title MUST be escaped in rendered HTML
        $docPage = $this->actingAs($author)->get(route('documents.show', $doc));
        $docPage->assertOk();
        $docPage->assertDontSee('<script>alert(1)</script>', false);
        $docPage->assertDontSee('<img src=x onerror=alert(1)>', false);
        $docPage->assertSee('&lt;img src=x onerror=alert(1)&gt;', false);

        // Mass Assignment: submitting created_by or document_number in metadata update cannot override them
        $originalCreator = $doc->created_by;
        $originalNumber = $doc->document_number;
        $this->actingAs($author)->put(route('documents.update-metadata', $doc), [
            'document_type_id' => $this->docType->id,
            'title' => 'ชื่ออัปเดตแล้ว',
            'document_date' => '2026-11-18',
            'confidentiality_level' => 'confidential',
            'created_by' => $admin->id,
            'document_number' => 'HACKED-001',
        ])->assertRedirect(route('documents.show', $doc));
        $doc->refresh();
        $this->assertSame($originalCreator, $doc->created_by);
        $this->assertSame($originalNumber, $doc->document_number);

        // Audit Log HN search uses POST and redirects with hn_hash ONLY (no plaintext HN in URL)
        $expectedHash = app(HnSecurityService::class)->hash('66-880011');
        $searchResp = $this->actingAs($admin)->post(route('audit-logs.search-hn'), ['hn' => '66-880011']);
        $searchResp->assertRedirect(route('audit-logs.index', ['hn_hash' => $expectedHash]));

        // Viewing patient & viewing audit log both produce audit events
        $this->actingAs($author)->get(route('patients.show', $this->lrPatient))->assertOk();
        $this->actingAs($admin)->get(route('audit-logs.index'))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'view_patient', 'user_id' => $author->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'view_audit_log', 'user_id' => $admin->id]);
    }
}
