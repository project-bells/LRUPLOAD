<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {
    }

    public function index(): View
    {
        $users = User::query()
            ->with(['department', 'roles'])
            ->orderBy('name')
            ->paginate(20);

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('id')->get(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name_th')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:80', 'alpha_dash', 'unique:users,username'],
            'employee_code' => ['nullable', 'string', 'max:50', 'unique:users,employee_code'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'password' => ['required', 'string', 'min:10', 'max:120'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ], [
            'current_password.required' => 'กรุณากรอกรหัสผ่านผู้ดูแลระบบเพื่อยืนยัน',
            'current_password.current_password' => 'รหัสผ่านยืนยันตัวตนไม่ถูกต้อง',
        ]);

        $newUser = User::query()->create([
            'name' => (string) $validated['name'],
            'username' => (string) $validated['username'],
            'employee_code' => $validated['employee_code'] ?? null,
            'email' => (string) $validated['email'],
            'department_id' => $validated['department_id'] ?? null,
            'password' => Hash::make((string) $validated['password']),
            'is_active' => true,
            'password_changed_at' => now(),
        ]);

        $newUser->roles()->sync($validated['role_ids']);

        $this->auditLog->record(
            action: 'manage_user',
            user: $request->user(),
            outcome: 'success',
            afterData: [
                'operation' => 'create',
                'target_user_id' => $newUser->id,
                'role_ids' => $validated['role_ids'],
            ],
            request: $request,
        );

        return redirect()->route('admin.users.index')->with('status', 'สร้างบัญชีผู้ใช้งานเรียบร้อยแล้ว');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'employee_code' => ['nullable', 'string', 'max:50', Rule::unique('users', 'employee_code')->ignore($user->id)],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'password' => ['nullable', 'string', 'min:10', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ], [
            'current_password.required' => 'กรุณากรอกรหัสผ่านผู้ดูแลระบบเพื่อยืนยัน',
            'current_password.current_password' => 'รหัสผ่านยืนยันตัวตนไม่ถูกต้อง',
        ]);

        $beforeRoles = array_map('intval', $user->roles()->pluck('roles.id')->all());
        $newRoles = array_map('intval', $validated['role_ids']);
        $passwordChanged = ! empty($validated['password']);
        $willBeActive = $request->boolean('is_active');
        $rolesChanged = $beforeRoles !== $newRoles;

        $updateData = [
            'name' => (string) $validated['name'],
            'username' => (string) $validated['username'],
            'employee_code' => $validated['employee_code'] ?? null,
            'email' => (string) $validated['email'],
            'department_id' => $validated['department_id'] ?? null,
            'is_active' => $willBeActive,
        ];

        if ($passwordChanged) {
            $updateData['password'] = Hash::make((string) $validated['password']);
            $updateData['password_changed_at'] = now();
        }

        $user->update($updateData);
        $user->roles()->sync($newRoles);

        // Immediately invalidate active sessions & remember_token on password reset, deactivation, or role change (SEC-M-02)
        if ($passwordChanged || ! $willBeActive || $rolesChanged) {
            $user->forceFill(['remember_token' => Str::random(60)])->save();
            $this->revokeUserSessions($user->id, $request->user()?->id === $user->id ? $request->session()->getId() : null);
        }

        $this->auditLog->record(
            action: 'manage_user',
            user: $request->user(),
            outcome: 'success',
            beforeData: ['target_user_id' => $user->id, 'role_ids' => $beforeRoles],
            afterData: [
                'operation' => 'update',
                'target_user_id' => $user->id,
                'is_active' => $user->is_active,
                'password_reset' => $passwordChanged,
                'sessions_revoked' => ($passwordChanged || ! $willBeActive || $rolesChanged),
                'role_ids' => $newRoles,
            ],
            request: $request,
        );

        if ($rolesChanged) {
            $this->auditLog->record(
                action: 'change_role',
                user: $request->user(),
                outcome: 'success',
                beforeData: ['target_user_id' => $user->id, 'role_ids' => $beforeRoles],
                afterData: ['target_user_id' => $user->id, 'role_ids' => $newRoles],
                request: $request,
            );
        }

        return redirect()->route('admin.users.index')->with('status', 'อัปเดตข้อมูลผู้ใช้งานเรียบร้อยแล้ว');
    }

    private function revokeUserSessions(int $targetUserId, ?string $exceptSessionId = null): void
    {
        try {
            $table = (string) config('session.table', 'sessions');
            $query = DB::table($table)->where('user_id', $targetUserId);
            if ($exceptSessionId !== null && $exceptSessionId !== '') {
                $query->where('id', '!=', $exceptSessionId);
            }
            $query->delete();
        } catch (\Throwable) {
            // Safe no-op if sessions table not used by current driver
        }
    }
}
