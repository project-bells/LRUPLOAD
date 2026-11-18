@extends('layouts.app')

@section('title', 'ทะเบียนผู้ป่วย (Patient Snapshots)')

@section('breadcrumbs')
    <span>/</span>
    <span class="text-[#004F85] font-medium">ทะเบียนผู้ป่วย</span>
@endsection

@section('content')
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold text-[#004F85]">ทะเบียนผู้ป่วยในระบบเอกสาร (มารดา / ทารก)</h2>
            <p class="text-xs text-slate-500 mt-0.5">แสดงเฉพาะผู้ป่วยที่ผู้ใช้ได้เลือกและจัดเก็บ Snapshot ลง MySQL แล้ว</p>
        </div>
        @if(auth()->user()->hasPermission('patients.lookup'))
            <a href="{{ route('patients.lookup.index') }}" class="aikchol-btn-primary px-4 py-2 rounded-lg text-xs font-semibold">
                + ค้นหา HN จาก HIS
            </a>
        @endif
    </div>

    <div class="aikchol-card p-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-2 text-xs">
            <a href="{{ route('patients.index') }}" class="px-3 py-1.5 rounded-lg font-medium {{ $selectedType === '' ? 'bg-[#004F85] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">ทั้งหมด</a>
            <a href="{{ route('patients.index', ['patient_type' => 'mother']) }}" class="px-3 py-1.5 rounded-lg font-medium {{ $selectedType === 'mother' ? 'bg-[#004F85] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">มารดา</a>
            <a href="{{ route('patients.index', ['patient_type' => 'child']) }}" class="px-3 py-1.5 rounded-lg font-medium {{ $selectedType === 'child' ? 'bg-[#004F85] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">ทารก</a>
        </div>

        <form method="POST" action="{{ route('patients.search-local') }}" class="flex gap-2">
            @csrf
            <input type="text" name="hn" required placeholder="ค้นหาด้วย HN..." class="aikchol-input font-mono text-xs !w-52">
            <button type="submit" class="aikchol-btn-primary px-3.5 py-1.5 rounded-lg text-xs font-semibold cursor-pointer">ค้นหา</button>
        </form>
    </div>

    <div class="aikchol-card overflow-hidden">
        @if($patients->isEmpty())
            <div class="p-10 text-center text-sm text-slate-500">
                <p class="font-medium text-slate-700">ยังไม่มีข้อมูลผู้ป่วยในระบบเอกสาร</p>
                <p class="text-xs mt-1">กรุณาค้นหา HN จากระบบ HIS และกดยืนยันเลือกผู้ป่วย</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 text-xs text-slate-500 uppercase border-b border-[#DCE6EF]">
                        <tr>
                            <th class="px-5 py-3">HN</th>
                            <th class="px-5 py-3">ประเภท</th>
                            <th class="px-5 py-3">ชื่อ–นามสกุล</th>
                            <th class="px-5 py-3">Ward / AN</th>
                            <th class="px-5 py-3">จำนวนเอกสาร</th>
                            <th class="px-5 py-3">อัปเดต Snapshot</th>
                            <th class="px-5 py-3 text-right">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($patients as $patient)
                            <tr class="hover:bg-[#F4F8FB]">
                                <td class="px-5 py-3 font-mono font-bold text-[#004F85]">{{ $patient->hn }}</td>
                                <td class="px-5 py-3">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $patient->patient_type === \App\Enums\PatientType::Mother ? 'bg-[#EBF4FA] text-[#004F85]' : 'bg-[#EAF8F9] text-[#1F8E96]' }}">
                                        {{ $patient->patient_type?->labelTh() ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 font-medium text-slate-800">{{ $patient->display_name }}</td>
                                <td class="px-5 py-3 text-xs text-slate-600">{{ $patient->ward ?? '-' }} / {{ $patient->admission_number ?? '-' }}</td>
                                <td class="px-5 py-3 font-semibold text-slate-700">{{ $patient->documents_count }} ฉบับ</td>
                                <td class="px-5 py-3 text-xs text-slate-500">{{ $patient->source_fetched_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td class="px-5 py-3 text-right space-x-2">
                                    <a href="{{ route('patients.show', $patient) }}" class="text-xs font-semibold text-[#004F85] hover:underline">รายละเอียด</a>
                                    @if(auth()->user()->hasPermission('documents.create'))
                                        <a href="{{ route('documents.create', $patient) }}" class="text-xs font-semibold text-[#1F8E96] hover:underline">+ เพิ่มเอกสาร</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-[#DCE6EF]">
                {{ $patients->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
