@extends('layouts.app')

@section('title', 'ตรวจสอบ Revision ใหม่ก่อนบันทึก — ' . $document->document_number)

@section('breadcrumbs')
    <span>/</span>
    <a href="{{ route('documents.show', $document) }}" class="hover:text-[#004F85]">{{ $document->document_number }}</a>
    <span>/</span>
    <span class="text-[#004F85] font-medium">ตรวจสอบ Revision ก่อนบันทึก</span>
@endsection

@section('content')
<div class="space-y-6" x-data="{ submitting: false }">
    <div class="aikchol-card p-6 border-t-4 border-t-[#004F85]">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-100">
            <div class="flex items-center gap-4">
                <img src="{{ route('branding.logo') }}" alt="{{ $branding->hospital_name_en }}" class="h-10 w-auto object-contain">
                <div>
                    <p class="text-xs font-semibold text-[#004F85]">{{ $branding->hospital_name_en }} • ยืนยันการเพิ่ม Revision</p>
                    <h2 class="text-lg font-bold text-slate-900">{{ $document->title }} ({{ $document->document_number }})</h2>
                </div>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-[#FFF8E6] text-[#9A6000] border border-[#FBB435]">
                จะสร้าง Rev. {{ ($document->currentRevision?->revision_number ?? 0) + 1 }}
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 text-xs">
            <div class="p-4 rounded-xl bg-slate-50 border border-[#DCE6EF] space-y-1.5">
                <p><strong>เหตุผลการแก้ไข:</strong> {{ $changeReason }}</p>
                <p><strong>หมายเหตุ:</strong> {{ $notes ?: '-' }}</p>
                <p class="text-amber-800">Revision เดิม (Rev. {{ $document->currentRevision?->revision_number }}) จะเปลี่ยนเป็น <strong>Superseded</strong> โดยไม่ลบไฟล์เดิม</p>
            </div>
            <div class="p-4 rounded-xl bg-slate-50 border border-[#DCE6EF] space-y-1.5">
                <p><strong>ชื่อไฟล์ใหม่:</strong> {{ $file['original_name'] }}</p>
                <p><strong>ชนิด / ขนาด:</strong> {{ strtoupper($file['extension']) }} • {{ number_format($file['file_size'] / 1024, 1) }} KB</p>
                <p><strong>SHA-256:</strong> <span class="font-mono text-[10px] break-all">{{ $file['sha256_hash'] }}</span></p>
            </div>
        </div>
    </div>

    <div class="aikchol-card p-5">
        <h3 class="text-sm font-bold text-[#004F85] mb-3">ตัวอย่างไฟล์ Revision ใหม่</h3>
        <x-pdf-preview-box
            :stream-url="$streamUrl"
            :mime-type="$file['mime_type']"
            :extension="$file['extension']"
            :original-name="$file['original_name']"
        />
    </div>

    <div class="aikchol-card p-5 flex flex-col sm:flex-row items-center justify-between gap-3">
        <a href="{{ route('documents.revisions.create', $document) }}" class="px-5 py-2.5 rounded-lg text-sm font-semibold border border-slate-300 bg-white text-slate-700">
            ← ย้อนกลับไปแก้ไข
        </a>

        <form method="POST" action="{{ route('documents.revisions.confirm', $document) }}" @submit="submitting = true">
            @csrf
            <input type="hidden" name="staging_token" value="{{ $stagingToken }}">
            <input type="hidden" name="idempotency_token" value="{{ $idempotencyToken }}">
            <button type="submit" :disabled="submitting" class="aikchol-btn-primary px-6 py-2.5 rounded-lg text-sm font-bold shadow cursor-pointer disabled:opacity-60">
                <span x-text="submitting ? 'กำลังสร้าง Revision ใหม่...' : '✓ ยืนยันสร้าง Revision ใหม่'">✓ ยืนยันสร้าง Revision ใหม่</span>
            </button>
        </form>
    </div>
</div>
@endsection
