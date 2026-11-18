@extends('layouts.app')

@section('title', 'ตั้งค่าระบบและสถานะ SQL Server 2014')

@section('breadcrumbs')
    <span>/</span>
    <span class="text-[#004F85] font-medium">ตั้งค่าระบบ</span>
@endsection

@section('content')
<div class="max-w-4xl space-y-6">
    <div>
        <h2 class="text-xl font-bold text-[#004F85]">ตั้งค่าระบบและสถานะการเชื่อมต่อ SQL Server 2014</h2>
        <p class="text-xs text-slate-500 mt-0.5">
            การตั้งค่าเอกสารมีผลบังคับใช้ทันที • ค่าเชื่อมต่อ SQL Server 2014 จัดการผ่านไฟล์ <code class="font-mono">.env</code> บนเซิร์ฟเวอร์เท่านั้นเพื่อไม่เก็บ Credential ซ้ำซ้อนลง MySQL
        </p>
    </div>

    <!-- Editable Runtime Document & Deletion Policy -->
    @php
        $currentRetentionDays = (int) ($settings->get('soft_delete_retention_days')?->value_plain ?? 0);
    @endphp
    <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="aikchol-card p-6 space-y-5" x-data="{ retentionDays: {{ $currentRetentionDays }} }">
            <h3 class="text-sm font-bold text-[#004F85]">นโยบายความปลอดภัยเอกสารและถังเอกสาร (Soft Deleted Documents)</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">ขนาดไฟล์สูงสุดที่อนุญาต (KB)</label>
                    <input type="number" name="max_file_size_kb" value="{{ old('max_file_size_kb', $settings->get('max_file_size_kb')?->value_plain ?? 20480) }}" min="512" max="102400" required class="aikchol-input text-sm">
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
                        ตั้งค่าเป็น <code class="font-mono">0</code> เพื่อปิดการลบอัตโนมัติ หรือเลือกจำนวนวัน เช่น 30, 45, 90 วัน (รันอัตโนมัติทุกวันเวลา 02:00 น. และยังคงเก็บ Audit Log ไว้เสมอ)
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

            <!-- Re-authentication for privileged setting changes (SEC-M-03) -->
            <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div class="max-w-sm flex-1">
                    <label for="current_password" class="block text-xs font-semibold text-slate-700 mb-1">ยืนยันรหัสผ่านผู้ดูแลระบบปัจจุบัน *</label>
                    <input id="current_password" type="password" name="current_password" required autocomplete="current-password" placeholder="กรอกรหัสผ่านปัจจุบันเพื่อบันทึกการเปลี่ยนแปลง" class="aikchol-input text-sm">
                </div>
                <button type="submit" class="aikchol-btn-primary px-6 py-2.5 rounded-lg text-sm font-semibold shadow-sm cursor-pointer">
                    บันทึกการตั้งค่าระบบ
                </button>
            </div>
        </div>
    </form>

    <!-- Read-Only SQL Server 2014 Environment Status (Option 1 — SEC-H-05) -->
    <div class="aikchol-card p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h3 class="text-sm font-bold text-[#004F85]">สถานะการเชื่อมต่อ Microsoft SQL Server 2014 (Read-Only จาก .env)</h3>
                <p class="text-xs text-slate-500 mt-0.5">Connection Name: <code class="font-mono">patient_sqlsrv</code> • แสดงเฉพาะสถานะการตั้งค่า ไม่เปิดเผย IP, Username หรือ Password</p>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-bold {{ $hisEnvStatus['enabled'] ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-amber-100 text-amber-800 border border-amber-200' }}">
                {{ $hisEnvStatus['enabled'] ? 'เปิดใช้งาน (Enabled)' : 'ปิดใช้งาน (Disabled)' }}
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
            <div class="p-3 rounded-lg bg-slate-50 border border-slate-200/80">
                <p class="text-slate-500">PHP Driver (`pdo_sqlsrv`)</p>
                <p class="font-bold mt-0.5 {{ $hisEnvStatus['pdo_sqlsrv_loaded'] ? 'text-emerald-700' : 'text-red-700' }}">
                    {{ $hisEnvStatus['pdo_sqlsrv_loaded'] ? 'พร้อมใช้งาน (Loaded)' : 'ยังไม่ได้ติดตั้ง/เปิดใช้งาน' }}
                </p>
            </div>
            <div class="p-3 rounded-lg bg-slate-50 border border-slate-200/80">
                <p class="text-slate-500">Host / Database / Read-Only Account</p>
                <p class="font-bold mt-0.5 {{ ($hisEnvStatus['host_configured'] && $hisEnvStatus['database_configured'] && $hisEnvStatus['credentials_configured']) ? 'text-emerald-700' : 'text-amber-700' }}">
                    {{ ($hisEnvStatus['host_configured'] && $hisEnvStatus['database_configured'] && $hisEnvStatus['credentials_configured']) ? 'กำหนดค่าใน .env ครบแล้ว' : 'ยังตั้งค่าใน .env ไม่ครบ' }}
                </p>
            </div>
            <div class="p-3 rounded-lg bg-slate-50 border border-slate-200/80">
                <p class="text-slate-500">Driver / Port</p>
                <p class="font-mono font-bold text-slate-800 mt-0.5">{{ $hisEnvStatus['driver'] }} : {{ $hisEnvStatus['port'] }}</p>
            </div>
            <div class="p-3 rounded-lg bg-slate-50 border border-slate-200/80">
                <p class="text-slate-500">TLS Encrypt (`PATIENT_DB_ENCRYPT`)</p>
                <p class="font-mono font-bold mt-0.5 {{ $hisEnvStatus['encrypt'] === 'yes' ? 'text-emerald-700' : 'text-red-700' }}">{{ $hisEnvStatus['encrypt'] }}</p>
            </div>
            <div class="p-3 rounded-lg bg-slate-50 border border-slate-200/80">
                <p class="text-slate-500">Trust Server Certificate</p>
                <p class="font-mono font-bold mt-0.5 {{ $hisEnvStatus['trust_cert'] === 'false' ? 'text-emerald-700' : 'text-amber-700' }}">{{ $hisEnvStatus['trust_cert'] }}</p>
            </div>
            <div class="p-3 rounded-lg bg-slate-50 border border-slate-200/80">
                <p class="text-slate-500">Login / Query Timeout</p>
                <p class="font-mono font-bold text-slate-800 mt-0.5">{{ $hisEnvStatus['login_timeout'] }}s / {{ $hisEnvStatus['query_timeout'] }}s</p>
            </div>
        </div>

        <div class="p-3.5 rounded-lg bg-[#EBF4FA]/60 border border-[#DCE6EF] text-xs text-slate-700">
            <strong>แนวทางจัดการสำหรับผู้ดูแลเซิร์ฟเวอร์:</strong> หากต้องการปรับเปลี่ยน Host, Database, รหัสผ่าน Read-Only หรือนโยบาย TLS ให้แก้ไขในไฟล์ <code class="font-mono">.env</code> บนเซิร์ฟเวอร์ตามคู่มือ <code class="font-mono">docs/SQL_SERVER_2014_INTEGRATION_GUIDE.md</code> แล้วรัน <code class="font-mono">php artisan config:clear</code>
        </div>
    </div>
</div>
@endsection
