@extends('layouts.app')

@section('title', 'ค้นหา HN ผู้ป่วย (HIS)')

@section('breadcrumbs')
    <span>/</span>
    <span class="text-[#004F85] font-medium">ค้นหา HN ผู้ป่วย</span>
@endsection

@section('content')
<div class="space-y-6" x-data="{ searching: false }">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold text-[#004F85]">ค้นหาข้อมูลผู้ป่วยด้วย HN (จาก SQL Server 2014)</h2>
            <p class="text-xs text-slate-500 mt-0.5">
                เชื่อมต่อแบบ Read-Only ด้วย Parameter Binding • ระบบจะไม่บันทึกข้อมูลผู้ป่วยลง MySQL จนกว่าท่านจะกดเลือกผู้ป่วย
            </p>
        </div>
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold {{ $hisEnabled ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-800 border border-amber-200' }}">
            <span class="w-2 h-2 rounded-full {{ $hisEnabled ? 'bg-emerald-500' : 'bg-[#FBB435]' }}"></span>
            {{ $hisEnabled ? 'SQL Server 2014 พร้อมเชื่อมต่อ' : 'SQL Server 2014 ยังปิดอยู่ (PATIENT_DB_ENABLED=false)' }}
        </span>
    </div>

    <!-- Search Card -->
    <div class="aikchol-card p-6">
        <form method="POST" action="{{ route('patients.lookup') }}" @submit="searching = true" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
            @csrf
            <div class="md:col-span-6">
                <label for="hn" class="block text-sm font-medium text-slate-700 mb-1">หมายเลข HN ผู้ป่วย (มารดา หรือ ทารก)</label>
                <input
                    id="hn"
                    name="hn"
                    type="text"
                    value="{{ old('hn') }}"
                    required
                    autocomplete="off"
                    placeholder="กรอก HN เช่น 66-012345 หรือ 00123456"
                    class="aikchol-input font-mono"
                >
            </div>

            <div class="md:col-span-3">
                <label for="patient_type" class="block text-sm font-medium text-slate-700 mb-1">ประเภทผู้ป่วย</label>
                <select id="patient_type" name="patient_type" class="aikchol-input">
                    <option value="auto" @selected(($searchedPatientType ?? 'auto') === 'auto')>เลือกอัตโนมัติ</option>
                    <option value="mother" @selected(($searchedPatientType ?? '') === 'mother')>มารดา</option>
                    <option value="child" @selected(($searchedPatientType ?? '') === 'child')>ทารก</option>
                </select>
            </div>

            <div class="md:col-span-3">
                <button
                    type="submit"
                    :disabled="searching"
                    class="aikchol-btn-primary w-full py-2.5 px-4 rounded-lg text-sm font-semibold flex items-center justify-center gap-2 cursor-pointer disabled:opacity-60"
                >
                    <svg x-show="searching" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span x-text="searching ? 'กำลังค้นหาจาก HIS...' : 'ค้นหาผู้ป่วย'">ค้นหาผู้ป่วย</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Search Result from SQL Server 2014 -->
    @if($result !== null)
        @if($result->isFound() && $result->patient !== null)
            @php $p = $result->patient; @endphp
            <div class="aikchol-card overflow-hidden border-[#3EC0C8]">
                <div class="bg-[#EBF4FA] px-6 py-4 border-b border-[#DCE6EF] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div class="flex items-center gap-2.5">
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $p->patientType === 'mother' ? 'bg-[#004F85] text-white' : 'bg-[#3EC0C8] text-slate-950' }}">
                            {{ $p->patientType === 'mother' ? 'มารดา (Mother)' : 'ทารก (Child)' }}
                        </span>
                        <h3 class="text-base font-bold text-[#004F85]">ผลการค้นหาจากระบบ HIS</h3>
                    </div>
                    <span class="text-xs text-slate-500">
                        ดึงข้อมูลเมื่อ: {{ $result->sourceFetchedAt ? \Carbon\Carbon::parse($result->sourceFetchedAt)->format('d/m/Y H:i:s') : now()->format('d/m/Y H:i:s') }}
                    </span>
                </div>

                <div class="p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 text-sm">
                        <div>
                            <p class="text-xs text-slate-500">HN</p>
                            <p class="font-mono font-bold text-lg text-[#004F85]">{{ $p->hn }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">ชื่อ–นามสกุล</p>
                            <p class="font-semibold text-slate-800">{{ $p->displayName() }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">วันเดือนปีเกิด / เพศ</p>
                            <p class="font-medium text-slate-800">{{ $p->birthDate ?? '-' }} • {{ $p->sex ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">เลขประจำตัวประชาชน (Masked)</p>
                            <p class="font-mono text-slate-700">{{ $p->nationalIdMasked ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">AN / Ward</p>
                            <p class="text-slate-800">{{ $p->admissionNumber ?? '-' }} / {{ $p->ward ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">ความสัมพันธ์จาก HIS (HN มารดา / ทารก)</p>
                            <p class="font-mono text-slate-800">
                                @if($p->motherHn) มารดา: {{ $p->motherHn }} @endif
                                @if($p->childHn) ทารก: {{ $p->childHn }} @endif
                                @if(!$p->motherHn && !$p->childHn) - @endif
                            </p>
                        </div>
                    </div>

                    @if($p->sourceUpdatedAt)
                        <p class="text-xs text-slate-400 mt-4">วันที่ข้อมูลต้นทางได้รับการปรับปรุง: {{ $p->sourceUpdatedAt }}</p>
                    @endif

                    <!-- Action Buttons: Select Patient (Creates/Updates Patient Snapshot) -->
                    <div class="mt-6 pt-5 border-t border-slate-100" x-data="{ selectedType: @js($p->patientType) }">
                        <div class="mb-4 max-w-xs">
                            <label class="block text-xs font-medium text-slate-600 mb-1">ยืนยันประเภทผู้ป่วยก่อนบันทึก Snapshot</label>
                            <select x-model="selectedType" class="aikchol-input text-xs">
                                <option value="mother">มารดา (Mother)</option>
                                <option value="child">ทารก (Child)</option>
                            </select>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <form method="POST" action="{{ route('patients.select') }}">
                                @csrf
                                <input type="hidden" name="selection_token" value="{{ $selectionToken }}">
                                <input type="hidden" name="patient_type" :value="selectedType">
                                <input type="hidden" name="next_action" value="profile">
                                <button type="submit" class="aikchol-btn-primary px-5 py-2.5 rounded-lg text-sm font-semibold shadow-sm cursor-pointer">
                                    ยืนยันเลือกผู้ป่วย (ดูทะเบียน & เอกสารเดิม)
                                </button>
                            </form>

                            @if(auth()->user()->hasPermission('documents.create'))
                                <form method="POST" action="{{ route('patients.select') }}">
                                    @csrf
                                    <input type="hidden" name="selection_token" value="{{ $selectionToken }}">
                                    <input type="hidden" name="patient_type" :value="selectedType">
                                    <input type="hidden" name="next_action" value="create_document">
                                    <button type="submit" class="aikchol-btn-secondary px-5 py-2.5 rounded-lg text-sm font-semibold shadow-sm cursor-pointer">
                                        เลือกผู้ป่วยและเพิ่มเอกสารใหม่ทันที →
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @else
            <!-- Friendly Error / Not Found / SQL Server Unavailable State -->
            <div class="aikchol-card p-6 border-amber-300 bg-amber-50/60">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <p class="text-sm font-bold text-amber-900">
                            {{ $result->status === \App\Enums\LookupStatus::NotFound ? 'ไม่พบข้อมูลผู้ป่วย' : 'ไม่สามารถดึงข้อมูลจากระบบ HIS ได้' }}
                        </p>
                        <p class="text-sm text-amber-800 mt-1">{{ $result->displayMessage() }}</p>
                    </div>
                    <a href="{{ route('patients.lookup.index') }}" class="inline-flex items-center justify-center px-4 py-2 rounded-lg text-xs font-semibold bg-white border border-amber-300 text-amber-900 hover:bg-amber-100">
                        ลองใหม่อีกครั้ง
                    </a>
                </div>
            </div>
        @endif
    @endif

    <!-- Existing Local Snapshot Match (Resilience when HIS is offline) -->
    @if($existingSnapshot !== null)
        <div class="aikchol-card p-5 border-[#004F85]/30 bg-[#EBF4FA]/40">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold text-[#004F85]">พบประวัติ Patient Snapshot เดิมในระบบเอกสาร (MySQL)</p>
                    <p class="text-sm font-bold text-slate-800 mt-0.5">
                        {{ $existingSnapshot->display_name }} (HN: <span class="font-mono">{{ $existingSnapshot->hn }}</span>)
                    </p>
                    <p class="text-xs text-slate-500">อัปเดต Snapshot ล่าสุด: {{ $existingSnapshot->source_fetched_at?->format('d/m/Y H:i') ?? '-' }}</p>
                </div>
                <a href="{{ route('patients.show', $existingSnapshot) }}" class="aikchol-btn-primary px-4 py-2 rounded-lg text-xs font-semibold">
                    เปิดดูเอกสารเดิมในระบบ →
                </a>
            </div>
        </div>
    @endif

    <!-- Offline Resilience Helper (shown ONLY when SQL Server 2014 is disabled or unavailable) -->
    @php
        $showOfflineFallback = ! $hisEnabled || (
            $result !== null && in_array($result->status, [
                \App\Enums\LookupStatus::ConnectionFailed,
                \App\Enums\LookupStatus::Timeout,
                \App\Enums\LookupStatus::UnexpectedError,
            ], true)
        );
    @endphp
    @if($showOfflineFallback)
        <div class="aikchol-card p-5">
            <h3 class="text-sm font-semibold text-slate-800">ค้นหาเอกสารเดิมในฐานข้อมูลระบบเอกสาร (กรณี SQL Server 2014 ไม่พร้อมใช้งาน)</h3>
            <p class="text-xs text-slate-500 mt-0.5 mb-3">ค้นหาผู้ป่วยที่เคยถูกจัดเก็บ Snapshot ไว้แล้วใน MySQL ด้วย HN Hash</p>
            <form method="POST" action="{{ route('patients.search-local') }}" class="flex flex-col sm:flex-row gap-3 max-w-lg">
                @csrf
                <input type="text" name="hn" required placeholder="กรอก HN เพื่อเปิดเอกสารเดิมใน MySQL" class="aikchol-input font-mono text-sm">
                <button type="submit" class="px-4 py-2 rounded-lg text-xs font-semibold bg-slate-800 text-white hover:bg-slate-700 shrink-0 cursor-pointer">
                    ค้นหาใน MySQL
                </button>
            </form>
        </div>
    @endif
</div>
@endsection
