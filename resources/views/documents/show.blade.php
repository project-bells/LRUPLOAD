@extends('layouts.app')

@section('title', $document->title . ' — ' . $document->document_number)

@section('breadcrumbs')
    <span>/</span>
    <a href="{{ route('documents.index') }}" class="hover:text-[#004F85]">รายการเอกสาร</a>
    <span>/</span>
    <span class="text-[#004F85] font-medium">{{ $document->document_number }}</span>
@endsection

@section('content')
<div class="space-y-6" x-data="{ showModalPreview: false }">
    <!-- Header & Primary Actions -->
    <div class="aikchol-card p-6">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-2 mb-1.5">
                    <span class="font-mono text-xs font-bold px-2.5 py-0.5 rounded bg-[#EBF4FA] text-[#004F85]">{{ $document->document_number }}</span>
                    <span class="text-xs px-2.5 py-0.5 rounded bg-slate-100 text-slate-700 font-medium">{{ $document->documentType?->name_th }}</span>
                    @if($document->currentRevision)
                        <x-revision-badge :status="$document->currentRevision->status" />
                        <span class="text-xs font-semibold text-slate-600">Rev. {{ $document->currentRevision->revision_number }}</span>
                    @endif
                </div>
                <h2 class="text-xl font-bold text-slate-900">{{ $document->title }}</h2>
                <p class="text-xs text-slate-500 mt-1">
                    ผู้จัดทำ: {{ $document->creator?->name }} • บันทึกเมื่อ: {{ $document->created_at?->format('d/m/Y H:i') }} • ระดับความลับ: {{ $document->confidentiality_level?->labelTh() }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if($document->currentRevision && auth()->user()->hasPermission('documents.preview'))
                    <!-- Desktop Large Modal Preview -->
                    <button type="button" @click="showModalPreview = true" class="hidden md:inline-flex aikchol-btn-primary px-4 py-2 rounded-lg text-xs font-semibold shadow-sm cursor-pointer">
                        เปิด Preview (Modal)
                    </button>
                    <!-- Full-screen / Mobile Preview -->
                    <a href="{{ route('documents.revisions.preview', [$document, $document->currentRevision]) }}" class="aikchol-btn-secondary px-4 py-2 rounded-lg text-xs font-semibold">
                        เปิดดูเต็มหน้าจอ
                    </a>
                @endif

                @if($document->currentRevision && auth()->user()->hasPermission('documents.download'))
                    <a href="{{ route('documents.revisions.download', [$document, $document->currentRevision]) }}" class="px-3.5 py-2 rounded-lg text-xs font-semibold bg-white border border-slate-300 text-slate-700 hover:bg-slate-50">
                        ดาวน์โหลด
                    </a>
                @endif

                @if(auth()->user()->hasPermission('documents.create_revision'))
                    <a href="{{ route('documents.revisions.create', $document) }}" class="px-3.5 py-2 rounded-lg text-xs font-semibold bg-[#004F85] text-white hover:bg-[#003B63]">
                        + เพิ่ม Revision
                    </a>
                @endif

                @if(auth()->user()->hasPermission('documents.edit_metadata'))
                    <a href="{{ route('documents.edit-metadata', $document) }}" class="px-3.5 py-2 rounded-lg text-xs font-semibold border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
                        แก้ไข Metadata
                    </a>
                @endif

                @if(auth()->user()->hasPermission('documents.request_delete') && ! $document->pendingDeleteRequest)
                    <a href="{{ route('documents.delete-request.create', $document) }}" class="px-3.5 py-2 rounded-lg text-xs font-semibold border border-red-200 bg-red-50 text-red-700 hover:bg-red-100">
                        ส่งคำขอลบ
                    </a>
                @endif
            </div>
        </div>

        @if($document->pendingDeleteRequest)
            <div class="mt-4 rounded-lg border border-amber-300 bg-[#FFF8E6] px-4 py-3 text-xs text-[#9A6000]">
                <strong>อยู่ระหว่างรออนุมัติคำขอลบ:</strong> ขอโดย {{ $document->pendingDeleteRequest->requester?->name }} — เหตุผล: {{ $document->pendingDeleteRequest->request_reason }}
            </div>
        @endif
    </div>

    <!-- Patient Snapshot & Document Metadata Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="aikchol-card p-5">
            <h3 class="text-sm font-bold text-[#004F85] mb-3">ข้อมูลผู้ป่วย ณ วันที่จัดเก็บเอกสาร (Immutable Snapshot)</h3>
            <div class="space-y-3">
                @forelse($document->patientSnapshots as $snap)
                    @php $sd = $snap->snapshot_data; @endphp
                    <div class="p-3.5 rounded-lg bg-[#EBF4FA]/50 border border-[#DCE6EF] text-xs space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="font-mono font-bold text-sm text-[#004F85]">HN: {{ $sd['hn'] ?? '-' }}</span>
                            <span class="px-2 py-0.5 rounded-full bg-white text-[#004F85] font-semibold border border-[#DCE6EF]">
                                {{ ($sd['patient_type'] ?? '') === 'child' ? 'ทารก' : 'มารดา' }}
                            </span>
                        </div>
                        <p class="font-semibold text-slate-800">
                            {{ trim(($sd['title_name'] ?? '') . ($sd['first_name'] ?? '') . ' ' . ($sd['last_name'] ?? '')) }}
                        </p>
                        <p class="text-slate-500">AN: {{ $sd['admission_number'] ?? '-' }} • Ward: {{ $sd['ward'] ?? '-' }}</p>
                        <p class="text-[11px] text-slate-400">บันทึกหลักฐาน Snapshot เมื่อ: {{ $snap->created_at?->format('d/m/Y H:i') }}</p>
                    </div>
                @empty
                    <p class="text-xs text-slate-500">ไม่มีข้อมูล Snapshot</p>
                @endforelse
            </div>
        </div>

        <div class="aikchol-card p-5">
            <h3 class="text-sm font-bold text-[#004F85] mb-3">ข้อมูล Metadata เอกสาร</h3>
            <dl class="grid grid-cols-2 gap-y-2.5 gap-x-4 text-xs">
                <div><dt class="text-slate-500">เจ้าของเอกสาร</dt><dd class="font-semibold text-slate-800 mt-0.5">{{ $document->patient_owner_type?->labelTh() }}</dd></div>
                <div><dt class="text-slate-500">วันที่เอกสาร</dt><dd class="font-semibold text-slate-800 mt-0.5">{{ $document->document_date?->format('d/m/Y') }}</dd></div>
                <div><dt class="text-slate-500">วันที่รับเอกสาร</dt><dd class="text-slate-800 mt-0.5">{{ $document->received_date?->format('d/m/Y') ?? '-' }}</dd></div>
                <div><dt class="text-slate-500">เลขที่อ้างอิง</dt><dd class="font-mono text-slate-800 mt-0.5">{{ $document->reference_number ?? '-' }}</dd></div>
                <div class="col-span-2"><dt class="text-slate-500">รายละเอียด</dt><dd class="text-slate-800 mt-0.5">{{ $document->description ?: '-' }}</dd></div>
                <div class="col-span-2"><dt class="text-slate-500">หมายเหตุ</dt><dd class="text-slate-800 mt-0.5">{{ $document->notes ?: '-' }}</dd></div>
            </dl>
        </div>
    </div>

    <!-- Revision History Summary -->
    <div class="aikchol-card overflow-hidden">
        <div class="px-5 py-4 border-b border-[#DCE6EF] flex items-center justify-between">
            <div>
                <h3 class="font-bold text-[#004F85]">ประวัติ Revision ({{ $document->revisions->count() }} เวอร์ชัน)</h3>
                <p class="text-xs text-slate-500">ไฟล์เดิมทุกฉบับถูกเก็บรักษาไว้ครบถ้วน ไม่มีการเขียนทับ</p>
            </div>
            <a href="{{ route('documents.revisions.index', $document) }}" class="text-xs font-semibold text-[#004F85] hover:underline">ดูประวัติฉบับเต็ม →</a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase border-b border-[#DCE6EF]">
                    <tr>
                        <th class="px-5 py-3">Rev</th>
                        <th class="px-5 py-3">สถานะ</th>
                        <th class="px-5 py-3">ไฟล์ / SHA-256</th>
                        <th class="px-5 py-3">เหตุผลการเปลี่ยนแปลง</th>
                        <th class="px-5 py-3">ผู้สร้าง / วันเวลา</th>
                        <th class="px-5 py-3 text-right">ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($document->revisions as $rev)
                        <tr class="hover:bg-[#F4F8FB]">
                            <td class="px-5 py-3 font-bold text-[#004F85]">Rev. {{ $rev->revision_number }}</td>
                            <td class="px-5 py-3"><x-revision-badge :status="$rev->status" /></td>
                            <td class="px-5 py-3 text-xs">
                                <p class="font-medium text-slate-800">{{ $rev->storedFile?->original_name }}</p>
                                <p class="text-[11px] font-mono text-slate-400" title="{{ $rev->storedFile?->sha256_hash }}">
                                    {{ $rev->storedFile?->formattedSize() }} • {{ \Illuminate\Support\Str::limit($rev->storedFile?->sha256_hash ?? '', 18) }}
                                </p>
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-700">{{ $rev->change_reason ?? '-' }}</td>
                            <td class="px-5 py-3 text-xs text-slate-500">
                                {{ $rev->creator?->name }}<br>{{ $rev->created_at?->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-5 py-3 text-right space-x-2">
                                @if($rev->isCurrent() || auth()->user()->hasPermission('revisions.view_all'))
                                    @if(auth()->user()->hasPermission('documents.preview'))
                                        <a href="{{ route('documents.revisions.preview', [$document, $rev]) }}" class="text-xs font-semibold text-[#004F85] hover:underline">Preview</a>
                                    @endif
                                    @if(auth()->user()->hasPermission('documents.download'))
                                        <a href="{{ route('documents.revisions.download', [$document, $rev]) }}" class="text-xs font-semibold text-[#1F8E96] hover:underline">ดาวน์โหลด</a>
                                    @endif
                                @else
                                    <span class="text-[11px] text-slate-400">จำกัดสิทธิ์</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Direct Soft Delete Section for Supervisor / Admin -->
    @if(auth()->user()->hasPermission('documents.soft_delete'))
        <div class="aikchol-card p-5 border-red-200 bg-red-50/30">
            <h3 class="text-sm font-bold text-red-800 mb-1">ดำเนินการ Soft Delete โดยตรง (สำหรับหัวหน้า/ผู้ดูแลระบบ)</h3>
            <p class="text-xs text-slate-600 mb-3">การ Soft Delete จะซ่อนเอกสารจากรายการทั่วไป โดยไม่ลบไฟล์จริง Revision หรือ Audit Log และสามารถกู้คืนได้</p>
            <form method="POST" action="{{ route('documents.soft-delete', $document) }}" onsubmit="return confirm('ยืนยันย้ายเอกสารนี้เข้าสู่ถังเอกสาร (Soft Delete)?')" class="flex flex-col sm:flex-row gap-3">
                @csrf
                <input type="text" name="delete_reason" required minlength="5" placeholder="ระบุเหตุผลในการย้ายเข้าถังเอกสาร..." class="aikchol-input text-xs flex-1">
                <button type="submit" class="px-4 py-2 rounded-lg text-xs font-semibold bg-red-700 text-white hover:bg-red-800 shrink-0 cursor-pointer">
                    ย้ายเข้าถังเอกสาร (Soft Delete)
                </button>
            </form>
        </div>
    @endif

    <!-- Large Desktop Preview Modal -->
    @if($document->currentRevision && auth()->user()->hasPermission('documents.preview'))
        <template x-if="showModalPreview">
            <div
                @keydown.escape.window="showModalPreview = false"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 p-4"
            >
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[92vh] flex flex-col overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-[#DCE6EF] flex items-center justify-between bg-[#F4F8FB]">
                        <div class="flex items-center gap-3 min-w-0">
                            <img src="{{ route('branding.logo') }}" alt="{{ $branding->hospital_name_en }}" class="h-7 w-auto object-contain">
                            <div class="truncate">
                                <p class="text-sm font-bold text-[#004F85] truncate">{{ $document->title }} (Rev. {{ $document->currentRevision->revision_number }})</p>
                                <p class="text-[11px] text-slate-500">{{ $document->document_number }}</p>
                            </div>
                        </div>
                        <button type="button" @click="showModalPreview = false" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-200 text-slate-700 hover:bg-slate-300 cursor-pointer">
                            ปิดหน้าต่าง ✕
                        </button>
                    </div>
                    <div class="p-4 flex-1 overflow-auto">
                        <x-pdf-preview-box
                            :stream-url="$previewUrls[$document->currentRevision->id]"
                            :mime-type="$document->currentRevision->storedFile?->mime_type ?? 'application/pdf'"
                            :extension="$document->currentRevision->storedFile?->extension ?? 'pdf'"
                            :original-name="$document->currentRevision->storedFile?->original_name ?? 'document'"
                            :file-size-formatted="$document->currentRevision->storedFile?->formattedSize() ?? ''"
                            :download-url="auth()->user()->hasPermission('documents.download') ? route('documents.revisions.download', [$document, $document->currentRevision]) : null"
                            height-class="h-[74vh]"
                        />
                    </div>
                </div>
            </div>
        </template>
    @endif
</div>
@endsection
