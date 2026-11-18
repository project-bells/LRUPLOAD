@extends('layouts.app')

@section('title', 'ตรวจสอบข้อมูลก่อนบันทึกเอกสาร')

@section('breadcrumbs')
    <span>/</span>
    <a href="{{ route('patients.show', $patient) }}" class="hover:text-[#004F85]">HN {{ $patient->hn }}</a>
    <span>/</span>
    <span class="text-[#004F85] font-medium">ตรวจสอบก่อนบันทึก</span>
@endsection

@section('content')
<div class="space-y-6" x-data="{ submitting: false }">
    <!-- Official Aikchol Review Header -->
    <div class="aikchol-card p-6 border-t-4 border-t-[#004F85]">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-100">
            <div class="flex items-center gap-4">
                <img src="{{ route('branding.logo') }}" alt="{{ $branding->hospital_name_en }}" class="h-11 w-auto object-contain">
                <div>
                    <p class="text-xs font-semibold text-[#004F85]">{{ $branding->hospital_name_en }} • {{ $branding->department_name_th }}</p>
                    <h2 class="text-lg font-bold text-slate-900">ขั้นตอนที่ 2/2: ตรวจสอบความถูกต้องก่อนยืนยันบันทึก</h2>
                </div>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-[#FFF8E6] text-[#9A6000] border border-[#FBB435]">
                ยังไม่ได้บันทึกลงฐานข้อมูล
            </span>
        </div>

        <!-- Patient & Relationship Summary -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-5 text-sm">
            <div class="p-4 rounded-xl bg-[#EBF4FA]/50 border border-[#DCE6EF]">
                <h3 class="text-xs font-bold uppercase tracking-wider text-[#004F85] mb-2">ข้อมูลผู้ป่วย (HIS Snapshot)</h3>
                <dl class="space-y-1.5 text-xs">
                    <div class="flex justify-between"><dt class="text-slate-500">HN:</dt><dd class="font-mono font-bold text-[#004F85]">{{ $patient->hn }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">ชื่อ–นามสกุล:</dt><dd class="font-semibold text-slate-800">{{ $patient->display_name }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">ประเภทผู้ป่วย:</dt><dd class="font-medium">{{ $patient->patient_type?->labelTh() }}</dd></div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">ความสัมพันธ์มารดา–ทารก:</dt>
                        <dd class="font-medium">
                            @if($relatedPatient)
                                HN {{ $relatedPatient->hn }} ({{ $relatedPatient->display_name }})
                            @else
                                ไม่มีผู้เกี่ยวข้องเพิ่มเติม
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="p-4 rounded-xl bg-slate-50 border border-[#DCE6EF]">
                <h3 class="text-xs font-bold uppercase tracking-wider text-[#004F85] mb-2">รายละเอียดเอกสาร & ไฟล์แนบ</h3>
                <dl class="space-y-1.5 text-xs">
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">ชื่อเอกสาร:</dt><dd class="font-semibold text-slate-800 text-right">{{ $metadata['title'] }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">ประเภทเอกสาร:</dt><dd class="font-medium">{{ $documentType->name_th }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">วันที่เอกสาร:</dt><dd>{{ $metadata['document_date'] }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">ชื่อไฟล์เดิม:</dt><dd class="font-mono truncate max-w-[220px]">{{ $file['original_name'] }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">ชนิด / ขนาดไฟล์:</dt><dd>{{ strtoupper($file['extension']) }} ({{ $file['mime_type'] }}) • {{ number_format($file['file_size'] / 1024, 1) }} KB</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">SHA-256:</dt><dd class="font-mono text-[10px] truncate max-w-[200px]" title="{{ $file['sha256_hash'] }}">{{ $file['sha256_hash'] }}</dd></div>
                </dl>
            </div>
        </div>
    </div>

    <!-- Live Document Preview (PDF.js / Image) -->
    <div class="aikchol-card p-5">
        <h3 class="text-sm font-bold text-[#004F85] mb-3">ตัวอย่างไฟล์เอกสารก่อนบันทึก</h3>
        <x-pdf-preview-box
            :stream-url="$streamUrl"
            :mime-type="$file['mime_type']"
            :extension="$file['extension']"
            :original-name="$file['original_name']"
            :file-size-formatted="number_format($file['file_size'] / 1024, 1) . ' KB'"
        />
    </div>

    <!-- Confirm / Back Actions -->
    <div class="aikchol-card p-5 flex flex-col sm:flex-row items-center justify-between gap-3">
        <a href="{{ route('documents.create', $patient) }}" class="w-full sm:w-auto text-center px-5 py-2.5 rounded-lg text-sm font-semibold border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
            ← ย้อนกลับไปแก้ไข
        </a>

        <form method="POST" action="{{ route('documents.confirm') }}" @submit="submitting = true" class="w-full sm:w-auto">
            @csrf
            <input type="hidden" name="staging_token" value="{{ $stagingToken }}">
            <input type="hidden" name="idempotency_token" value="{{ $idempotencyToken }}">
            <button
                type="submit"
                :disabled="submitting"
                class="aikchol-btn-primary w-full sm:w-auto px-7 py-3 rounded-lg text-sm font-bold shadow cursor-pointer disabled:opacity-60"
            >
                <span x-text="submitting ? 'กำลังบันทึกข้อมูลและสร้าง Revision 1...' : '✓ ยืนยันและบันทึกเอกสาร (สร้าง Revision 1)'">
                    ✓ ยืนยันและบันทึกเอกสาร (สร้าง Revision 1)
                </span>
            </button>
        </form>
    </div>
</div>
@endsection
