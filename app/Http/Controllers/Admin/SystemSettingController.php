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
        $hasPasswordSet = ($settings->get('patient_db_password')?->value_encrypted !== null && $settings->get('patient_db_password')?->value_encrypted !== '')
            || (string) config('database.connections.patient_sqlsrv.password', '') !== '';

        return view('admin.settings.index', [
            'settings' => $settings,
            'hasPasswordSet' => $hasPasswordSet,
            'envSummary' => [
                'patient_db_enabled' => (bool) config('patient_lookup.enabled', false),
                'patient_db_connection' => (string) config('database.connections.patient_sqlsrv.driver', 'sqlsrv'),
                'patient_db_encrypt' => (string) config('database.connections.patient_sqlsrv.encrypt', 'yes'),
                'patient_db_trust_cert' => (string) config('database.connections.patient_sqlsrv.trust_server_certificate', 'false'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'permanent_delete_enabled' => ['nullable', 'boolean'],
            'soft_delete_retention_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'max_file_size_kb' => ['required', 'integer', 'min:512', 'max:102400'],
            'patient_db_enabled' => ['nullable', 'boolean'],
            'patient_db_host' => ['nullable', 'string', 'max:150'],
            'patient_db_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'patient_db_database' => ['nullable', 'string', 'max:120'],
            'patient_db_username' => ['nullable', 'string', 'max:120'],
            'patient_db_password' => ['nullable', 'string', 'max:255'],
            'patient_db_encrypt' => ['nullable', 'boolean'],
            'patient_db_trust_server_certificate' => ['nullable', 'boolean'],
            'patient_db_login_timeout' => ['required', 'integer', 'min:1', 'max:60'],
            'patient_db_query_timeout' => ['required', 'integer', 'min:1', 'max:120'],
        ]);

        $plainPairs = [
            'security' => [
                'permanent_delete_enabled' => $request->boolean('permanent_delete_enabled') ? '1' : '0',
                'soft_delete_retention_days' => (string) ((int) $validated['soft_delete_retention_days']),
                'max_file_size_kb' => (string) $validated['max_file_size_kb'],
            ],
            'patient_sqlsrv' => [
                'patient_db_enabled' => $request->boolean('patient_db_enabled') ? '1' : '0',
                'patient_db_host' => (string) ($validated['patient_db_host'] ?? ''),
                'patient_db_port' => (string) $validated['patient_db_port'],
                'patient_db_database' => (string) ($validated['patient_db_database'] ?? ''),
                'patient_db_username' => (string) ($validated['patient_db_username'] ?? ''),
                'patient_db_encrypt' => $request->boolean('patient_db_encrypt') ? '1' : '0',
                'patient_db_trust_server_certificate' => $request->boolean('patient_db_trust_server_certificate') ? '1' : '0',
                'patient_db_login_timeout' => (string) $validated['patient_db_login_timeout'],
                'patient_db_query_timeout' => (string) $validated['patient_db_query_timeout'],
            ],
        ];

        $userId = $request->user()?->id;

        foreach ($plainPairs as $group => $items) {
            foreach ($items as $key => $val) {
                SystemSetting::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'group_name' => $group,
                        'value_plain' => $val,
                        'is_secret' => false,
                        'updated_by' => $userId,
                    ]
                );
            }
        }

        // Update encrypted SQL Server password ONLY when a new non-empty value is supplied (never expose existing password)
        $passwordUpdated = false;
        if (! empty($validated['patient_db_password'])) {
            SystemSetting::query()->updateOrCreate(
                ['key' => 'patient_db_password'],
                [
                    'group_name' => 'patient_sqlsrv',
                    'value_encrypted' => (string) $validated['patient_db_password'],
                    'value_plain' => null,
                    'is_secret' => true,
                    'updated_by' => $userId,
                ]
            );
            $passwordUpdated = true;
        }

        $this->auditLog->record(
            action: 'change_setting',
            user: $request->user(),
            outcome: 'success',
            afterData: [
                'permanent_delete_enabled' => $request->boolean('permanent_delete_enabled'),
                'soft_delete_retention_days' => (int) $validated['soft_delete_retention_days'],
                'max_file_size_kb' => (int) $validated['max_file_size_kb'],
                'patient_db_enabled' => $request->boolean('patient_db_enabled'),
                'patient_db_encrypt' => $request->boolean('patient_db_encrypt'),
                'patient_db_trust_server_certificate' => $request->boolean('patient_db_trust_server_certificate'),
                'patient_db_password_updated' => $passwordUpdated,
            ],
            request: $request,
        );

        return redirect()->route('admin.settings.index')
            ->with('status', 'บันทึกการตั้งค่าระบบเรียบร้อยแล้ว (รหัสผ่านเดิมจะไม่ถูกเปิดเผย)');
    }
}
