@extends('layouts.app')

@section('title', 'ตั้งค่าระบบและ SQL Server 2014')

@section('breadcrumbs')
    <span>/</span>
    <span class="text-[#004F85] font-medium">ตั้งค่าระบบ</span>
@endsection

@section('content')
<div class="max-w-4xl space-y-6">
    <div>
        <h2 class="text-xl font-bold text-[#004F85]">ตั้งค่าระบบและการเชื่อมต่อ SQL Server 2014 (Read-Only)</h2>
        <p class="text-xs text-slate-500 mt-0.5">
            รหัสผ่าน SQL Server ถูกเข้ารหัสก่อนจัดเก็บและจะไม่ถูกแสดงกลับบนหน้าจอ • ค่าเริ่มต้นบังคับ TLS Encrypt=true และ TrustServerCertificate=false
        </p>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- Document & Deletion Policy -->
        @php
            $currentRetentionDays = (int) ($settings->get('soft_delete_retention_days')?->value_plain ?? 0);
        @endphp
        <div class="aikchol-card p-6 space-y-5" x-data="{ retentionDays: {{ $currentRetentionDays }} }">
            <h3 class="text-sm font-bold text-[#004F85]">นโยบายความปลอดภัยเอกสารและถังเอกสาร (Soft Deleted Documents)</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">ขนาดไฟล์สูงสุดที่อนุญาต (KB)</label>
                    <input type="number" name="max_file_size_kb" value="{{ $settings->get('max_file_size_kb')?->value_plain ?? 20480 }}" min="512" max="102400" required class="aikchol-input text-sm">
                </div>
                <div class="flex items-center pt-5">
                    <label class="inline-flex items-start gap-2.5 text-xs cursor-pointer">
                        <input type="checkbox" name="permanent_delete_enabled" value="1" @checked(($settings->get('permanent_delete_enabled')?->value_plain ?? '1') === '1') class="mt-0.5 rounded text-red-700">
                        <span>
                            <strong class="text-red-800">เปิดใช้งานการสั่งลบถาวร (Permanent Delete) จากถังเอกสาร</strong>
                            <span class="block text-slate-500">ใช้ได้เฉพาะหัวหน้าหน่วยงาน (Supervisor) และผู้ดูแลระบบ (Administrator) พร้อมยืนยันรหัสผ่านและไม่ลบ Audit Log</span>
                        </span>
                    </label>
                </div>
            </div>

            <!-- Soft Deleted Auto-Purge Retention Period -->
            <div class="pt-4 border-t border-slate-100 space-y-3">
                <div>
                    <label for="soft_delete_retention_days" class="block text-xs font-bold text-slate-800">
                        กำหนดระยะเวลาเก็บเอกสารในถังเอกสาร (Soft Deleted Documents) ก่อนลบถาวรอัตโนมัติ (วัน)
                    </label>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                        ตั้งค่าเป็น <code class="font-mono">0</code> เพื่อปิดการลบอัตโนมัติ หรือเลือกจำนวนวัน เช่น 30, 45, 90 วัน (ระบบจะรันคำสั่ง <code class="font-mono">documents:purge-expired-trash</code> ทุกวันเวลา 02:00 น. และยังคงเก็บ Audit Log ไว้เสมอ)
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @foreach([0 => 'ปิด (ไม่ลบอัตโนมัติ)', 30 => '30 วัน', 45 => '45 วัน', 90 => '90 วัน', 180 => '180 วัน', 365 => '365 วัน'] as $daysVal => $daysLabel)
                        <button
                            type="button"
                            @click="retentionDays = {{ $daysVal }}"
                            :class="Number(retentionDays) === {{ $daysVal }} ? 'bg-[#004F85] text-white border-[#004F85]' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition cursor-pointer"
                        >
                            {{ $daysLabel }}
                        </button>
                    @endforeach
                </div>

                <div class="max-w-xs">
                    <label class="block text-[11px] text-slate-500 mb-1">หรือระบุจำนวนวันเอง (0 – 3650 วัน)</label>
                    <div class="flex items-center gap-2">
                        <input
                            id="soft_delete_retention_days"
                            type="number"
                            name="soft_delete_retention_days"
                            x-model="retentionDays"
                            min="0"
                            max="3650"
                            required
                            class="aikchol-input text-sm font-mono"
                        >
                        <span class="text-xs text-slate-600 shrink-0">วัน</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- SQL Server 2014 Connection Settings -->
        <div class="aikchol-card p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-[#004F85]">การตั้งค่าเชื่อมต่อ SQL Server 2014 (Connection: patient_sqlsrv)</h3>
                <span class="text-xs font-mono text-slate-500">Driver: {{ $envSummary['patient_db_connection'] }}</span>
            </div>

            <div class="p-3.5 rounded-lg bg-[#EBF4FA]/60 border border-[#DCE6EF] text-xs text-slate-700">
                <strong>หมายเหตุการ Deploy จริง:</strong> แนะนำให้ตั้งค่า Credential ผ่านไฟล์ <code class="font-mono">.env</code> บนเซิร์ฟเวอร์ Production ที่จำกัดสิทธิ์ไฟล์ และใช้บัญชี SQL Server แบบ <strong>Read-Only</strong> เท่านั้น
            </div>

            <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-800">
                <input type="checkbox" name="patient_db_enabled" value="1" @checked(($settings->get('patient_db_enabled')?->value_plain ?? '0') === '1') class="rounded text-[#004F85]">
                <span>เปิดใช้งานการค้นหาผู้ป่วยจาก SQL Server 2014 (PATIENT_DB_ENABLED)</span>
            </label>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Host / Server Address</label>
                    <input type="text" name="patient_db_host" value="{{ $settings->get('patient_db_host')?->value_plain ?? '' }}" placeholder="ระบุจากทีม IT โรงพยาบาล" class="aikchol-input text-sm font-mono">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Port</label>
                    <input type="number" name="patient_db_port" value="{{ $settings->get('patient_db_port')?->value_plain ?? 1433 }}" required class="aikchol-input text-sm font-mono">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Database Name</label>
                    <input type="text" name="patient_db_database" value="{{ $settings->get('patient_db_database')?->value_plain ?? '' }}" class="aikchol-input text-sm font-mono">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Read-Only Username</label>
                    <input type="text" name="patient_db_username" value="{{ $settings->get('patient_db_username')?->value_plain ?? '' }}" autocomplete="off" class="aikchol-input text-sm font-mono">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Read-Only Password
                        <span class="text-slate-400 font-normal">
                            ({{ $hasPasswordSet ? 'มีรหัสผ่านบันทึกไว้แล้ว — เว้นว่างหากไม่ต้องการเปลี่ยน ระบบจะไม่แสดงรหัสผ่านเดิม' : 'ยังไม่ได้ตั้งรหัสผ่าน' }})
                        </span>
                    </label>
                    <input type="password" name="patient_db_password" value="" autocomplete="new-password" placeholder="กรอกเฉพาะเมื่อต้องการตั้งหรือเปลี่ยนรหัสผ่านใหม่" class="aikchol-input text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Login Timeout (วินาที)</label>
                    <input type="number" name="patient_db_login_timeout" value="{{ $settings->get('patient_db_login_timeout')?->value_plain ?? 5 }}" min="1" max="60" required class="aikchol-input text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Query Timeout (วินาที)</label>
                    <input type="number" name="patient_db_query_timeout" value="{{ $settings->get('patient_db_query_timeout')?->value_plain ?? 10 }}" min="1" max="120" required class="aikchol-input text-sm">
                </div>
            </div>

            <div class="pt-2 space-y-2 border-t border-slate-100">
                <label class="flex items-center gap-2 text-xs text-slate-800">
                    <input type="checkbox" name="patient_db_encrypt" value="1" @checked(($settings->get('patient_db_encrypt')?->value_plain ?? '1') === '1') class="rounded text-[#004F85]">
                    <span><strong>Encrypt Connection (TLS)</strong> — แนะนำให้เปิดไว้เสมอ (Default: true)</span>
                </label>
                <label class="flex items-center gap-2 text-xs text-slate-800">
                    <input type="checkbox" name="patient_db_trust_server_certificate" value="1" @checked(($settings->get('patient_db_trust_server_certificate')?->value_plain ?? '0') === '1') class="rounded text-amber-600">
                    <span><strong>Trust Server Certificate</strong> — ห้ามเปิดเป็นค่าเริ่มต้น (Default: false) เว้นแต่มีเอกสารอนุมัติจากทีม Security</span>
                </label>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="aikchol-btn-primary px-6 py-2.5 rounded-lg text-sm font-semibold shadow-sm cursor-pointer">
                บันทึกการตั้งค่าระบบ
            </button>
        </div>
    </form>
</div>
@endsection
