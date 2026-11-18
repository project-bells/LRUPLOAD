@extends('layouts.app')

@section('title', 'ตั้งค่า Branding และโลโก้')

@section('breadcrumbs')
    <span>/</span>
    <span class="text-[#004F85] font-medium">ตั้งค่า Branding และโลโก้</span>
@endsection

@section('content')
<div class="max-w-4xl space-y-6">
    <div>
        <h2 class="text-xl font-bold text-[#004F85]">ตั้งค่า Branding และโลโก้ของ Aikchol Hospital</h2>
        <p class="text-xs text-slate-500 mt-0.5">
            รักษาสัดส่วน ความคมชัด และพื้นที่ว่างรอบโลโก้ต้นฉบับ • สีหลักของระบบอ้างอิงจากเอกลักษณ์ของโรงพยาบาลเอกชล (#004F85, #3EC0C8, #FBB435)
        </p>
    </div>

    <!-- Current Logo Preview with Clear Space -->
    <div class="aikchol-card p-6">
        <p class="text-xs font-semibold text-slate-500 mb-3">ตัวอย่างการแสดงผลโลโก้ปัจจุบัน (รักษาสัดส่วนและ Clear Space)</p>
        <div class="p-8 rounded-xl border border-dashed border-[#004F85]/30 bg-white flex flex-col items-center justify-center">
            <img src="{{ route('branding.logo') }}" alt="{{ $brandingSetting->hospital_name_en }}" class="h-16 w-auto max-w-full object-contain">
            <p class="text-xs text-slate-500 mt-4">{{ $brandingSetting->hospital_name_th }} — {{ $brandingSetting->hospital_name_en }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.branding.update') }}" enctype="multipart/form-data" class="aikchol-card p-6 space-y-5">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">ชื่อโรงพยาบาล (ภาษาไทย) *</label>
                <input type="text" name="hospital_name_th" value="{{ old('hospital_name_th', $brandingSetting->hospital_name_th) }}" required class="aikchol-input text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">ชื่อโรงพยาบาล (ภาษาอังกฤษ) *</label>
                <input type="text" name="hospital_name_en" value="{{ old('hospital_name_en', $brandingSetting->hospital_name_en) }}" required class="aikchol-input text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">ชื่อหน่วยงาน (ภาษาไทย) *</label>
                <input type="text" name="department_name_th" value="{{ old('department_name_th', $brandingSetting->department_name_th) }}" required class="aikchol-input text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">ชื่อหน่วยงาน (ภาษาอังกฤษ) *</label>
                <input type="text" name="department_name_en" value="{{ old('department_name_en', $brandingSetting->department_name_en) }}" required class="aikchol-input text-sm">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-slate-100">
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">สีหลัก (Primary — Aikchol Blue)</label>
                <input type="text" name="primary_color" value="{{ old('primary_color', $brandingSetting->primary_color) }}" required pattern="^#[0-9A-Fa-f]{6}$" class="aikchol-input text-sm font-mono">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">สีรอง (Secondary — Medical Teal)</label>
                <input type="text" name="secondary_color" value="{{ old('secondary_color', $brandingSetting->secondary_color) }}" required pattern="^#[0-9A-Fa-f]{6}$" class="aikchol-input text-sm font-mono">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">สีเน้น (Accent — Warm Gold)</label>
                <input type="text" name="accent_color" value="{{ old('accent_color', $brandingSetting->accent_color) }}" required pattern="^#[0-9A-Fa-f]{6}$" class="aikchol-input text-sm font-mono">
            </div>
        </div>

        <div class="pt-2 border-t border-slate-100 space-y-3">
            <label class="block text-xs font-medium text-slate-700">เปลี่ยนไฟล์โลโก้ (PNG พื้นหลังโปร่งใสแนะนำ, ไม่เกิน 4 MB)</label>
            <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp" class="block w-full text-xs text-slate-700 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[#EBF4FA] file:text-[#004F85] file:font-semibold">
            @if($brandingSetting->logo_path)
                <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox" name="reset_logo" value="1" class="rounded">
                    <span>กลับไปใช้ไฟล์โลโก้มาตรฐานของ Aikchol Hospital</span>
                </label>
            @endif
        </div>

        <div class="flex justify-end">
            <button type="submit" class="aikchol-btn-primary px-6 py-2.5 rounded-lg text-sm font-semibold shadow-sm cursor-pointer">
                บันทึกการตั้งค่า Branding
            </button>
        </div>
    </form>
</div>
@endsection
