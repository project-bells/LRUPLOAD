<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {
    }

    public function index(): View
    {
        $settings = SystemSetting::query()->get()->keyBy('key');

        // Option 1 (SEC-H-05): SQL Server 2014 connection is strictly managed via server .env / Secret Manager.
        // Display only non-sensitive operational status flags (never expose IP, hostname, username, or password).
        $conn = (array) config('database.connections.patient_sqlsrv', []);

        return view('admin.settings.index', [
            'settings' => $settings,
            'hisEnvStatus' => [
                'enabled' => (bool) config('patient_lookup.enabled', false),
                'driver' => (string) ($conn['driver'] ?? 'sqlsrv'),
                'port' => (string) ($conn['port'] ?? '1433'),
                'encrypt' => (string) ($conn['encrypt'] ?? 'yes'),
                'trust_cert' => (string) ($conn['trust_server_certificate'] ?? 'false'),
                'login_timeout' => (int) ($conn['login_timeout'] ?? 5),
                'query_timeout' => (int) config('patient_lookup.query_timeout', 10),
                'host_configured' => trim((string) ($conn['host'] ?? '')) !== '',
                'database_configured' => trim((string) ($conn['database'] ?? '')) !== '',
                'credentials_configured' => trim((string) ($conn['username'] ?? '')) !== '' && trim((string) ($conn['password'] ?? '')) !== '',
                'pdo_sqlsrv_loaded' => extension_loaded('pdo_sqlsrv'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'permanent_delete_enabled' => ['nullable', 'boolean'],
            'soft_delete_retention_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'max_file_size_kb' => ['required', 'integer', 'min:512', 'max:102400'],
        ], [
            'current_password.required' => 'กรุณากรอกรหัสผ่านปัจจุบันเพื่อยืนยันการเปลี่ยนแปลงตั้งค่าระบบ',
            'current_password.current_password' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง',
        ]);

        $before = [
            'permanent_delete_enabled' => SystemSetting::query()->where('key', 'permanent_delete_enabled')->value('value_plain'),
            'soft_delete_retention_days' => SystemSetting::query()->where('key', 'soft_delete_retention_days')->value('value_plain'),
            'max_file_size_kb' => SystemSetting::query()->where('key', 'max_file_size_kb')->value('value_plain'),
        ];

        $pairs = [
            'permanent_delete_enabled' => $request->boolean('permanent_delete_enabled') ? '1' : '0',
            'soft_delete_retention_days' => (string) ((int) $validated['soft_delete_retention_days']),
            'max_file_size_kb' => (string) ((int) $validated['max_file_size_kb']),
        ];

        $userId = $request->user()?->id;

        foreach ($pairs as $key => $val) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'group_name' => 'security',
                    'value_plain' => $val,
                    'is_secret' => false,
                    'updated_by' => $userId,
                ]
            );
        }

        $this->auditLog->record(
            action: 'change_setting',
            user: $request->user(),
            outcome: 'success',
            beforeData: $before,
            afterData: [
                'permanent_delete_enabled' => $request->boolean('permanent_delete_enabled'),
                'soft_delete_retention_days' => (int) $validated['soft_delete_retention_days'],
                'max_file_size_kb' => (int) $validated['max_file_size_kb'],
            ],
            request: $request,
        );

        return redirect()->route('admin.settings.index')
            ->with('status', 'บันทึกการตั้งค่าระบบเรียบร้อยแล้ว (มีผลบังคับใช้ทันที)');
    }
}
