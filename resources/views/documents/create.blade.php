@extends('layouts.app')

@section('title', 'เพิ่มเอกสารผู้ป่วย — HN ' . $patient->hn)

@section('breadcrumbs')
    <span>/</span>
    <a href="{{ route('patients.show', $patient) }}" class="hover:text-[#004F85]">HN {{ $patient->hn }}</a>
    <span>/</span>
    <span class="text-[#004F85] font-medium">เพิ่มเอกสารใหม่</span>
@endsection

@section('content')
<div class="max-w-4xl space-y-6">
    <div>
        <h2 class="text-xl font-bold text-[#004F85]">เพิ่มเอกสารผู้ป่วย (ขั้นตอนที่ 1/2: กรอกข้อมูลและอัปโหลดไฟล์)</h2>
        <p class="text-xs text-slate-500 mt-0.5">
            ข้อมูลผู้ป่วยที่ดึงจาก HIS ถูกกำหนดเป็น Read-Only • หลังกดดำเนินการต่อ ระบบจะแสดงหน้าตรวจสอบพร้อมตัวอย่างเอกสารก่อนบันทึกจริง
        </p>
    </div>

    <form method="POST" action="{{ route('documents.stage') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        <input type="hidden" name="patient_id" value="{{ $patient->id }}">

        <!-- Read-Only Patient Information from HIS Snapshot -->
        <div class="aikchol-card p-6 bg-[#EBF4FA]/40">
            <h3 class="text-sm font-bold text-[#004F85] mb-3">ข้อมูลผู้ป่วยจากระบบ HIS (Read-Only)</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">HN</label>
                    <input type="text" value="{{ $patient->hn }}" readonly class="aikchol-input aikchol-input-readonly font-mono font-bold">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">ชื่อผู้ป่วย</label>
                    <input type="text" value="{{ $patient->display_name }}" readonly class="aikchol-input aikchol-input-readonly">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">ประเภทผู้ป่วย</label>
                    <input type="text" value="{{ $patient->patient_type?->labelTh() }}" readonly class="aikchol-input aikchol-input-readonly">
                </div>
            </div>

            @if($relatedPatients->isNotEmpty())
                <div class="mt-4">
                    <label for="related_patient_id" class="block text-xs font-medium text-slate-700 mb-1">
                        HN {{ $patient->patient_type === \App\Enums\PatientType::Mother ? 'ทารก' : 'มารดา' }}ที่เกี่ยวข้อง (หากเอกสารเกี่ยวข้องร่วมกัน)
                    </label>
                    <select id="related_patient_id" name="related_patient_id" class="aikchol-input">
                        <option value="">-- ไม่ระบุผู้เกี่ยวข้องเพิ่มเติม --</option>
                        @foreach($relatedPatients as $rel)
                            <option value="{{ $rel->id }}" @selected((string) old('related_patient_id') === (string) $rel->id)>
                                HN {{ $rel->hn }} — {{ $rel->display_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        <!-- Document Metadata -->
        <div class="aikchol-card p-6 space-y-4">
            <h3 class="text-sm font-bold text-[#004F85]">ข้อมูลรายละเอียดเอกสาร</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="document_type_id" class="block text-sm font-medium text-slate-700 mb-1">ประเภทเอกสาร <span class="text-red-600">*</span></label>
                    <select id="document_type_id" name="document_type_id" required class="aikchol-input">
                        <option value="">-- เลือกประเภทเอกสาร --</option>
                        @foreach($documentTypes as $type)
                            <option value="{{ $type->id }}" @selected((string) old('document_type_id') === (string) $type->id)>{{ $type->name_th }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="patient_owner_type" class="block text-sm font-medium text-slate-700 mb-1">เจ้าของเอกสาร <span class="text-red-600">*</span></label>
                    <select id="patient_owner_type" name="patient_owner_type" required class="aikchol-input">
                        @foreach($ownerTypes as $owner)
                            <option value="{{ $owner->value }}" @selected(old('patient_owner_type', $patient->patient_type?->value ?? 'mother') === $owner->value)>
                                {{ $owner->labelTh() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label for="title" class="block text-sm font-medium text-slate-700 mb-1">ชื่อเอกสาร <span class="text-red-600">*</span></label>
                    <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="255" placeholder="เช่น ใบยินยอมการคลอด, ใบบันทึกการคลอด (Partograph)" class="aikchol-input">
                </div>

                <div>
                    <label for="document_date" class="block text-sm font-medium text-slate-700 mb-1">วันที่เอกสาร <span class="text-red-600">*</span></label>
                    <input id="document_date" name="document_date" type="date" value="{{ old('document_date', now()->format('Y-m-d')) }}" required class="aikchol-input">
                </div>

                <div>
                    <label for="received_date" class="block text-sm font-medium text-slate-700 mb-1">วันที่รับเอกสาร</label>
                    <input id="received_date" name="received_date" type="date" value="{{ old('received_date', now()->format('Y-m-d')) }}" class="aikchol-input">
                </div>

                <div>
                    <label for="reference_number" class="block text-sm font-medium text-slate-700 mb-1">เลขที่เอกสารอ้างอิง (ถ้ามี)</label>
                    <input id="reference_number" name="reference_number" type="text" value="{{ old('reference_number') }}" maxlength="100" class="aikchol-input">
                </div>

                <div>
                    <label for="confidentiality_level" class="block text-sm font-medium text-slate-700 mb-1">ระดับความลับ <span class="text-red-600">*</span></label>
                    <select id="confidentiality_level" name="confidentiality_level" required class="aikchol-input">
                        @foreach($confidentialityLevels as $lvl)
                            <option value="{{ $lvl->value }}" @selected(old('confidentiality_level', 'confidential') === $lvl->value)>
                                {{ $lvl->labelTh() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label for="description" class="block text-sm font-medium text-slate-700 mb-1">รายละเอียด</label>
                    <textarea id="description" name="description" rows="2" class="aikchol-input">{{ old('description') }}</textarea>
                </div>

                <div class="sm:col-span-2">
                    <label for="notes" class="block text-sm font-medium text-slate-700 mb-1">หมายเหตุ</label>
                    <textarea id="notes" name="notes" rows="2" class="aikchol-input">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        <!-- File Upload -->
        <div class="aikchol-card p-6">
            <h3 class="text-sm font-bold text-[#004F85] mb-1">ไฟล์แนบเอกสาร <span class="text-red-600">*</span></h3>
            <p class="text-xs text-slate-500 mb-3">
                รองรับไฟล์ PDF (แนะนำ), JPG, JPEG, PNG, DOCX, XLSX ขนาดไม่เกิน {{ number_format((int) config('documents.max_file_size_kb', 20480) / 1024, 0) }} MB • ตรวจสอบ MIME Type และคำนวณ SHA-256 อัตโนมัติ
            </p>
            <input
                type="file"
                name="file"
                required
                accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx"
                class="block w-full text-sm text-slate-700 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#EBF4FA] file:text-[#004F85] hover:file:bg-[#DCEAF5] border border-dashed border-[#004F85]/40 rounded-xl p-4 bg-slate-50/70 cursor-pointer"
            >

            <div class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                <span>ผู้จัดทำ: <strong class="text-slate-700">{{ auth()->user()->name }}</strong></span>
                <span>เวลาดำเนินการ: {{ now()->format('d/m/Y H:i') }}</span>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('patients.show', $patient) }}" class="px-4 py-2.5 rounded-lg text-sm font-medium border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
                ยกเลิก
            </a>
            <button type="submit" class="aikchol-btn-primary px-6 py-2.5 rounded-lg text-sm font-semibold shadow-sm cursor-pointer">
                ถัดไป: ตรวจสอบและดูตัวอย่างเอกสาร →
            </button>
        </div>
    </form>
</div>
@endsection
