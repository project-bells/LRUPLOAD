<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleManagementController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {
    }

    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::query()->with(['permissions'])->withCount('users')->orderBy('id')->get(),
            'permissionsGrouped' => Permission::query()->orderBy('group_name')->orderBy('name')->get()->groupBy('group_name'),
        ]);
    }

    public function updatePermissions(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ], [
            'current_password.required' => 'กรุณากรอกรหัสผ่านปัจจุบันเพื่อยืนยันการเปลี่ยนแปลงสิทธิ์',
            'current_password.current_password' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง',
        ]);

        $before = $role->permissions()->pluck('permissions.name')->all();
        $ids = array_map('intval', (array) ($validated['permission_ids'] ?? []));
        $role->permissions()->sync($ids);
        $after = $role->permissions()->pluck('permissions.name')->all();

        $this->auditLog->record(
            action: 'change_permission',
            user: $request->user(),
            outcome: 'success',
            beforeData: ['role' => $role->name, 'permissions' => $before],
            afterData: ['role' => $role->name, 'permissions' => $after],
            request: $request,
        );

        return redirect()->route('admin.roles.index')
            ->with('status', "อัปเดตสิทธิ์ของบทบาท {$role->display_name_th} เรียบร้อยแล้ว");
    }
}
