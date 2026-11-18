@extends('layouts.app')

@section('title', 'รายการเอกสารทั้งหมด')

@section('breadcrumbs')
    <span>/</span>
    <span class="text-[#004F85] font-medium">รายการเอกสารทั้งหมด</span>
@endsection

@section('content')
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold text-[#004F85]">รายการเอกสารผู้ป่วย แผนกห้องคลอด</h2>
            <p class="text-xs text-slate-500 mt-0.5">ค้นหาและตรวจสอบเอกสารทั้งหมดที่ใช้งานอยู่ในระบบ</p>
        </div>
        @if(auth()->user()->hasPermission('patients.lookup'))
            <a href="{{ route('patients.lookup.index') }}" class="aikchol-btn-primary px-4 py-2 rounded-lg text-xs font-semibold">
                + ค้นหา HN เพื่อเพิ่มเอกสารใหม่
            </a>
        @endif
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="{{ route('documents.index') }}" class="aikchol-card p-4 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
        <div class="md:col-span-4">
            <label class="block text-xs font-medium text-slate-600 mb-1">คำค้นหา (ชื่อเอกสาร / เลขที่เอกสาร)</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="ระบุคำค้นหา..." class="aikchol-input text-sm">
        </div>
        <div class="md:col-span-3">
            <label class="block text-xs font-medium text-slate-600 mb-1">ประเภทเอกสาร</label>
            <select name="document_type_id" class="aikchol-input text-sm">
                <option value="">ทั้งหมด</option>
                @foreach($documentTypes as $type)
                    <option value="{{ $type->id }}" @selected((string) request('document_type_id') === (string) $type->id)>{{ $type->name_th }}</option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-3">
            <label class="block text-xs font-medium text-slate-600 mb-1">เจ้าของเอกสาร</label>
            <select name="owner_type" class="aikchol-input text-sm">
                <option value="">ทั้งหมด</option>
                <option value="mother" @selected(request('owner_type') === 'mother')>มารดา</option>
                <option value="child" @selected(request('owner_type') === 'child')>ทารก</option>
                <option value="mother_and_child" @selected(request('owner_type') === 'mother_and_child')>ทั้งมารดาและทารก</option>
            </select>
        </div>
        <div class="md:col-span-2 flex gap-2">
            <button type="submit" class="aikchol-btn-primary flex-1 py-2.5 rounded-lg text-xs font-semibold cursor-pointer">กรองข้อมูล</button>
            <a href="{{ route('documents.index') }}" class="px-3 py-2.5 rounded-lg text-xs border border-slate-200 text-slate-600 hover:bg-slate-50">ล้าง</a>
        </div>
    </form>

    <div class="aikchol-card overflow-hidden">
        @if($documents->isEmpty())
            <div class="p-10 text-center text-sm text-slate-500">
                <p class="font-medium text-slate-700">ไม่พบรายการเอกสารตามเงื่อนไขที่ระบุ</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 text-xs text-slate-500 uppercase border-b border-[#DCE6EF]">
                        <tr>
                            <th class="px-5 py-3">เลขที่เอกสาร</th>
                            <th class="px-5 py-3">ชื่อเอกสาร / ประเภท</th>
                            <th class="px-5 py-3">ผู้ป่วย (HN)</th>
                            <th class="px-5 py-3">วันที่เอกสาร</th>
                            <th class="px-5 py-3">Revision</th>
                            <th class="px-5 py-3 text-right">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($documents as $doc)
                            @php $p = $doc->patients->first(); @endphp
                            <tr class="hover:bg-[#F4F8FB]">
                                <td class="px-5 py-3 font-mono text-xs font-semibold text-[#004F85]">{{ $doc->document_number }}</td>
                                <td class="px-5 py-3">
                                    <p class="font-semibold text-slate-800">{{ $doc->title }}</p>
                                    <p class="text-xs text-slate-500">{{ $doc->documentType?->name_th }} • {{ $doc->patient_owner_type?->labelTh() }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    @if($p)
                                        <p class="font-medium text-slate-800">{{ $p->display_name }}</p>
                                        <p class="text-xs font-mono text-slate-500">HN: {{ $p->hn }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-xs text-slate-600">{{ $doc->document_date?->format('d/m/Y') }}</td>
                                <td class="px-5 py-3">
                                    @if($doc->currentRevision)
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            Rev. {{ $doc->currentRevision->revision_number }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right space-x-2">
                                    <a href="{{ route('documents.show', $doc) }}" class="text-xs font-semibold text-[#004F85] hover:underline">รายละเอียด</a>
                                    @if($doc->currentRevision && auth()->user()->hasPermission('documents.preview'))
                                        <a href="{{ route('documents.revisions.preview', [$doc, $doc->currentRevision]) }}" class="text-xs font-semibold text-[#1F8E96] hover:underline">Preview</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-[#DCE6EF]">
                {{ $documents->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
