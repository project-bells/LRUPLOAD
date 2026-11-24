<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'patients.lookup', 'group_name' => 'patients', 'display_name_th' => 'ค้นหา HN ผู้ป่วยจาก HIS'],
            ['name' => 'patients.view', 'group_name' => 'patients', 'display_name_th' => 'ดูข้อมูลผู้ป่วยในระบบ'],
            ['name' => 'patients.delete', 'group_name' => 'patients', 'display_name_th' => 'ลบข้อมูลผู้ป่วย (กรณีไม่มีเอกสาร)'],
            ['name' => 'documents.view', 'group_name' => 'documents', 'display_name_th' => 'ดูรายการและรายละเอียดเอกสาร'],
            ['name' => 'documents.preview', 'group_name' => 'documents', 'display_name_th' => 'เปิดดูตัวอย่างไฟล์เอกสาร (Preview)'],
            ['name' => 'documents.download', 'group_name' => 'documents', 'display_name_th' => 'ดาวน์โหลดไฟล์เอกสาร'],
            ['name' => 'documents.create', 'group_name' => 'documents', 'display_name_th' => 'สร้างเอกสารและอัปโหลดไฟล์'],
            ['name' => 'documents.edit_metadata', 'group_name' => 'documents', 'display_name_th' => 'แก้ไขรายละเอียดเอกสาร (Metadata)'],
            ['name' => 'documents.create_revision', 'group_name' => 'documents', 'display_name_th' => 'เพิ่ม Revision เอกสาร'],
            ['name' => 'documents.request_delete', 'group_name' => 'documents', 'display_name_th' => 'ส่งคำขอลบเอกสาร'],
            ['name' => 'documents.soft_delete', 'group_name' => 'documents', 'display_name_th' => 'ลบเอกสารแบบ Soft Delete'],
            ['name' => 'documents.restore', 'group_name' => 'documents', 'display_name_th' => 'กู้คืนเอกสารที่ถูกลบ'],
            ['name' => 'documents.permanent_delete', 'group_name' => 'documents', 'display_name_th' => 'ลบเอกสารถาวร (ตามนโยบายพิเศษ)'],
            ['name' => 'revisions.view_all', 'group_name' => 'revisions', 'display_name_th' => 'ดูประวัติและไฟล์ Revision ทั้งหมด'],
            ['name' => 'delete_requests.review', 'group_name' => 'delete_requests', 'display_name_th' => 'อนุมัติหรือปฏิเสธคำขอลบเอกสาร'],
            ['name' => 'audit.view_department', 'group_name' => 'audit', 'display_name_th' => 'ดู Audit Log ของหน่วยงาน'],
            ['name' => 'audit.view_all', 'group_name' => 'audit', 'display_name_th' => 'ดู Audit Log ทั้งระบบ'],
            ['name' => 'users.manage', 'group_name' => 'admin', 'display_name_th' => 'จัดการผู้ใช้งาน'],
            ['name' => 'roles.manage', 'group_name' => 'admin', 'display_name_th' => 'จัดการบทบาทและสิทธิ์'],
            ['name' => 'document_types.manage', 'group_name' => 'admin', 'display_name_th' => 'จัดการประเภทเอกสาร'],
            ['name' => 'branding.manage', 'group_name' => 'admin', 'display_name_th' => 'จัดการ Logo และ Branding'],
            ['name' => 'settings.manage', 'group_name' => 'admin', 'display_name_th' => 'จัดการการตั้งค่าระบบและ SQL Server'],
        ];

        $permissionMap = [];
        foreach ($permissions as $item) {
            $perm = Permission::query()->updateOrCreate(
                ['name' => $item['name']],
                [
                    'group_name' => $item['group_name'],
                    'display_name_th' => $item['display_name_th'],
                ]
            );
            $permissionMap[$perm->name] = $perm->id;
        }

        $rolesConfig = [
            'viewer' => [
                'display_name_th' => 'ผู้ดูอย่างเดียว (Viewer)',
                'display_name_en' => 'Viewer',
                'description' => 'ค้นหา HN ดูข้อมูลผู้ป่วย ดูรายการเอกสาร และเปิด Preview ตามสิทธิ์',
                'permissions' => [
                    'patients.lookup',
                    'patients.view',
                    'documents.view',
                    'documents.preview',
                ],
            ],
            'author' => [
                'display_name_th' => 'ผู้จัดทำเอกสาร (Author)',
                'display_name_en' => 'Author',
                'description' => 'สร้างเอกสาร อัปโหลดไฟล์ เพิ่ม Revision แก้ไข Metadata และส่งคำขอลบ',
                'permissions' => [
                    'patients.lookup',
                    'patients.view',
                    'documents.view',
                    'documents.preview',
                    'documents.download',
                    'documents.create',
                    'documents.edit_metadata',
                    'documents.create_revision',
                    'documents.request_delete',
                ],
            ],
            'supervisor' => [
                'display_name_th' => 'หัวหน้าหน่วยงาน (Supervisor)',
                'display_name_en' => 'Supervisor',
                'description' => 'ดู Revision ทั้งหมด อนุมัติคำขอลบ Soft Delete กู้คืนเอกสาร และดู Audit Log ของหน่วยงาน',
                'permissions' => [
                    'patients.lookup',
                    'patients.view',
                    'patients.delete',
                    'documents.view',
                    'documents.preview',
                    'documents.download',
                    'documents.create',
                    'documents.edit_metadata',
                    'documents.create_revision',
                    'documents.request_delete',
                    'revisions.view_all',
                    'delete_requests.review',
                    'documents.soft_delete',
                    'documents.restore',
                    'documents.permanent_delete',
                    'audit.view_department',
                ],
            ],
            'administrator' => [
                'display_name_th' => 'ผู้ดูแลระบบ (Administrator)',
                'display_name_en' => 'Administrator',
                'description' => 'จัดการผู้ใช้งาน บทบาท สิทธิ์ ประเภทเอกสาร Branding การตั้งค่าระบบ และ Audit Log ทั้งระบบ',
                'permissions' => array_keys($permissionMap),
            ],
        ];

        foreach ($rolesConfig as $name => $cfg) {
            $role = Role::query()->updateOrCreate(
                ['name' => $name],
                [
                    'display_name_th' => $cfg['display_name_th'],
                    'display_name_en' => $cfg['display_name_en'],
                    'description' => $cfg['description'],
                    'is_system' => true,
                ]
            );

            $ids = array_map(static fn (string $permName) => $permissionMap[$permName], $cfg['permissions']);
            $role->permissions()->sync($ids);
        }
    }
}
