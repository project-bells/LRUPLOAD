@extends('layouts.app')

@section('title', 'รายละเอียดผู้ป่วย — ' . $patient->hn)

@section('breadcrumbs')
    <span>/</span>
    <a href="{{ route('patients.index') }}" class="hover:text-[#004F85]">ทะเบียนผู้ป่วย</a>
    <span>/</span>
    <span class="text-[#004F85] font-medium">HN {{ $patient->hn }}</span>
@endsection

@section('content')
<div class="space-y-6">
    <!-- Patient Summary Card -->
    <div class="aikchol-card overflow-hidden">
        <div class="bg-[#EBF4FA] px-6 py-4 border-b border-[#DCE6EF] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="px-3 py-1 rounded-full text-xs font-bold {{ $patient->patient_type === \App\Enums\PatientType::Mother ? 'bg-[#004F85] text-white' : 'bg-[#3EC0C8] text-slate-950' }}">
                    {{ $patient->patient_type?->labelTh() }}
                </span>
                <div>
                    <h2 class="text-lg font-bold text-[#004F85]">{{ $patient->display_name }}</h2>
                    <p class="text-xs font-mono text-slate-600">HN: {{ $patient->hn }} • AN: {{ $patient->admission_number ?? '-' }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('patients.relationships', $patient) }}" class="px-3.5 py-2 rounded-lg text-xs font-semibold bg-white border border-[#004F85]/30 text-[#004F85] hover:bg-[#EBF4FA]">
                    ความสัมพันธ์มารดา–ทารก
                </a>
                @if(auth()->user()->hasPermission('documents.create'))
                    <a href="{{ route('documents.create', $patient) }}" class="aikchol-btn-primary px-4 py-2 rounded-lg text-xs font-semibold shadow-sm">
                        + เพิ่มเอกสารผู้ป่วย
                    </a>
                @endif
                @can('delete', $patient)
                    <form method="POST" action="{{ route('patients.destroy', $patient) }}" class="inline" onsubmit="return confirm('ยืนยันการลบข้อมูลผู้ป่วย HN {{ $patient->hn }} ออกจากระบบ? (การลบนี้ใช้สำหรับผู้ป่วยที่ไม่มีเอกสารเท่านั้น)');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-3.5 py-2 rounded-lg text-xs font-semibold bg-rose-50 border border-rose-200 text-rose-700 hover:bg-rose-100 hover:border-rose-300 transition cursor-pointer">
                            ลบข้อมูลผู้ป่วย (0 ฉบับ)
                        </button>
                    </form>
                @endcan
            </div>
        </div>

        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-xs text-slate-500">วันเดือนปีเกิด / เพศ</p>
                <p class="font-medium text-slate-800">{{ $patient->birth_date ?? '-' }} • {{ $patient->sex ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">เลขประจำตัวประชาชน (Masked)</p>
                <p class="font-mono text-slate-700">{{ $patient->national_id_masked ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">Ward / หน่วยงาน</p>
                <p class="font-medium text-slate-800">{{ $patient->ward ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">ดึงข้อมูลจาก HIS ล่าสุด</p>
                <p class="text-xs text-slate-700">{{ $patient->source_fetched_at?->format('d/m/Y H:i:s') ?? '-' }}</p>
            </div>
        </div>
    </div>

    <!-- Linked Mother / Children -->
    <div class="aikchol-card p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold text-[#004F85]">ความสัมพันธ์ระหว่างมารดาและทารก</h3>
            <a href="{{ route('patients.relationships', $patient) }}" class="text-xs font-semibold text-[#004F85] hover:underline">จัดการความสัมพันธ์ →</a>
        </div>

        @if($patient->patient_type === \App\Enums\PatientType::Mother)
            @if($patient->children->isEmpty())
                <p class="text-xs text-slate-500">ยังไม่มีรายการทารกที่เชื่อมโยงกับมารดารายนี้</p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($patient->children as $child)
                        <a href="{{ route('patients.show', $child) }}" class="p-3 rounded-lg border border-[#DCE6EF] bg-[#EAF8F9]/50 hover:border-[#3EC0C8] transition">
                            <p class="text-xs font-semibold text-[#1F8E96]">ทารก • HN: <span class="font-mono">{{ $child->hn }}</span></p>
                            <p class="text-sm font-bold text-slate-800 mt-0.5">{{ $child->display_name }}</p>
                            <p class="text-[11px] text-slate-500 mt-1">แหล่งเชื่อมโยง: {{ $child->pivot->relationship_source }}</p>
                        </a>
                    @endforeach
                </div>
            @endif
        @else
            @if($patient->mothers->isEmpty())
                <p class="text-xs text-slate-500">ยังไม่มีข้อมูลมารดาที่เชื่อมโยงกับทารกรายนี้</p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($patient->mothers as $mother)
                        <a href="{{ route('patients.show', $mother) }}" class="p-3 rounded-lg border border-[#DCE6EF] bg-[#EBF4FA]/60 hover:border-[#004F85] transition">
                            <p class="text-xs font-semibold text-[#004F85]">มารดา • HN: <span class="font-mono">{{ $mother->hn }}</span></p>
                            <p class="text-sm font-bold text-slate-800 mt-0.5">{{ $mother->display_name }}</p>
                            <p class="text-[11px] text-slate-500 mt-1">แหล่งเชื่อมโยง: {{ $mother->pivot->relationship_source }}</p>
                        </a>
                    @endforeach
                </div>
            @endif
        @endif
    </div>

    <!-- Patient Document List (Screen 7) -->
    <div class="aikchol-card overflow-hidden">
        <div class="px-5 py-4 border-b border-[#DCE6EF] flex items-center justify-between">
            <div>
                <h3 class="font-bold text-[#004F85]">รายการเอกสารของผู้ป่วย ({{ $patient->documents->count() }} ฉบับ)</h3>
                <p class="text-xs text-slate-500">แสดงเอกสารที่ใช้งานอยู่ปัจจุบัน พร้อมเวอร์ชัน Revision ล่าสุด</p>
            </div>
        </div>

        @if($patient->documents->isEmpty())
            <div class="p-10 text-center text-sm text-slate-500">
                <p class="font-medium text-slate-700">ยังไม่มีเอกสารสำหรับผู้ป่วยรายนี้</p>
                @if(auth()->user()->hasPermission('documents.create'))
                    <a href="{{ route('documents.create', $patient) }}" class="aikchol-btn-primary inline-block mt-3 px-4 py-2 rounded-lg text-xs font-semibold">
                        + เพิ่มเอกสารฉบับแรก
                    </a>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 text-xs text-slate-500 uppercase border-b border-[#DCE6EF]">
                        <tr>
                            <th class="px-5 py-3">เลขที่เอกสาร</th>
                            <th class="px-5 py-3">ชื่อเอกสาร / ประเภท</th>
                            <th class="px-5 py-3">เจ้าของเอกสาร</th>
                            <th class="px-5 py-3">วันที่เอกสาร</th>
                            <th class="px-5 py-3">Revision ปัจจุบัน</th>
                            <th class="px-5 py-3 text-right">ดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($patient->documents as $doc)
                            <tr class="hover:bg-[#F4F8FB]">
                                <td class="px-5 py-3 font-mono text-xs font-semibold text-[#004F85]">{{ $doc->document_number }}</td>
                                <td class="px-5 py-3">
                                    <p class="font-semibold text-slate-800">{{ $doc->title }}</p>
                                    <p class="text-xs text-slate-500">{{ $doc->documentType?->name_th }}</p>
                                </td>
                                <td class="px-5 py-3 text-xs text-slate-600">{{ $doc->patient_owner_type?->labelTh() }}</td>
                                <td class="px-5 py-3 text-xs text-slate-600">{{ $doc->document_date?->format('d/m/Y') }}</td>
                                <td class="px-5 py-3">
                                    @if($doc->currentRevision)
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            Rev. {{ $doc->currentRevision->revision_number }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right space-x-2">
                                    <a href="{{ route('documents.show', $doc) }}" class="text-xs font-semibold text-[#004F85] hover:underline">เปิดดู</a>
                                    @if($doc->currentRevision && auth()->user()->hasPermission('documents.preview'))
                                        <a href="{{ route('documents.revisions.preview', [$doc, $doc->currentRevision]) }}" class="text-xs font-semibold text-[#1F8E96] hover:underline">Preview</a>
                                    @endif
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
