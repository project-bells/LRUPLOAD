@extends('layouts.app')

@section('title', 'ส่งคำขอลบเอกสาร — ' . $document->document_number)

@section('breadcrumbs')
    <span>/</span>
    <a href="{{ route('documents.show', $document) }}" class="hover:text-[#004F85]">{{ $document->document_number }}</a>
    <span>/</span>
    <span class="text-[#004F85] font-medium">ส่งคำขอลบเอกสาร</span>
@endsection

@section('content')
<div class="max-w-2xl space-y-6">
    <div>
        <h2 class="text-xl font-bold text-red-800">ส่งคำขอลบเอกสาร</h2>
        <p class="text-xs text-slate-500 mt-0.5">ผู้จัดทำไม่สามารถลบเอกสารโดยตรงได้ คำขอจะถูกส่งไปยังหัวหน้าหน่วยงานหรือผู้ดูแลระบบเพื่อพิจารณาอนุมัติ Soft Delete</p>
    </div>

    <div class="aikchol-card p-5 bg-slate-50 text-xs space-y-1">
        <p class="text-sm font-bold text-slate-900">{{ $document->title }}</p>
        <p>เลขที่เอกสาร: <span class="font-mono font-semibold text-[#004F85]">{{ $document->document_number }}</span> • ประเภท: {{ $document->documentType?->name_th }}</p>
    </div>

    <form method="POST" action="{{ route('documents.delete-request.store', $document) }}" class="aikchol-card p-6 space-y-4">
        @csrf

        <div>
            <label for="request_reason" class="block text-sm font-medium text-slate-700 mb-1">เหตุผลในการขอลบเอกสาร <span class="text-red-600">*</span></label>
            <textarea id="request_reason" name="request_reason" rows="4" required minlength="5" placeholder="ระบุรายละเอียดเหตุผลอย่างชัดเจน เช่น สร้างเอกสารซ้ำซ้อน / อัปโหลดผิดผู้ป่วย" class="aikchol-input">{{ old('request_reason') }}</textarea>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('documents.show', $document) }}" class="px-4 py-2 rounded-lg text-sm border border-slate-300 bg-white text-slate-700">ยกเลิก</a>
            <button type="submit" class="px-5 py-2 rounded-lg text-sm font-semibold bg-red-700 text-white hover:bg-red-800 cursor-pointer">
                ยืนยันส่งคำขอลบ
            </button>
        </div>
    </form>
</div>
@endsection
