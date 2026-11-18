@props([
    'streamUrl',
    'mimeType' => 'application/pdf',
    'extension' => 'pdf',
    'originalName' => 'document.pdf',
    'fileSizeFormatted' => '',
    'downloadUrl' => null,
    'heightClass' => 'h-[65vh]',
])

@php
    $ext = strtolower((string) $extension);
    $isPdf = $ext === 'pdf' || $mimeType === 'application/pdf';
    $isImage = in_array($ext, ['jpg', 'jpeg', 'png'], true) || str_starts_with((string) $mimeType, 'image/');
@endphp

@if($isPdf)
    <div x-data="aikcholPdfViewer(@js($streamUrl))" x-ref="viewerRoot" class="border border-[#DCE6EF] rounded-xl overflow-hidden bg-slate-900 flex flex-col {{ $heightClass }}">
        <!-- PDF Toolbar -->
        <div class="bg-[#004F85] text-white px-3 py-2 flex flex-wrap items-center justify-between gap-2 text-xs">
            <div class="flex items-center gap-1.5">
                <button type="button" @click="prevPage()" :disabled="pageNum <= 1" class="px-2 py-1 rounded bg-white/15 hover:bg-white/25 disabled:opacity-40">
                    ◀ ก่อนหน้า
                </button>
                <span class="px-2">
                    หน้า <input type="number" min="1" :max="pageCount" :value="pageNum" @change="goToPage($event)" class="w-12 text-center rounded bg-white text-slate-900 px-1 py-0.5 font-semibold"> / <span x-text="pageCount || '-'"></span>
                </span>
                <button type="button" @click="nextPage()" :disabled="pageNum >= pageCount" class="px-2 py-1 rounded bg-white/15 hover:bg-white/25 disabled:opacity-40">
                    ถัดไป ▶
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-1.5">
                <button type="button" @click="zoomOut()" class="px-2 py-1 rounded bg-white/15 hover:bg-white/25" title="ย่อ">− ย่อ</button>
                <span class="px-1.5 font-mono" x-text="Math.round(scale * 100) + '%'"></span>
                <button type="button" @click="zoomIn()" class="px-2 py-1 rounded bg-white/15 hover:bg-white/25" title="ขยาย">+ ขยาย</button>
                <button type="button" @click="fitWidth()" class="px-2 py-1 rounded bg-white/15 hover:bg-white/25">พอดีความกว้าง</button>
                <button type="button" @click="rotateClockwise()" class="px-2 py-1 rounded bg-white/15 hover:bg-white/25">↻ หมุน 90°</button>
                <button type="button" @click="toggleFullscreen()" class="px-2 py-1 rounded bg-[#3EC0C8] text-slate-950 font-semibold hover:brightness-95">
                    <span x-text="isFullscreen ? 'ออกเต็มจอ' : 'เต็มหน้าจอ'"></span>
                </button>
                @if($downloadUrl)
                    <a href="{{ $downloadUrl }}" class="px-2.5 py-1 rounded bg-[#FBB435] text-slate-950 font-semibold hover:brightness-95">
                        ดาวน์โหลด
                    </a>
                @endif
            </div>
        </div>

        <!-- Canvas Viewport -->
        <div x-ref="canvasContainer" class="flex-1 overflow-auto bg-slate-800 flex flex-col items-center justify-start p-4 relative">
            <div x-show="loading" class="my-auto text-center text-white/90 py-12">
                <div class="inline-block w-8 h-8 border-4 border-[#3EC0C8] border-t-transparent rounded-full animate-spin mb-3"></div>
                <p class="text-sm">กำลังโหลดตัวอย่างเอกสาร PDF...</p>
            </div>

            <div x-show="error" x-cloak class="my-auto max-w-md bg-white rounded-xl p-5 text-center text-slate-800 shadow">
                <p class="font-semibold text-red-700 mb-1">ไม่สามารถแสดงตัวอย่าง PDF</p>
                <p class="text-xs text-slate-600 mb-3" x-text="error"></p>
                <button type="button" @click="loadDocument(url)" class="aikchol-btn-primary px-4 py-1.5 rounded-lg text-xs font-semibold">ลองใหม่</button>
            </div>

            <canvas x-ref="pdfCanvas" x-show="!loading && !error" class="shadow-xl bg-white rounded"></canvas>
        </div>
    </div>
@elseif($isImage)
    <div class="border border-[#DCE6EF] rounded-xl overflow-hidden bg-slate-900">
        <div class="bg-[#004F85] text-white px-4 py-2 flex items-center justify-between text-xs">
            <span class="font-medium truncate">ตัวอย่างรูปภาพ: {{ $originalName }}</span>
            @if($downloadUrl)
                <a href="{{ $downloadUrl }}" class="px-2.5 py-1 rounded bg-[#FBB435] text-slate-950 font-semibold">ดาวน์โหลด</a>
            @endif
        </div>
        <div class="p-4 flex items-center justify-center bg-slate-800 max-h-[65vh] overflow-auto">
            <img src="{{ $streamUrl }}" alt="{{ $originalName }}" class="max-w-full max-h-[58vh] object-contain rounded shadow-lg bg-white">
        </div>
    </div>
@else
    <div class="border border-[#DCE6EF] rounded-xl bg-slate-50 p-6 text-center">
        <div class="w-12 h-12 rounded-full bg-[#EBF4FA] text-[#004F85] flex items-center justify-center mx-auto mb-3 font-bold uppercase text-xs">
            {{ $ext }}
        </div>
        <p class="font-semibold text-slate-800">{{ $originalName }}</p>
        <p class="text-xs text-slate-500 mt-1">เบราว์เซอร์ไม่รองรับการแสดงตัวอย่างไฟล์ประเภท {{ strtoupper($ext) }} โดยตรง {{ $fileSizeFormatted ? "({$fileSizeFormatted})" : '' }}</p>
        @if($downloadUrl)
            <a href="{{ $downloadUrl }}" class="aikchol-btn-primary inline-flex items-center gap-2 mt-4 px-4 py-2 rounded-lg text-xs font-semibold">
                ดาวน์โหลดไฟล์เพื่อเปิดตรวจสอบ
            </a>
        @endif
    </div>
@endif
