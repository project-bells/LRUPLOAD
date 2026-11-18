@extends('layouts.app')

@section('title', 'เพิ่ม Revision ใหม่ — ' . $document->document_number)

@section('breadcrumbs')
    <span>/</span>
    <a href="{{ route('documents.show', $document) }}" class="hover:text-[#004F85]">{{ $document->document_number }}</a>
    <span>/</span>
    <span class="text-[#004F85] font-medium">เพิ่ม Revision</span>
@endsection

@section('content')
<div class="max-w-3xl space-y-6">
    <div>
        <h2 class="text-xl font-bold text-[#004F85]">เพิ่ม Revision ใหม่ (ไม่เขียนทับไฟล์เดิม)</h2>
        <p class="text-xs text-slate-500 mt-0.5">
            Revision ปัจจุบัน (Rev. {{ $document->currentRevision?->revision_number ?? 1 }}) จะถูกเปลี่ยนสถานะเป็น Superseded และเก็บไฟล์เดิมไว้อ้างอิงย้อนหลัง
        </p>
    </div>

    <div class="aikchol-card p-5 bg-[#EBF4FA]/40 text-xs space-y-1">
        <p class="font-bold text-[#004F85] text-sm">{{ $document->title }}</p>
        <p class="text-slate-600">เลขที่เอกสาร: <span class="font-mono font-semibold">{{ $document->document_number }}</span> • ประเภท: {{ $document->documentType?->name_th }}</p>
        <p class="text-slate-600">ไฟล์ปัจจุบัน (Rev. {{ $document->currentRevision?->revision_number }}): {{ $document->currentRevision?->storedFile?->original_name }}</p>
    </div>

    <form method="POST" action="{{ route('documents.revisions.stage', $document) }}" enctype="multipart/form-data" class="aikchol-card p-6 space-y-4">
        @csrf

        <div>
            <label for="change_reason" class="block text-sm font-medium text-slate-700 mb-1">เหตุผลในการเพิ่ม Revision / แก้ไขเอกสาร <span class="text-red-600">*</span></label>
            <textarea id="change_reason" name="change_reason" rows="3" required minlength="5" placeholder="เช่น อัปโหลดสแกนหน้าเอกสารที่ขาดหาย / แก้ไขลายเซ็นยืนยันความยินยอม" class="aikchol-input">{{ old('change_reason') }}</textarea>
        </div>

        <div>
            <label for="notes" class="block text-sm font-medium text-slate-700 mb-1">หมายเหตุเพิ่มเติม (ถ้ามี)</label>
            <textarea id="notes" name="notes" rows="2" class="aikchol-input">{{ old('notes') }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">ไฟล์เอกสารฉบับใหม่ <span class="text-red-600">*</span></label>
            <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx" class="block w-full text-sm text-slate-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#EBF4FA] file:text-[#004F85] border border-dashed border-[#004F85]/40 rounded-xl p-4 bg-slate-50/70">
        </div>

        <div class="pt-3 flex justify-end gap-3">
            <a href="{{ route('documents.show', $document) }}" class="px-4 py-2.5 rounded-lg text-sm border border-slate-300 bg-white text-slate-700">ยกเลิก</a>
            <button type="submit" class="aikchol-btn-primary px-6 py-2.5 rounded-lg text-sm font-semibold cursor-pointer">
                ถัดไป: ตรวจสอบและดูตัวอย่างไฟล์ →
            </button>
        </div>
    </form>
</div>
@endsection
