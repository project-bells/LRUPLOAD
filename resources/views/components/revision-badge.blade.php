@props(['status'])

@php
    $value = $status instanceof \App\Enums\RevisionStatus ? $status->value : (string) $status;
    $classes = match ($value) {
        'current' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
        'superseded' => 'bg-slate-100 text-slate-700 border-slate-200',
        'withdrawn' => 'bg-red-100 text-red-800 border-red-200',
        default => 'bg-slate-100 text-slate-700 border-slate-200',
    };
    $label = match ($value) {
        'current' => 'ฉบับปัจจุบัน (Current)',
        'superseded' => 'ถูกแทนที่แล้ว (Superseded)',
        'withdrawn' => 'ถูกเพิกถอน (Withdrawn)',
        default => $value,
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {$classes}"]) }}>
    {{ $label }}
</span>
