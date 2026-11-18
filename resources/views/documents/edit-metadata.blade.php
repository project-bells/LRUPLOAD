@extends('layouts.app')

@section('title', 'แก้ไข Metadata เอกสาร — ' . $document->document_number)

@section('breadcrumbs')
    <span>/</span>
    <a href="{{ route('documents.show', $document) }}" class="hover:text-[#004F85]">{{ $document->document_number }}</a>
    <span>/</span>
    <span class="text-[#004F85] font-medium">แก้ไข Metadata</span>
@endsection

@section('content')
<div class="max-w-3xl space-y-5">
    <div>
        <h2 class="text-xl font-bold text-[#004F85]">แก้ไขรายละเอียดเอกสาร (Metadata)</h2>
        <p class="text-xs text-slate-500 mt-0.5">สามารถแก้ไขเฉพาะข้อมูลเอกสารเท่านั้น • ข้อมูลผู้ป่วย (HN) และไฟล์ต้นฉบับไม่สามารถแก้ไขในหน้านี้ได้</p>
    </div>

    <form method="POST" action="{{ route('documents.update-metadata', $document) }}" class="aikchol-card p-6 space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">ชื่อเอกสาร <span class="text-red-600">*</span></label>
            <input type="text" name="title" value="{{ old('title', $document->title) }}" required class="aikchol-input">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">ประเภทเอกสาร <span class="text-red-600">*</span></label>
                <select name="document_type_id" required class="aikchol-input">
                    @foreach($documentTypes as $type)
                        <option value="{{ $type->id }}" @selected((string) old('document_type_id', $document->document_type_id) === (string) $type->id)>{{ $type->name_th }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">ระดับความลับ <span class="text-red-600">*</span></label>
                <select name="confidentiality_level" required class="aikchol-input">
                    @foreach($confidentialityLevels as $lvl)
                        <option value="{{ $lvl->value }}" @selected(old('confidentiality_level', $document->confidentiality_level?->value) === $lvl->value)>{{ $lvl->labelTh() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">วันที่เอกสาร <span class="text-red-600">*</span></label>
                <input type="date" name="document_date" value="{{ old('document_date', $document->document_date?->format('Y-m-d')) }}" required class="aikchol-input">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">วันที่รับเอกสาร</label>
                <input type="date" name="received_date" value="{{ old('received_date', $document->received_date?->format('Y-m-d')) }}" class="aikchol-input">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">เลขที่เอกสารอ้างอิง</label>
            <input type="text" name="reference_number" value="{{ old('reference_number', $document->reference_number) }}" class="aikchol-input">
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">รายละเอียด</label>
            <textarea name="description" rows="2" class="aikchol-input">{{ old('description', $document->description) }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">หมายเหตุ</label>
            <textarea name="notes" rows="2" class="aikchol-input">{{ old('notes', $document->notes) }}</textarea>
        </div>

        <div class="pt-3 flex justify-end gap-3">
            <a href="{{ route('documents.show', $document) }}" class="px-4 py-2 rounded-lg text-sm border border-slate-300 bg-white text-slate-700">ยกเลิก</a>
            <button type="submit" class="aikchol-btn-primary px-5 py-2 rounded-lg text-sm font-semibold cursor-pointer">บันทึกการแก้ไข</button>
        </div>
    </form>
</div>
@endsection
