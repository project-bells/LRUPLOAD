<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="pdf-worker-url" content="{{ asset('js/pdf.worker.min.js') }}">
    <title>@yield('title', 'หน้าหลัก') — {{ $branding->hospital_name_en }} | ระบบจัดเก็บเอกสารผู้ป่วย แผนกห้องคลอด</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --brand-primary: {{ $branding->primary_color ?? '#004F85' }};
            --brand-secondary: {{ $branding->secondary_color ?? '#3EC0C8' }};
            --brand-accent: {{ $branding->accent_color ?? '#FBB435' }};
        }
    </style>
</head>
<body class="min-h-screen bg-[#F4F8FB] text-slate-800" x-data="{ sidebarOpen: false }">
    <div class="flex min-h-screen">
        <!-- Mobile Sidebar Backdrop -->
        <div
            x-show="sidebarOpen"
            x-cloak
            @click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden no-print"
        ></div>

        <!-- Sidebar -->
        <aside
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            class="fixed inset-y-0 left-0 z-40 w-72 bg-white border-r border-[#DCE6EF] transition-transform duration-200 ease-in-out flex flex-col lg:static lg:inset-auto no-print"
        >
            <!-- Aikchol Hospital Logo Area with generous clear space -->
            <div class="px-5 py-5 border-b border-[#DCE6EF] bg-white">
                <a href="{{ route('dashboard') }}" class="block focus:outline-none">
                    <div class="flex items-center justify-center py-1">
                        <img
                            src="{{ route('branding.logo') }}"
                            alt="{{ $branding->hospital_name_en }}"
                            class="h-11 w-auto max-w-full object-contain"
                        >
                    </div>
                    <div class="mt-3 pt-2.5 border-t border-slate-100 text-center">
                        <p class="text-xs font-semibold tracking-wide text-[#004F85]">
                            {{ $branding->hospital_name_en }}
                        </p>
                        <p class="text-[11px] text-slate-500 mt-0.5">
                            {{ $branding->department_name_th }} • Document Management
                        </p>
                    </div>
                </a>
            </div>

            <!-- Navigation Menu -->
            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1 text-sm">
                <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">เมนูหลัก</p>

                <a href="{{ route('dashboard') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition {{ request()->routeIs('dashboard') ? 'bg-[#EBF4FA] text-[#004F85]' : 'text-slate-600 hover:bg-slate-50 hover:text-[#004F85]' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span>ภาพรวมระบบ (Dashboard)</span>
                </a>

                @if(auth()->user()?->hasPermission('patients.lookup'))
                    <a href="{{ route('patients.lookup.index') }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition {{ request()->routeIs('patients.lookup*') ? 'bg-[#EBF4FA] text-[#004F85]' : 'text-slate-600 hover:bg-slate-50 hover:text-[#004F85]' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                        <span>ค้นหา HN ผู้ป่วย (HIS)</span>
                    </a>
                @endif

                @if(auth()->user()?->hasPermission('patients.view'))
                    <a href="{{ route('patients.index') }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition {{ request()->routeIs('patients.index', 'patients.show', 'patients.relationships*') ? 'bg-[#EBF4FA] text-[#004F85]' : 'text-slate-600 hover:bg-slate-50 hover:text-[#004F85]' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span>ทะเบียนผู้ป่วย (มารดา / ทารก)</span>
                    </a>
                @endif

                @if(auth()->user()?->hasPermission('documents.view'))
                    <a href="{{ route('documents.index') }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition {{ request()->routeIs('documents.index', 'documents.show', 'documents.create', 'documents.review', 'documents.revisions*', 'documents.edit-metadata') ? 'bg-[#EBF4FA] text-[#004F85]' : 'text-slate-600 hover:bg-slate-50 hover:text-[#004F85]' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span>รายการเอกสารทั้งหมด</span>
                    </a>
                @endif

                @if(auth()->user()?->hasAnyPermission(['delete_requests.review', 'documents.restore', 'documents.soft_delete', 'audit.view_department', 'audit.view_all']))
                    <div class="pt-4 mt-3 border-t border-slate-100">
                        <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">การกำกับดูแลและตรวจสอบ</p>

                        @if(auth()->user()?->hasPermission('delete_requests.review'))
                            <a href="{{ route('delete-requests.index') }}"
                               class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition {{ request()->routeIs('delete-requests.*') ? 'bg-[#EBF4FA] text-[#004F85]' : 'text-slate-600 hover:bg-slate-50 hover:text-[#004F85]' }}">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                <span>คำขอลบรออนุมัติ</span>
                            </a>
                        @endif

                        @if(auth()->user()?->hasAnyPermission(['documents.restore', 'documents.soft_delete', 'documents.permanent_delete']))
                            <a href="{{ route('documents.trash') }}"
                               class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition {{ request()->routeIs('documents.trash') ? 'bg-[#EBF4FA] text-[#004F85]' : 'text-slate-600 hover:bg-slate-50 hover:text-[#004F85]' }}">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                <span>ถังเอกสาร (Soft Deleted)</span>
                            </a>
                        @endif

                        @if(auth()->user()?->hasAnyPermission(['audit.view_department', 'audit.view_all']))
                            <a href="{{ route('audit-logs.index') }}"
                               class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition {{ request()->routeIs('audit-logs.*') ? 'bg-[#EBF4FA] text-[#004F85]' : 'text-slate-600 hover:bg-slate-50 hover:text-[#004F85]' }}">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                <span>Audit Log (บันทึกการใช้งาน)</span>
                            </a>
                        @endif
                    </div>
                @endif

                @if(auth()->user()?->hasAnyPermission(['users.manage', 'roles.manage', 'document_types.manage', 'settings.manage', 'branding.manage']))
                    <div class="pt-4 mt-3 border-t border-slate-100">
                        <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">ผู้ดูแลระบบ</p>

                        @if(auth()->user()?->hasPermission('users.manage'))
                            <a href="{{ route('admin.users.index') }}"
                               class="flex items-center gap-3 px-3 py-2 rounded-lg font-medium transition {{ request()->routeIs('admin.users.*') ? 'bg-[#EBF4FA] text-[#004F85]' : 'text-slate-600 hover:bg-slate-50 hover:text-[#004F85]' }}">
                                <span>จัดการผู้ใช้งาน</span>
                            </a>
                        @endif
                        @if(auth()->user()?->hasPermission('roles.manage'))
                            <a href="{{ route('admin.roles.index') }}"
                               class="flex items-center gap-3 px-3 py-2 rounded-lg font-medium transition {{ request()->routeIs('admin.roles.*') ? 'bg-[#EBF4FA] text-[#004F85]' : 'text-slate-600 hover:bg-slate-50 hover:text-[#004F85]' }}">
                                <span>จัดการบทบาทและสิทธิ์</span>
                            </a>
                        @endif
                        @if(auth()->user()?->hasPermission('document_types.manage'))
                            <a href="{{ route('admin.document-types.index') }}"
                               class="flex items-center gap-3 px-3 py-2 rounded-lg font-medium transition {{ request()->routeIs('admin.document-types.*') ? 'bg-[#EBF4FA] text-[#004F85]' : 'text-slate-600 hover:bg-slate-50 hover:text-[#004F85]' }}">
                                <span>จัดการประเภทเอกสาร</span>
                            </a>
                        @endif
                        @if(auth()->user()?->hasPermission('settings.manage'))
                            <a href="{{ route('admin.settings.index') }}"
                               class="flex items-center gap-3 px-3 py-2 rounded-lg font-medium transition {{ request()->routeIs('admin.settings.*') ? 'bg-[#EBF4FA] text-[#004F85]' : 'text-slate-600 hover:bg-slate-50 hover:text-[#004F85]' }}">
                                <span>ตั้งค่าระบบและ SQL Server</span>
                            </a>
                        @endif
                        @if(auth()->user()?->hasPermission('branding.manage'))
                            <a href="{{ route('admin.branding.index') }}"
                               class="flex items-center gap-3 px-3 py-2 rounded-lg font-medium transition {{ request()->routeIs('admin.branding.*') ? 'bg-[#EBF4FA] text-[#004F85]' : 'text-slate-600 hover:bg-slate-50 hover:text-[#004F85]' }}">
                                <span>ตั้งค่า Branding และโลโก้</span>
                            </a>
                        @endif
                    </div>
                @endif
            </nav>

            <!-- User Footer -->
            <div class="p-4 border-t border-[#DCE6EF] bg-slate-50/70">
                <div class="text-xs">
                    <p class="font-semibold text-slate-800 truncate">{{ auth()->user()?->name }}</p>
                    <p class="text-slate-500 truncate">{{ auth()->user()?->primaryRoleLabel() }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="w-full py-1.5 px-3 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-red-50 hover:text-red-700 hover:border-red-200 transition">
                        ออกจากระบบ
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Workspace -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Top Navigation Bar -->
            <header class="sticky top-0 z-20 bg-white/95 backdrop-blur border-b border-[#DCE6EF] px-4 sm:px-6 h-16 flex items-center justify-between gap-4 no-print">
                <div class="flex items-center gap-3 min-w-0">
                    <button type="button" @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100" aria-label="เปิดเมนู">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <img src="{{ route('branding.logo') }}" alt="{{ $branding->hospital_name_en }}" class="h-7 w-auto object-contain lg:hidden">
                    <div class="hidden sm:block truncate">
                        <h1 class="text-sm font-semibold text-[#004F85] truncate">
                            ระบบจัดเก็บเอกสารผู้ป่วย {{ $branding->department_name_th }} — {{ $branding->hospital_name_th }}
                        </h1>
                        <p class="text-[11px] text-slate-500 truncate">{{ $branding->hospital_name_en }} Labor Room Document Management System</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    @if(auth()->user()?->hasPermission('patients.lookup'))
                        <a href="{{ route('patients.lookup.index') }}" class="aikchol-btn-primary inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-semibold shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                            <span>ค้นหา HN</span>
                        </a>
                    @endif
                </div>
            </header>

            <!-- Print Header (visible only when printing reports/reviews) -->
            <div class="hidden print:flex items-center justify-between border-b-2 border-[#004F85] pb-3 mb-4 px-6 pt-4">
                <img src="{{ route('branding.logo') }}" alt="{{ $branding->hospital_name_en }}" class="h-12 w-auto object-contain">
                <div class="text-right">
                    <p class="font-bold text-[#004F85]">{{ $branding->hospital_name_en }} ({{ $branding->hospital_name_th }})</p>
                    <p class="text-xs text-slate-600">ระบบจัดเก็บเอกสารผู้ป่วย {{ $branding->department_name_th }}</p>
                </div>
            </div>

            <!-- Page Content -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
                <!-- Breadcrumb -->
                @hasSection('breadcrumbs')
                    <nav aria-label="Breadcrumb" class="mb-4 text-xs text-slate-500 flex flex-wrap items-center gap-1.5 no-print">
                        <a href="{{ route('dashboard') }}" class="hover:text-[#004F85]">หน้าหลัก</a>
                        @yield('breadcrumbs')
                    </nav>
                @endif

                <!-- Flash Success Message -->
                @if(session('status'))
                    <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 flex items-start gap-2.5">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                <!-- Validation / Error Summary -->
                @if($errors->any())
                    <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <p class="font-semibold mb-1">กรุณาตรวจสอบข้อมูลต่อไปนี้:</p>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>

            <footer class="border-t border-[#DCE6EF] bg-white px-6 py-3 text-xs text-slate-500 flex flex-col sm:flex-row items-center justify-between gap-2 no-print">
                <span>&copy; {{ date('Y') }} {{ $branding->hospital_name_en }} — {{ $branding->department_name_th }}</span>
                <span>Protected under PDPA • Confidential Medical Records</span>
            </footer>
        </div>
    </div>
</body>
</html>
