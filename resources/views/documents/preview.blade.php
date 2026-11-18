@extends('layouts.app')

@section('title', 'ตัวอย่างเอกสาร — ' . $document->document_number . ' (Rev ' . $revision->revision_number . ')')

@section('breadcrumbs')
    <span>/</span>
    <a href="{{ route('documents.index') }}" class="hover:text-[#004F85]">รายการเอกสาร</a>
    <span>/</span>
    <a href="{{ route('documents.show', $document) }}" class="hover:text-[#004F85]">{{ $document->document_number }}</a>
    <span>/</span>
    <span class="text-[#004F85] font-medium">Preview Rev. {{ $revision->revision_number }}</span>
@endsection

@section('content')
<div class="space-y-4">
    <!-- Warning Banner when viewing an older/superseded or withdrawn revision -->
    @if(! $revision->isCurrent())
        <div class="rounded-xl border-2 border-[#FBB435] bg-[#FFF8E6] px-5 py-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="text-sm text-[#9A6000]">
                <p class="font-bold">คำเตือน: ท่านกำลังเปิดดูเอกสาร Revision เก่า (Rev. {{ $revision->revision_number }} — {{ $revision->status?->labelTh() }})</p>
                <p class="text-xs mt-0.5">เอกสารฉบับนี้ถูกแทนที่แล้ว ไม่ใช่ฉบับใช้งานปัจจุบัน กรุณาตรวจสอบก่อนนำไปใช้อ้างอิงทางการแพทย์</p>
            </div>
            @if($document->currentRevision)
                <a href="{{ route('documents.revisions.preview', [$document, $document->currentRevision]) }}" class="px-4 py-2 rounded-lg text-xs font-bold bg-[#004F85] text-white shrink-0 text-center">
                    ไปที่ฉบับปัจจุบัน (Rev. {{ $document->currentRevision->revision_number }})
                </a>
            @endif
        </div>
    @endif

    <div class="aikchol-card p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-base font-bold text-[#004F85]">{{ $document->title }}</h2>
                <x-revision-badge :status="$revision->status" />
            </div>
            <p class="text-xs text-slate-500 mt-0.5">
                เลขที่: <span class="font-mono font-semibold">{{ $document->document_number }}</span> • Revision {{ $revision->revision_number }} • ไฟล์: {{ $revision->storedFile?->original_name }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('documents.show', $document) }}" class="px-3.5 py-2 rounded-lg text-xs font-semibold border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
                ← กลับหน้ารายละเอียด
            </a>
        </div>
    </div>

    <x-pdf-preview-box
        :stream-url="$streamUrl"
        :mime-type="$revision->storedFile?->mime_type ?? 'application/pdf'"
        :extension="$revision->storedFile?->extension ?? 'pdf'"
        :original-name="$revision->storedFile?->original_name ?? 'document'"
        :file-size-formatted="$revision->storedFile?->formattedSize() ?? ''"
        :download-url="auth()->user()->hasPermission('documents.download') ? route('documents.revisions.download', [$document, $revision]) : null"
        height-class="h-[78vh]"
    />
</div>
@endsection
