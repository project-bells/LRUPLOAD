<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminAndAllScreensSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Patient $patient;
    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('tmp_uploads');
        Storage::fake('private_documents');

        $this->seed([
            RolesAndPermissionsSeeder::class,
            InitialDataSeeder::class,
        ]);

        $dept = Department::query()->where('code', 'LR')->firstOrFail();
        $this->admin = User::query()->create([
            'department_id' => $dept->id,
            'username' => 'sysadmin',
            'name' => 'Admin Aikchol',
            'email' => 'sysadmin@aikchol.local',
            'password' => Hash::make('AdminSecret@2026!'),
            'is_active' => true,
        ]);
        $adminRole = Role::query()->where('name', 'administrator')->firstOrFail();
        $this->admin->roles()->sync([$adminRole->id]);

        $hnSec = app(HnSecurityService::class);
        $this->patient = Patient::query()->create([
            'source_system' => 'his_sqlsrv_2014',
            'patient_type' => 'mother',
            'hn_encrypted' => '66-112233',
            'hn_hash' => $hnSec->hash('66-112233'),
            'title_name_encrypted' => 'นาง',
            'first_name_encrypted' => 'มานี',
            'last_name_encrypted' => 'มีสุข',
            'source_fetched_at' => now(),
        ]);

        $docType = DocumentType::query()->firstOrFail();
        $pdf = UploadedFile::fake()->createWithContent('smoke.pdf', "%PDF-1.4\n%%EOF\n");
        $staged = app(FileStorageService::class)->stageUpload($pdf, $this->admin);
        $this->document = app(DocumentService::class)->createDocumentFromStagedUpload(
            primaryPatient: $this->patient,
            metadata: [
                'document_type_id' => $docType->id,
                'patient_owner_type' => 'mother',
                'title' => 'เอกสารทดสอบหน้าจอทั้งหมด',
                'document_date' => '2026-11-18',
                'confidentiality_level' => 'confidential',
            ],
            stagedFile: $staged,
            actor: $this->admin,
        );
    }

    public function test_all_core_and_admin_screens_render_cleanly_with_aikchol_branding(): void
    {
        $rev = $this->document->currentRevision()->firstOrFail();

        $urls = [
            route('dashboard'),
            route('patients.lookup.index'),
            route('patients.index'),
            route('patients.show', $this->patient),
            route('patients.relationships', $this->patient),
            route('documents.index'),
            route('documents.create', $this->patient),
            route('documents.show', $this->document),
            route('documents.edit-metadata', $this->document),
            route('documents.revisions.index', $this->document),
            route('documents.revisions.create', $this->document),
            route('documents.revisions.preview', [$this->document, $rev]),
            route('documents.delete-request.create', $this->document),
            route('delete-requests.index'),
            route('documents.trash'),
            route('audit-logs.index'),
            route('admin.users.index'),
            route('admin.roles.index'),
            route('admin.document-types.index'),
            route('admin.settings.index'),
            route('admin.branding.index'),
        ];

        foreach ($urls as $url) {
            $resp = $this->actingAs($this->admin)->get($url);
            $resp->assertOk();
            $resp->assertSee('Aikchol Hospital');
        }
    }

    public function test_sql_server_password_is_encrypted_and_never_displayed_on_settings_screen(): void
    {
        $secretPassword = 'UltraSecretSql2014Password!@#';

        $this->actingAs($this->admin)->put(route('admin.settings.update'), [
            'max_file_size_kb' => 20480,
            'soft_delete_retention_days' => 45,
            'patient_db_host' => '192.168.10.20',
            'patient_db_port' => 1433,
            'patient_db_database' => 'HIS_DB',
            'patient_db_username' => 'his_readonly',
            'patient_db_password' => $secretPassword,
            'patient_db_encrypt' => 1,
            'patient_db_login_timeout' => 5,
            'patient_db_query_timeout' => 10,
        ])->assertRedirect(route('admin.settings.index'));

        $page = $this->actingAs($this->admin)->get(route('admin.settings.index'));
        $page->assertOk();
        $page->assertDontSee($secretPassword);
        $page->assertSee('มีรหัสผ่านบันทึกไว้แล้ว');
        $this->assertSame('45', SystemSetting::query()->where('key', 'soft_delete_retention_days')->value('value_plain'));

        $rawDbRow = SystemSetting::query()->where('key', 'patient_db_password')->firstOrFail();
        $this->assertSame($secretPassword, $rawDbRow->resolvedValue());
        $this->assertNotSame($secretPassword, $rawDbRow->getRawOriginal('value_encrypted'));
    }

    public function test_supervisor_and_admin_permanent_delete_and_policy_toggle_preserve_audit_logs(): void
    {
        $dept = Department::query()->where('code', 'LR')->firstOrFail();
        $supervisor = User::query()->create([
            'department_id' => $dept->id,
            'username' => 'sup_perm_test',
            'name' => 'Supervisor Test',
            'email' => 'sup_perm@aikchol.local',
            'password' => Hash::make('SupSecret@2026!'),
            'is_active' => true,
        ]);
        $supRole = Role::query()->where('name', 'supervisor')->firstOrFail();
        $supervisor->roles()->sync([$supRole->id]);

        $this->document->delete(); // Soft delete first

        // When Admin disables permanent delete policy -> 403 Forbidden
        SystemSetting::query()->where('key', 'permanent_delete_enabled')->update(['value_plain' => '0']);
        $this->actingAs($supervisor)->delete(route('documents.permanent-delete', $this->document->id), [
            'password' => 'SupSecret@2026!',
            'reason' => 'คำสั่งอนุมัติลบถาวรตามนโยบายหน่วยงาน',
        ])->assertForbidden();

        // Enable policy via SystemSetting
        SystemSetting::query()->where('key', 'permanent_delete_enabled')->update(['value_plain' => '1']);

        // Wrong password rejected
        $this->actingAs($supervisor)->delete(route('documents.permanent-delete', $this->document->id), [
            'password' => 'WrongPassword!',
            'reason' => 'คำสั่งอนุมัติลบถาวรตามนโยบายหน่วยงาน',
        ])->assertSessionHasErrors('password');

        // Correct password -> Supervisor permanently deletes & preserves Audit Logs
        $docId = $this->document->id;
        $this->actingAs($supervisor)->delete(route('documents.permanent-delete', $docId), [
            'password' => 'SupSecret@2026!',
            'reason' => 'คำสั่งอนุมัติลบถาวรตามนโยบาย PDPA โรงพยาบาลเอกชล',
        ])->assertRedirect(route('documents.trash'));

        $this->assertDatabaseMissing('documents', ['id' => $docId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'permanent_delete', 'document_id' => $docId, 'user_id' => $supervisor->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'create_document', 'document_id' => $docId]);
        $this->assertGreaterThanOrEqual(2, AuditLog::query()->where('document_id', $docId)->count());
    }

    public function test_scheduled_retention_purge_deletes_only_expired_soft_deleted_documents(): void
    {
        $docType = DocumentType::query()->firstOrFail();
        $fileSvc = app(FileStorageService::class);
        $docSvc = app(DocumentService::class);

        // Create a second document that was soft-deleted only 5 days ago
        $pdf = UploadedFile::fake()->createWithContent('recent_trash.pdf', "%PDF-1.4\n%%EOF\n");
        $recentDoc = $docSvc->createDocumentFromStagedUpload(
            primaryPatient: $this->patient,
            metadata: [
                'document_type_id' => $docType->id,
                'patient_owner_type' => 'mother',
                'title' => 'เอกสารถูกลบไม่ถึง 30 วัน',
                'document_date' => '2026-11-18',
                'confidentiality_level' => 'confidential',
            ],
            stagedFile: $fileSvc->stageUpload($pdf, $this->admin),
            actor: $this->admin,
        );

        // Mark $this->document as soft-deleted 35 days ago, $recentDoc 5 days ago
        $this->document->delete();
        Document::withTrashed()->whereKey($this->document->id)->update(['deleted_at' => now()->subDays(35)]);

        $recentDoc->delete();
        Document::withTrashed()->whereKey($recentDoc->id)->update(['deleted_at' => now()->subDays(5)]);

        // Configure Admin retention = 30 days
        SystemSetting::query()->where('key', 'soft_delete_retention_days')->update(['value_plain' => '30']);

        $this->artisan('documents:purge-expired-trash')->assertSuccessful();

        // Expired doc (35d > 30d) permanently purged, Audit Logs preserved
        $this->assertDatabaseMissing('documents', ['id' => $this->document->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'permanent_delete', 'document_id' => $this->document->id]);

        // Recent trashed doc (5d < 30d) must still remain in trash
        $this->assertSoftDeleted('documents', ['id' => $recentDoc->id]);
    }

    public function test_dashboard_limits_to_20_with_updated_labels_and_documents_index_paginates_20(): void
    {
        $docType = DocumentType::query()->firstOrFail();
        $fileSvc = app(FileStorageService::class);
        $docSvc = app(DocumentService::class);

        for ($i = 1; $i <= 24; $i++) {
            $pdf = UploadedFile::fake()->createWithContent("doc_{$i}.pdf", "%PDF-1.4\n%{$i}\n%%EOF\n");
            $docSvc->createDocumentFromStagedUpload(
                primaryPatient: $this->patient,
                metadata: [
                    'document_type_id' => $docType->id,
                    'patient_owner_type' => 'mother',
                    'title' => "เอกสารลำดับ {$i}",
                    'document_date' => '2026-11-18',
                    'confidentiality_level' => 'confidential',
                ],
                stagedFile: $fileSvc->stageUpload($pdf, $this->admin),
                actor: $this->admin,
            );
        }

        // Total active = 25 (1 from setUp + 24 created)
        $dashboard = $this->actingAs($this->admin)->get(route('dashboard'));
        $dashboard->assertOk();
        $dashboard->assertSee('เอกสารพร้อมใช้งาน');
        $dashboard->assertSee('เอกสารทั้งหมดในระบบ');
        $dashboard->assertViewHas('recentDocuments', static fn ($docs) => $docs->count() === 20);

        $indexPage1 = $this->actingAs($this->admin)->get(route('documents.index'));
        $indexPage1->assertOk();
        $indexPage1->assertViewHas('documents', static fn ($paginator) => $paginator->perPage() === 20 && $paginator->count() === 20 && $paginator->total() === 25);

        $indexPage2 = $this->actingAs($this->admin)->get(route('documents.index', ['page' => 2]));
        $indexPage2->assertOk();
        $indexPage2->assertViewHas('documents', static fn ($paginator) => $paginator->count() === 5);
    }
}
