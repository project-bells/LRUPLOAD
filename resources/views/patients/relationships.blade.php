@extends('layouts.app')

@section('title', 'ความสัมพันธ์มารดาและทารก — ' . $patient->hn)

@section('breadcrumbs')
    <span>/</span>
    <a href="{{ route('patients.index') }}" class="hover:text-[#004F85]">ทะเบียนผู้ป่วย</a>
    <span>/</span>
    <a href="{{ route('patients.show', $patient) }}" class="hover:text-[#004F85]">HN {{ $patient->hn }}</a>
    <span>/</span>
    <span class="text-[#004F85] font-medium">ความสัมพันธ์มารดาและทารก</span>
@endsection

@section('content')
<div class="space-y-6 max-w-4xl">
    <div>
        <h2 class="text-xl font-bold text-[#004F85]">ความสัมพันธ์ระหว่างมารดาและทารก</h2>
        <p class="text-xs text-slate-500 mt-0.5">
            ระบบจะไม่คาดเดาความสัมพันธ์เอง หาก HIS ไม่คืนข้อมูลความสัมพันธ์ ผู้ใช้ต้องยืนยันผู้เกี่ยวข้องอย่างชัดเจนและระบบจะบันทึกผู้ดำเนินการใน Audit Log
        </p>
    </div>

    <div class="aikchol-card p-5 bg-[#EBF4FA]/40">
        <p class="text-xs text-slate-500">ผู้ป่วยหลัก</p>
        <p class="text-base font-bold text-[#004F85]">
            {{ $patient->display_name }} — HN: <span class="font-mono">{{ $patient->hn }}</span> ({{ $patient->patient_type?->labelTh() }})
        </p>
    </div>

    <!-- Current Relationships -->
    <div class="aikchol-card p-5">
        <h3 class="text-sm font-bold text-slate-800 mb-3">
            {{ $patient->patient_type === \App\Enums\PatientType::Mother ? 'ทารกที่เชื่อมโยงกับมารดารายนี้' : 'มารดาที่เชื่อมโยงกับทารกรายนี้' }}
        </h3>
        @php
            $linkedList = $patient->patient_type === \App\Enums\PatientType::Mother ? $patient->children : $patient->mothers;
        @endphp

        @if($linkedList->isEmpty())
            <p class="text-xs text-slate-500">ยังไม่มีข้อมูลความสัมพันธ์ที่เชื่อมโยงไว้</p>
        @else
            <div class="divide-y divide-slate-100">
                @foreach($linkedList as $rel)
                    <div class="py-3 flex items-center justify-between text-sm">
                        <div>
                            <p class="font-semibold text-slate-800">{{ $rel->display_name }} (<span class="font-mono text-[#004F85]">HN: {{ $rel->hn }}</span>)</p>
                            <p class="text-xs text-slate-500">แหล่งที่มา: {{ $rel->pivot->relationship_source }} • ยืนยันเมื่อ: {{ $rel->pivot->verified_at ?? '-' }}</p>
                        </div>
                        <a href="{{ route('patients.show', $rel) }}" class="text-xs font-semibold text-[#004F85] hover:underline">ดูข้อมูล →</a>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Manual Link Form -->
    @if(auth()->user()->hasAnyPermission(['documents.create', 'documents.edit_metadata']))
        <div class="aikchol-card p-6">
            <h3 class="text-sm font-bold text-[#004F85] mb-1">เชื่อมความสัมพันธ์เพิ่มเติม (Manual Verified)</h3>
            <p class="text-xs text-slate-500 mb-4">เลือก{{ $patient->patient_type === \App\Enums\PatientType::Mother ? 'ทารก' : 'มารดา' }}ที่มี Snapshot ในระบบแล้ว เพื่อยืนยันความสัมพันธ์</p>

            @if($candidates->isEmpty())
                <div class="p-4 rounded-lg bg-slate-50 text-xs text-slate-600">
                    ไม่พบรายการ{{ $patient->patient_type === \App\Enums\PatientType::Mother ? 'ทารก' : 'มารดา' }}ในระบบ กรุณาไปที่เมนู <a href="{{ route('patients.lookup.index') }}" class="text-[#004F85] font-semibold underline">ค้นหา HN</a> เพื่อเลือกผู้ป่วยก่อน
                </div>
            @else
                <form method="POST" action="{{ route('patients.relationships.store', $patient) }}" class="flex flex-col sm:flex-row gap-3">
                    @csrf
                    <select name="related_patient_id" required class="aikchol-input flex-1">
                        <option value="">-- เลือกผู้ป่วยที่เกี่ยวข้อง --</option>
                        @foreach($candidates as $cand)
                            <option value="{{ $cand->id }}">HN {{ $cand->hn }} — {{ $cand->display_name }} ({{ $cand->patient_type?->labelTh() }})</option>
                        @endforeach
                    </select>
                    <button type="submit" class="aikchol-btn-primary px-5 py-2.5 rounded-lg text-sm font-semibold cursor-pointer">
                        ยืนยันเชื่อมความสัมพันธ์
                    </button>
                </form>
            @endif
        </div>
    @endif
</div>
@endsection
