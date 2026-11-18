<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>เข้าสู่ระบบ — {{ $branding->hospital_name_en }} | ระบบจัดเก็บเอกสารผู้ป่วย แผนกห้องคลอด</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --brand-primary: {{ $branding->primary_color ?? '#004F85' }};
            --brand-secondary: {{ $branding->secondary_color ?? '#3EC0C8' }};
            --brand-accent: {{ $branding->accent_color ?? '#FBB435' }};
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-[#EBF4FA] via-[#F4F8FB] to-[#EAF8F9] flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="aikchol-card overflow-hidden shadow-lg">
            <!-- Top Brand Stripe -->
            <div class="h-1.5 bg-gradient-to-r from-[#004F85] via-[#3EC0C8] to-[#FBB435]"></div>

            <div class="p-8">
                <!-- Unmodified Official Aikchol Hospital Logo with clear space -->
                <div class="flex justify-center py-2 mb-5">
                    <img
                        src="{{ route('branding.logo') }}"
                        alt="{{ $branding->hospital_name_en }}"
                        class="h-14 w-auto max-w-full object-contain"
                    >
                </div>

                <div class="text-center mb-6">
                    <h1 class="text-lg font-bold text-[#004F85]">
                        ระบบจัดเก็บเอกสารผู้ป่วย {{ $branding->department_name_th }}
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        {{ $branding->hospital_name_en }} Labor Room Document Management System
                    </p>
                </div>

                @if(session('status'))
                    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-xs text-emerald-800">
                        {{ session('status') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3.5 py-2.5 text-xs text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.submit') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="login" class="block text-sm font-medium text-slate-700 mb-1">ชื่อผู้ใช้ หรือ อีเมล</label>
                        <input
                            id="login"
                            name="login"
                            type="text"
                            value="{{ old('login') }}"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="กรอกชื่อผู้ใช้หรืออีเมลโรงพยาบาล"
                            class="aikchol-input"
                        >
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-700 mb-1">รหัสผ่าน</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                            class="aikchol-input"
                        >
                    </div>

                    <div class="flex items-center justify-between text-xs text-slate-600">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-[#004F85]">
                            <span>จดจำการเข้าสู่ระบบ</span>
                        </label>
                        <span class="text-slate-400">PDPA Protected</span>
                    </div>

                    <button type="submit" class="aikchol-btn-primary w-full py-2.5 rounded-lg text-sm font-semibold shadow-sm cursor-pointer">
                        เข้าสู่ระบบ
                    </button>
                </form>
            </div>

            <div class="bg-slate-50 border-t border-[#DCE6EF] px-6 py-3 text-center text-[11px] text-slate-500">
                การเข้าใช้งานทุกครั้งจะถูกบันทึกไว้ใน Audit Log ตามนโยบายความปลอดภัยของ {{ $branding->hospital_name_en }}
            </div>
        </div>
    </div>
</body>
</html>
