@extends('layouts.app')

@section('title', 'Audit Log (บันทึกการใช้งานระบบ)')

@section('breadcrumbs')
    <span>/</span>
    <span class="text-[#004F85] font-medium">Audit Log</span>
@endsection

@section('content')
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <h2 class="text-xl font-bold text-[#004F85]">Audit Log (บันทึกการใช้งานแบบ Append-Only)</h2>
            <p class="text-xs text-slate-500 mt-0.5">
                ขอบเขตการดู: {{ $canViewAll ? 'ทั้งระบบ (Administrator)' : 'เฉพาะหน่วยงาน (Supervisor)' }} • ไม่สามารถแก้ไขหรือลบรายการได้ • ใช้ HN Hash แทน HN จริงตาม PDPA
            </p>
        </div>
    </div>

    <!-- PDPA-Safe POST HN Hash Filter (never puts plaintext HN in GET URL / access logs) -->
    <form method="POST" action="{{ route('audit-logs.search-hn') }}" class="aikchol-card p-4 flex flex-col sm:flex-row sm:items-end gap-3">
        @csrf
        <div class="flex-1 max-w-sm">
            <label for="audit_hn" class="block text-xs font-medium text-slate-600 mb-1">ค้นหาประวัติด้วย HN (ส่งแบบ POST เพื่อแปลงเป็น HN Hash อย่างปลอดภัย)</label>
            <input id="audit_hn" type="text" name="hn" required autocomplete="off" placeholder="กรอก HN ที่ต้องการตรวจสอบ..." class="aikchol-input text-xs font-mono">
        </div>
        <button type="submit" class="aikchol-btn-primary px-4 py-2 rounded-lg text-xs font-semibold cursor-pointer shrink-0">
            ค้นหาด้วย HN Hash
        </button>
        @if(request()->filled('hn_hash'))
            <span class="text-[11px] font-mono text-[#004F85] bg-[#EBF4FA] px-2.5 py-1.5 rounded-lg">
                กำลังกรอง HN Hash: {{ \Illuminate\Support\Str::limit(request('hn_hash'), 16) }}
            </span>
        @endif
    </form>

    <!-- Filter Form (GET — strictly non-PII parameters only) -->
    <form method="GET" action="{{ route('audit-logs.index') }}" class="aikchol-card p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
        @if(request()->filled('hn_hash'))
            <input type="hidden" name="hn_hash" value="{{ request('hn_hash') }}">
        @endif
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">เหตุการณ์ (Action)</label>
            <select name="action" class="aikchol-input text-xs">
                <option value="">ทั้งหมด</option>
                @foreach($actions as $act)
                    <option value="{{ $act }}" @selected(request('action') === $act)>{{ $act }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">ผู้ใช้งาน</label>
            <select name="user_id" class="aikchol-input text-xs">
                <option value="">ทั้งหมด</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected((string) request('user_id') === (string) $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">ผลลัพธ์</label>
            <select name="outcome" class="aikchol-input text-xs">
                <option value="">ทั้งหมด</option>
                <option value="success" @selected(request('outcome') === 'success')>success</option>
                <option value="failed" @selected(request('outcome') === 'failed')>failed</option>
                <option value="denied" @selected(request('outcome') === 'denied')>denied</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">ตั้งแต่วันที่</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="aikchol-input text-xs">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">ถึงวันที่</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="aikchol-input text-xs">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="aikchol-btn-primary flex-1 py-2 rounded-lg text-xs font-semibold cursor-pointer">กรอง</button>
            <a href="{{ route('audit-logs.index') }}" class="px-3 py-2 rounded-lg text-xs border border-slate-200 text-slate-600">ล้าง</a>
        </div>
    </form>

    <div class="aikchol-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-slate-50 text-slate-500 uppercase border-b border-[#DCE6EF]">
                    <tr>
                        <th class="px-4 py-3">วันเวลา</th>
                        <th class="px-4 py-3">ผู้ใช้งาน</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">ผลลัพธ์</th>
                        <th class="px-4 py-3">Doc / Rev</th>
                        <th class="px-4 py-3">HN Hash (PDPA)</th>
                        <th class="px-4 py-3">IP / Request ID</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($logs as $log)
                        <tr class="hover:bg-[#F4F8FB]">
                            <td class="px-4 py-2.5 whitespace-nowrap font-mono text-slate-600">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                            <td class="px-4 py-2.5 font-medium text-slate-800">{{ $log->user?->name ?? 'System / Guest' }}</td>
                            <td class="px-4 py-2.5 font-mono font-semibold text-[#004F85]">{{ $log->action }}</td>
                            <td class="px-4 py-2.5">
                                <span class="px-2 py-0.5 rounded-full font-semibold {{ $log->outcome === 'success' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $log->outcome }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 font-mono text-slate-600">
                                {{ $log->document_id ? 'Doc#' . $log->document_id : '-' }}
                                {{ $log->revision_id ? '/Rev#' . $log->revision_id : '' }}
                            </td>
                            <td class="px-4 py-2.5 font-mono text-[11px] text-slate-500" title="{{ $log->hn_hash }}">
                                {{ $log->hn_hash ? \Illuminate\Support\Str::limit($log->hn_hash, 16) : '-' }}
                            </td>
                            <td class="px-4 py-2.5 font-mono text-[11px] text-slate-500">
                                {{ $log->ip_address }}<br>{{ \Illuminate\Support\Str::limit($log->request_id ?? '', 14) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">ไม่พบบันทึก Audit Log</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-[#DCE6EF]">{{ $logs->links() }}</div>
    </div>
</div>
@endsection
