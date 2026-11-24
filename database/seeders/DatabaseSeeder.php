<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            InitialDataSeeder::class,
        ]);

        $lrDepartment = Department::query()->where('code', 'LR')->first();

        // Always ensure at least one initial administrator account exists
        $adminUser = User::query()->where('username', 'admin')->first();
        if ($adminUser === null) {
            $adminUser = User::query()->create([
                'department_id' => $lrDepartment?->id,
                'employee_code' => 'AK-ADMIN-001',
                'username' => 'admin',
                'name' => 'ผู้ดูแลระบบ โรงพยาบาลเอกชล',
                'email' => 'admin@aikchol.local',
                'password' => Hash::make('Admin@Aikchol2026!'),
                'is_active' => true,
            ]);
            $adminRole = Role::query()->where('name', 'administrator')->first();
            if ($adminRole !== null) {
                $adminUser->roles()->sync([$adminRole->id]);
            }
        }

        // Seed additional demo accounts in non-production environments
        if (! app()->environment('production')) {
            $demoUsers = [
                [
                    'username' => 'supervisor',
                    'employee_code' => 'AK-LR-SUP01',
                    'name' => 'หัวหน้าแผนกห้องคลอด',
                    'email' => 'supervisor@aikchol.local',
                    'password' => 'Sup@Aikchol2026!',
                    'role' => 'supervisor',
                ],
                [
                    'username' => 'author',
                    'employee_code' => 'AK-LR-RN01',
                    'name' => 'พยาบาลวิชาชีพ ห้องคลอด',
                    'email' => 'author@aikchol.local',
                    'password' => 'Author@Aikchol2026!',
                    'role' => 'author',
                ],
                [
                    'username' => 'viewer',
                    'employee_code' => 'AK-LR-VW01',
                    'name' => 'เจ้าหน้าที่ตรวจสอบเอกสาร',
                    'email' => 'viewer@aikchol.local',
                    'password' => 'Viewer@Aikchol2026!',
                    'role' => 'viewer',
                ],
            ];

            foreach ($demoUsers as $item) {
                $user = User::query()->updateOrCreate(
                    ['email' => $item['email']],
                    [
                        'department_id' => $lrDepartment?->id,
                        'employee_code' => $item['employee_code'],
                        'username' => $item['username'],
                        'name' => $item['name'],
                        'password' => Hash::make($item['password']),
                        'is_active' => true,
                    ]
                );

                $role = Role::query()->where('name', $item['role'])->first();
                if ($role !== null) {
                    $user->roles()->sync([$role->id]);
                }
            }
        }
    }
}
