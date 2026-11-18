@extends('layouts.app')

@section('title', 'ถังเอกสาร (Soft Deleted)')

@section('breadcrumbs')
    <span>/</span>
    <span class="text-[#004F85] font-medium">ถังเอกสาร</span>
@endsection

@section('content')
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold text-[#004F85]">ถังเอกสาร (Soft Deleted Documents)</h2>
            <p class="text-xs text-slate-500 mt-0.5">ไฟล์จริง Revision ข้อมูลผู้ป่วย และ Audit Log ยังคงถูกเก็บรักษาไว้ครบถ้วน และสามารถกู้คืนหรือสั่งลบถาวรตามสิทธิ์ได้</p>
        </div>
        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold {{ $retentionDays > 0 ? 'bg-[#FFF8E6] text-[#9A6000] border border-[#FBB435]' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
            @if($retentionDays > 0)
                นโยบายลบถาวรอัตโนมัติ: หลัง {{ $retentionDays }} วัน
            @else
                นโยบายลบถาวรอัตโนมัติ: ปิดใช้งาน
            @endif
        </span>
    </div>

    <div class="aikchol-card overflow-hidden">
        @if($documents->isEmpty())
            <div class="p-10 text-center text-sm text-slate-500">ไม่มีเอกสารในถังเอกสาร</div>
        @else
            <div class="divide-y divide-slate-100">
                @foreach($documents as $doc)
                    <div class="p-5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div class="space-y-1">
                            <p class="font-mono text-xs font-bold text-[#004F85]">{{ $doc->document_number }} • {{ $doc->documentType?->name_th }}</p>
                            <p class="text-sm font-bold text-slate-900">{{ $doc->title }}</p>
                            <p class="text-xs text-red-700">เหตุผลที่ลบ: {{ $doc->delete_reason ?: '-' }}</p>
                            <p class="text-xs text-slate-500">
                                ลบโดย: {{ $doc->deleter?->name ?? '-' }} • เมื่อ: {{ $doc->deleted_at?->format('d/m/Y H:i') }}
                                @if($retentionDays > 0 && $doc->deleted_at)
                                    • <span class="text-[#9A6000] font-medium">กำหนดลบถาวรอัตโนมัติ: {{ $doc->deleted_at->copy()->addDays($retentionDays)->format('d/m/Y') }}</span>
                                @endif
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            @if(auth()->user()->hasPermission('documents.restore'))
                                <form method="POST" action="{{ route('documents.restore', $doc->id) }}" onsubmit="return confirm('ยืนยันกู้คืนเอกสารนี้กลับมาใช้งาน?')">
                                    @csrf
                                    <button type="submit" class="aikchol-btn-primary px-4 py-2 rounded-lg text-xs font-semibold cursor-pointer">
                                        ↺ กู้คืนเอกสาร (Restore)
                                    </button>
                                </form>
                            @endif

                            @if($permanentDeleteEnabled && auth()->user()->hasPermission('documents.permanent_delete'))
                                <details class="text-xs">
                                    <summary class="px-3.5 py-2 rounded-lg font-semibold bg-red-50 text-red-700 border border-red-200 hover:bg-red-100 cursor-pointer list-none">
                                        ลบถาวร (Permanent Delete)
                                    </summary>
                                    <form method="POST" action="{{ route('documents.permanent-delete', $doc->id) }}" class="mt-3 p-4 rounded-xl border border-red-300 bg-red-50 space-y-2 w-80">
                                        @csrf
                                        @method('DELETE')
                                        <p class="font-bold text-red-900">ยืนยันตัวตนเพื่อลบถาวร (Audit Log จะไม่ถูกลบ)</p>
                                        <input type="password" name="password" required placeholder="รหัสผ่านปัจจุบันของท่าน" class="aikchol-input text-xs">
                                        <input type="text" name="reason" required minlength="10" placeholder="เหตุผลการลบถาวร (อย่างน้อย 10 ตัวอักษร)" class="aikchol-input text-xs">
                                        <button type="submit" onclick="return confirm('คำเตือน: การลบถาวรไม่สามารถกู้คืนไฟล์ได้ ยืนยันหรือไม่?')" class="w-full py-2 rounded-lg bg-red-700 text-white font-bold cursor-pointer">
                                            ยืนยันลบถาวร
                                        </button>
                                    </form>
                                </details>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="px-5 py-3 border-t border-[#DCE6EF]">{{ $documents->links() }}</div>
        @endif
    </div>
</div>
@endsection
