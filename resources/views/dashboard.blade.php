@extends('layouts.app')

@section('title', 'ภาพรวมระบบ (Dashboard)')

@section('content')
<div class="space-y-6">
    <!-- Welcome Banner -->
    <div class="aikchol-card p-6 bg-gradient-to-r from-[#004F85] to-[#0B66A2] text-white border-0">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-wider text-[#9EE8ED] font-semibold">
                    {{ $branding->hospital_name_en }} • {{ $branding->department_name_en }}
                </p>
                <h2 class="text-xl sm:text-2xl font-bold mt-1">
                    ยินดีต้อนรับ, {{ auth()->user()->name }}
                </h2>
                <p class="text-sm text-white/85 mt-1">
                    ระบบจัดเก็บเอกสารผู้ป่วย แผนกห้องคลอด (รองรับ Revision แบบไม่เขียนทับไฟล์เดิม และความปลอดภัยตาม PDPA)
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold {{ $hisEnabled ? 'bg-emerald-400/20 text-emerald-100 border border-emerald-300/40' : 'bg-[#FBB435]/20 text-amber-100 border border-amber-300/40' }}">
                    <span class="w-2 h-2 rounded-full {{ $hisEnabled ? 'bg-emerald-300' : 'bg-[#FBB435]' }}"></span>
                    HIS SQL Server 2014: {{ $hisEnabled ? 'เปิดเชื่อมต่อ (Read-Only)' : 'โหมดรอตั้งค่า HIS' }}
                </span>
                @if(auth()->user()->hasPermission('patients.lookup'))
                    <a href="{{ route('patients.lookup.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold bg-[#3EC0C8] text-slate-950 hover:brightness-95 transition">
                        ค้นหา HN เพื่อจัดเก็บเอกสาร
                    </a>
                @endif
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="aikchol-card p-5">
            <p class="text-xs font-medium text-slate-500">เอกสารพร้อมใช้งาน</p>
            <p class="text-3xl font-bold text-[#004F85] mt-2">{{ number_format($stats['active_documents']) }}</p>
            <p class="text-xs text-slate-400 mt-1">เพิ่มวันนี้ {{ number_format($stats['today_documents']) }} รายการ</p>
        </div>

        <div class="aikchol-card p-5">
            <p class="text-xs font-medium text-slate-500">เอกสารทั้งหมดในระบบ</p>
            <p class="text-3xl font-bold text-[#1F8E96] mt-2">{{ number_format($stats['total_documents']) }}</p>
            <p class="text-xs text-slate-400 mt-1">รวมเอกสารพร้อมใช้งานและในถังเอกสาร</p>
        </div>

        <div class="aikchol-card p-5">
            <p class="text-xs font-medium text-slate-500">คำขอลบรออนุมัติ</p>
            <p class="text-3xl font-bold text-[#9A6000] mt-2">{{ number_format($stats['pending_delete_requests']) }}</p>
            @if(auth()->user()->hasPermission('delete_requests.review'))
                <a href="{{ route('delete-requests.index') }}" class="text-xs text-[#004F85] hover:underline mt-1 inline-block">ตรวจสอบรายการ →</a>
            @else
                <p class="text-xs text-slate-400 mt-1">รอหัวหน้า/ผู้ดูแลระบบพิจารณา</p>
            @endif
        </div>

        <div class="aikchol-card p-5">
            <p class="text-xs font-medium text-slate-500">เอกสารในถังเอกสาร (Soft Deleted)</p>
            <p class="text-3xl font-bold text-slate-700 mt-2">{{ number_format($stats['trashed_documents']) }}</p>
            @if(auth()->user()->hasPermission('documents.restore'))
                <a href="{{ route('documents.trash') }}" class="text-xs text-[#004F85] hover:underline mt-1 inline-block">เปิดถังเอกสาร →</a>
            @else
                <p class="text-xs text-slate-400 mt-1">ไฟล์เดิมไม่ถูกลบอัตโนมัติ</p>
            @endif
        </div>
    </div>

    <!-- Recent Documents Table -->
    <div class="aikchol-card overflow-hidden">
        <div class="px-5 py-4 border-b border-[#DCE6EF] flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-[#004F85]">เอกสารล่าสุด (แสดง 20 รายการล่าสุด)</h3>
                <p class="text-xs text-slate-500">รายการเอกสารที่จัดเก็บล่าสุดในแผนกห้องคลอด</p>
            </div>
            <a href="{{ route('documents.index') }}" class="text-xs font-semibold text-[#004F85] hover:underline">ดูทั้งหมด →</a>
        </div>

        @if($recentDocuments->isEmpty())
            <div class="p-10 text-center text-sm text-slate-500">
                <p class="font-medium text-slate-700">ยังไม่มีเอกสารในระบบ</p>
                <p class="text-xs mt-1">เริ่มต้นด้วยการค้นหา HN ผู้ป่วยเพื่อสร้างเอกสารรายการแรก</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 text-xs text-slate-500 uppercase border-b border-[#DCE6EF]">
                        <tr>
                            <th class="px-5 py-3">เลขที่เอกสาร</th>
                            <th class="px-5 py-3">ชื่อเอกสาร / ประเภท</th>
                            <th class="px-5 py-3">ผู้ป่วย (HN)</th>
                            <th class="px-5 py-3">Revision</th>
                            <th class="px-5 py-3">วันที่บันทึก</th>
                            <th class="px-5 py-3 text-right">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($recentDocuments as $doc)
                            @php $p = $doc->patients->first(); @endphp
                            <tr class="hover:bg-[#F4F8FB]">
                                <td class="px-5 py-3 font-mono text-xs font-semibold text-[#004F85]">{{ $doc->document_number }}</td>
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800">{{ $doc->title }}</p>
                                    <p class="text-xs text-slate-500">{{ $doc->documentType?->name_th }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    @if($p)
                                        <p class="font-medium text-slate-800">{{ $p->display_name }}</p>
                                        <p class="text-xs text-slate-500 font-mono">HN: {{ $p->hn }}</p>
                                    @else
                                        <span class="text-xs text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if($doc->currentRevision)
                                        <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">
                                            Rev. {{ $doc->currentRevision->revision_number }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-xs text-slate-500">{{ $doc->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('documents.show', $doc) }}" class="text-xs font-semibold text-[#004F85] hover:underline">เปิดดู</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
