<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $perm = Permission::query()->updateOrCreate(
            ['name' => 'patients.delete'],
            [
                'group_name' => 'patients',
                'display_name_th' => 'ลบข้อมูลผู้ป่วย (กรณีไม่มีเอกสาร)',
            ]
        );

        foreach (['administrator', 'supervisor', 'author'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->first();
            if ($role && ! $role->permissions()->where('permission_id', $perm->id)->exists()) {
                $role->permissions()->attach($perm->id);
            }
        }
    }

    public function down(): void
    {
        $perm = Permission::query()->where('name', 'patients.delete')->first();
        if ($perm) {
            $perm->roles()->detach();
            $perm->delete();
        }
    }
};
