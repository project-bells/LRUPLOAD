@extends('layouts.app')

@section('title', 'จัดการประเภทเอกสาร')

@section('breadcrumbs')
    <span>/</span>
    <span class="text-[#004F85] font-medium">จัดการประเภทเอกสาร</span>
@endsection

@section('content')
<div class="space-y-6 max-w-5xl">
    <div>
        <h2 class="text-xl font-bold text-[#004F85]">จัดการประเภทเอกสารแผนกห้องคลอด</h2>
        <p class="text-xs text-slate-500 mt-0.5">เพิ่ม แก้ไข หรือปิดการใช้งานประเภทเอกสารที่แสดงในฟอร์มจัดเก็บเอกสาร</p>
    </div>

    <!-- Add New Document Type -->
    <div class="aikchol-card p-6">
        <h3 class="text-sm font-bold text-[#004F85] mb-3">+ เพิ่มประเภทเอกสารใหม่</h3>
        <form method="POST" action="{{ route('admin.document-types.store') }}" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            @csrf
            <div class="md:col-span-2">
                <label class="block text-xs text-slate-600 mb-1">รหัส *</label>
                <input type="text" name="code" required placeholder="เช่น ULTRASOUND" class="aikchol-input text-xs font-mono uppercase">
            </div>
            <div class="md:col-span-4">
                <label class="block text-xs text-slate-600 mb-1">ชื่อภาษาไทย *</label>
                <input type="text" name="name_th" required placeholder="เช่น ผลอัลตราซาวด์" class="aikchol-input text-xs">
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs text-slate-600 mb-1">ชื่อภาษาอังกฤษ</label>
                <input type="text" name="name_en" placeholder="Ultrasound Result" class="aikchol-input text-xs">
            </div>
            <div class="md:col-span-1">
                <label class="block text-xs text-slate-600 mb-1">ลำดับ</label>
                <input type="number" name="sort_order" value="10" class="aikchol-input text-xs">
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="aikchol-btn-primary w-full py-2.5 rounded-lg text-xs font-semibold cursor-pointer">เพิ่มประเภท</button>
            </div>
        </form>
    </div>

    <!-- Existing Types -->
    <div class="aikchol-card overflow-hidden divide-y divide-slate-100">
        @foreach($documentTypes as $type)
            <form method="POST" action="{{ route('admin.document-types.update', $type) }}" class="p-4 grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
                @csrf
                @method('PUT')
                <div class="md:col-span-2">
                    <input type="text" name="code" value="{{ $type->code }}" required class="aikchol-input text-xs font-mono">
                </div>
                <div class="md:col-span-3">
                    <input type="text" name="name_th" value="{{ $type->name_th }}" required class="aikchol-input text-xs">
                </div>
                <div class="md:col-span-3">
                    <input type="text" name="name_en" value="{{ $type->name_en }}" class="aikchol-input text-xs">
                </div>
                <div class="md:col-span-1">
                    <input type="number" name="sort_order" value="{{ $type->sort_order }}" class="aikchol-input text-xs">
                </div>
                <div class="md:col-span-1 text-center text-xs text-slate-500">
                    {{ $type->documents_count }} ฉบับ
                </div>
                <div class="md:col-span-2 flex items-center justify-end gap-2">
                    <label class="inline-flex items-center gap-1 text-xs">
                        <input type="checkbox" name="is_active" value="1" @checked($type->is_active) class="rounded text-[#004F85]">
                        <span>ใช้งาน</span>
                    </label>
                    <button type="submit" class="aikchol-btn-primary px-3 py-1.5 rounded-lg text-xs font-semibold cursor-pointer">บันทึก</button>
                </div>
            </form>
        @endforeach
    </div>
</div>
@endsection
