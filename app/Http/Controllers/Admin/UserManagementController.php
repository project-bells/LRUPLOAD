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
use Illuminate\Support\Facades\Hash;
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
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:80', 'alpha_dash', 'unique:users,username'],
            'employee_code' => ['nullable', 'string', 'max:50', 'unique:users,employee_code'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'password' => ['required', 'string', 'min:10', 'max:120'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
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
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'employee_code' => ['nullable', 'string', 'max:50', Rule::unique('users', 'employee_code')->ignore($user->id)],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'password' => ['nullable', 'string', 'min:10', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        $beforeRoles = $user->roles()->pluck('roles.id')->all();

        $updateData = [
            'name' => (string) $validated['name'],
            'username' => (string) $validated['username'],
            'employee_code' => $validated['employee_code'] ?? null,
            'email' => (string) $validated['email'],
            'department_id' => $validated['department_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make((string) $validated['password']);
            $updateData['password_changed_at'] = now();
        }

        $user->update($updateData);
        $user->roles()->sync($validated['role_ids']);

        $this->auditLog->record(
            action: 'manage_user',
            user: $request->user(),
            outcome: 'success',
            beforeData: ['target_user_id' => $user->id, 'role_ids' => $beforeRoles],
            afterData: [
                'operation' => 'update',
                'target_user_id' => $user->id,
                'is_active' => $user->is_active,
                'role_ids' => $validated['role_ids'],
            ],
            request: $request,
        );

        if ($beforeRoles !== array_map('intval', $validated['role_ids'])) {
            $this->auditLog->record(
                action: 'change_role',
                user: $request->user(),
                outcome: 'success',
                beforeData: ['target_user_id' => $user->id, 'role_ids' => $beforeRoles],
                afterData: ['target_user_id' => $user->id, 'role_ids' => array_map('intval', $validated['role_ids'])],
                request: $request,
            );
        }

        return redirect()->route('admin.users.index')->with('status', 'อัปเดตข้อมูลผู้ใช้งานเรียบร้อยแล้ว');
    }
}
