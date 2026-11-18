@extends('layouts.app')

@section('title', 'รายการคำขอลบรออนุมัติ')

@section('breadcrumbs')
    <span>/</span>
    <span class="text-[#004F85] font-medium">คำขอลบรออนุมัติ</span>
@endsection

@section('content')
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold text-[#004F85]">รายการคำขอลบเอกสาร</h2>
            <p class="text-xs text-slate-500 mt-0.5">เมื่ออนุมัติ ระบบจะดำเนินการ Soft Delete เท่านั้น (ไม่ลบไฟล์จริง Revision และ Audit Log)</p>
        </div>
        <div class="flex gap-1.5 text-xs">
            @foreach(['pending' => 'รออนุมัติ', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ปฏิเสธแล้ว', 'all' => 'ทั้งหมด'] as $key => $label)
                <a href="{{ route('delete-requests.index', ['status' => $key]) }}"
                   class="px-3 py-1.5 rounded-lg font-medium {{ $selectedStatus === $key ? 'bg-[#004F85] text-white' : 'bg-white border border-slate-200 text-slate-700' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="space-y-4">
        @forelse($deleteRequests as $req)
            <div class="aikchol-card p-5 space-y-3">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $req->status === \App\Enums\DeleteRequestStatus::Pending ? 'bg-[#FFF8E6] text-[#9A6000] border border-[#FBB435]' : ($req->status === \App\Enums\DeleteRequestStatus::Approved ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800') }}">
                                {{ $req->status?->labelTh() }}
                            </span>
                            <span class="font-mono text-xs font-semibold text-[#004F85]">{{ $req->document?->document_number }}</span>
                        </div>
                        <h3 class="text-base font-bold text-slate-900 mt-1">{{ $req->document?->title ?? 'เอกสารที่ถูกลบ' }}</h3>
                        <p class="text-xs text-slate-600 mt-1"><strong>เหตุผลที่ขอลบ:</strong> {{ $req->request_reason }}</p>
                        <p class="text-xs text-slate-400 mt-1">ผู้ขอ: {{ $req->requester?->name }} • ส่งเมื่อ: {{ $req->created_at?->format('d/m/Y H:i') }}</p>
                        @if($req->reviewed_by)
                            <p class="text-xs text-slate-500 mt-1">ผู้พิจารณา: {{ $req->reviewer?->name }} ({{ $req->reviewed_at?->format('d/m/Y H:i') }}) • หมายเหตุ: {{ $req->review_note ?: '-' }}</p>
                        @endif
                    </div>

                    @if($req->document && ! $req->document->trashed())
                        <a href="{{ route('documents.show', $req->document) }}" class="text-xs font-semibold text-[#004F85] hover:underline shrink-0">ตรวจสอบเอกสาร →</a>
                    @endif
                </div>

                @if($req->status === \App\Enums\DeleteRequestStatus::Pending)
                    <form method="POST" action="{{ route('delete-requests.review', $req) }}" class="pt-3 border-t border-slate-100 flex flex-col sm:flex-row gap-3">
                        @csrf
                        <input type="text" name="review_note" placeholder="หมายเหตุการพิจารณา (บังคับระบุหากปฏิเสธ)" class="aikchol-input text-xs flex-1">
                        <div class="flex gap-2 shrink-0">
                            <button type="submit" name="decision" value="approve" onclick="return confirm('ยืนยันอนุมัติ Soft Delete เอกสารนี้?')" class="px-4 py-2 rounded-lg text-xs font-semibold bg-emerald-700 text-white hover:bg-emerald-800 cursor-pointer">
                                อนุมัติ (Soft Delete)
                            </button>
                            <button type="submit" name="decision" value="reject" class="px-4 py-2 rounded-lg text-xs font-semibold bg-red-600 text-white hover:bg-red-700 cursor-pointer">
                                ปฏิเสธคำขอ
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        @empty
            <div class="aikchol-card p-10 text-center text-sm text-slate-500">ไม่มีรายการคำขอลบตามสถานะที่เลือก</div>
        @endforelse
    </div>

    <div>{{ $deleteRequests->links() }}</div>
</div>
@endsection
