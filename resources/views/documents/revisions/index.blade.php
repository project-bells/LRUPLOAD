@extends('layouts.app')

@section('title', 'ประวัติ Revision — ' . $document->document_number)

@section('breadcrumbs')
    <span>/</span>
    <a href="{{ route('documents.show', $document) }}" class="hover:text-[#004F85]">{{ $document->document_number }}</a>
    <span>/</span>
    <span class="text-[#004F85] font-medium">Revision History</span>
@endsection

@section('content')
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold text-[#004F85]">ประวัติ Revision เอกสาร</h2>
            <p class="text-xs text-slate-500 mt-0.5">{{ $document->document_number }} — {{ $document->title }}</p>
        </div>
        @if(auth()->user()->hasPermission('documents.create_revision'))
            <a href="{{ route('documents.revisions.create', $document) }}" class="aikchol-btn-primary px-4 py-2 rounded-lg text-xs font-semibold">
                + เพิ่ม Revision ใหม่
            </a>
        @endif
    </div>

    <div class="space-y-4">
        @foreach($document->revisions as $rev)
            <div class="aikchol-card p-5 {{ $rev->isCurrent() ? 'border-l-4 border-l-emerald-600' : 'border-l-4 border-l-slate-300' }}">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-base font-bold text-[#004F85]">Revision {{ $rev->revision_number }}</span>
                            <x-revision-badge :status="$rev->status" />
                            @if($rev->superseded_at)
                                <span class="text-xs text-slate-400">ถูกแทนที่เมื่อ: {{ $rev->superseded_at->format('d/m/Y H:i') }}</span>
                            @endif
                        </div>
                        <p class="text-sm text-slate-800 mt-1.5"><strong>เหตุผล:</strong> {{ $rev->change_reason ?? '-' }}</p>
                        @if($rev->notes)
                            <p class="text-xs text-slate-600 mt-0.5">หมายเหตุ: {{ $rev->notes }}</p>
                        @endif
                        <p class="text-xs text-slate-500 mt-2">
                            ไฟล์: <span class="font-medium">{{ $rev->storedFile?->original_name }}</span> ({{ $rev->storedFile?->formattedSize() }}) •
                            SHA-256: <span class="font-mono text-[11px]">{{ $rev->storedFile?->sha256_hash }}</span>
                        </p>
                        <p class="text-xs text-slate-400 mt-1">ผู้สร้าง: {{ $rev->creator?->name }} • {{ $rev->created_at?->format('d/m/Y H:i:s') }}</p>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        @if($rev->isCurrent() || auth()->user()->hasPermission('revisions.view_all'))
                            @if(auth()->user()->hasPermission('documents.preview'))
                                <a href="{{ route('documents.revisions.preview', [$document, $rev]) }}" class="aikchol-btn-primary px-3.5 py-2 rounded-lg text-xs font-semibold">
                                    เปิด Preview
                                </a>
                            @endif
                            @if(auth()->user()->hasPermission('documents.download'))
                                <a href="{{ route('documents.revisions.download', [$document, $rev]) }}" class="px-3.5 py-2 rounded-lg text-xs font-semibold border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
                                    ดาวน์โหลด
                                </a>
                            @endif
                        @else
                            <span class="text-xs text-slate-400">เฉพาะหัวหน้า/ผู้มีสิทธิ์ดู Revision เก่า</span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
