@extends('layouts.app')

@section('title', 'จัดการผู้ใช้งาน')

@section('breadcrumbs')
    <span>/</span>
    <span class="text-[#004F85] font-medium">จัดการผู้ใช้งาน</span>
@endsection

@section('content')
<div class="space-y-6">
    <div>
        <h2 class="text-xl font-bold text-[#004F85]">จัดการผู้ใช้งานระบบ</h2>
        <p class="text-xs text-slate-500 mt-0.5">สร้างบัญชีผู้ใช้งาน กำหนดหน่วยงาน บทบาท และสถานะการใช้งาน (บันทึก Audit Log ทุกการเปลี่ยนแปลง)</p>
    </div>

    <!-- Create User Form -->
    <div class="aikchol-card p-6">
        <h3 class="text-sm font-bold text-[#004F85] mb-4">+ เพิ่มผู้ใช้งานใหม่</h3>
        <form method="POST" action="{{ route('admin.users.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">ชื่อ–นามสกุล *</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="aikchol-input text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">ชื่อผู้ใช้ (Username) *</label>
                <input type="text" name="username" value="{{ old('username') }}" required class="aikchol-input text-sm font-mono">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">รหัสพนักงาน</label>
                <input type="text" name="employee_code" value="{{ old('employee_code') }}" class="aikchol-input text-sm font-mono">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">อีเมล *</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="aikchol-input text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">หน่วยงาน</label>
                <select name="department_id" class="aikchol-input text-sm">
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name_th }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">รหัสผ่านเริ่มต้น (อย่างน้อย 10 ตัวอักษร) *</label>
                <input type="password" name="password" required minlength="10" class="aikchol-input text-sm">
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs font-medium text-slate-600 mb-1.5">บทบาท (Roles) *</label>
                <div class="flex flex-wrap gap-3">
                    @foreach($roles as $role)
                        <label class="inline-flex items-center gap-2 text-xs bg-slate-50 border border-slate-200 px-3 py-2 rounded-lg cursor-pointer">
                            <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" class="rounded text-[#004F85]">
                            <span>{{ $role->display_name_th }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="md:col-span-3 flex flex-col sm:flex-row sm:items-end justify-end gap-3">
                <div class="sm:w-64">
                    <label class="block text-xs font-medium text-slate-600 mb-1">รหัสผ่าน Admin ปัจจุบันเพื่อยืนยัน *</label>
                    <input type="password" name="current_password" required autocomplete="current-password" placeholder="••••••••" class="aikchol-input text-xs">
                </div>
                <button type="submit" class="aikchol-btn-primary px-5 py-2.5 rounded-lg text-xs font-semibold cursor-pointer">สร้างบัญชีผู้ใช้</button>
            </div>
        </form>
    </div>

    <!-- Users List -->
    <div class="aikchol-card overflow-hidden">
        <div class="divide-y divide-slate-100">
            @foreach($users as $u)
                <form method="POST" action="{{ route('admin.users.update', $u) }}" class="p-5 space-y-3">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1">ชื่อ–นามสกุล</label>
                            <input type="text" name="name" value="{{ $u->name }}" required class="aikchol-input text-xs">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1">Username</label>
                            <input type="text" name="username" value="{{ $u->username }}" required class="aikchol-input text-xs font-mono">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1">อีเมล</label>
                            <input type="email" name="email" value="{{ $u->email }}" required class="aikchol-input text-xs">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1">หน่วยงาน</label>
                            <select name="department_id" class="aikchol-input text-xs">
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" @selected($u->department_id === $dept->id)>{{ $dept->name_th }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1">รหัสผ่านใหม่ (เว้นว่างหากไม่เปลี่ยน)</label>
                            <input type="password" name="password" minlength="10" placeholder="••••••••" class="aikchol-input text-xs">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="inline-flex items-center gap-1.5 text-xs">
                                <input type="checkbox" name="is_active" value="1" @checked($u->is_active) class="rounded text-[#004F85]">
                                <span>เปิดใช้งาน</span>
                            </label>
                            <input type="password" name="current_password" required autocomplete="current-password" placeholder="รหัสผ่าน Admin *" class="aikchol-input text-xs">
                            <button type="submit" class="aikchol-btn-primary px-3 py-1.5 rounded-lg text-xs font-semibold cursor-pointer">บันทึก</button>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        <span class="text-[11px] text-slate-500">บทบาท:</span>
                        @foreach($roles as $role)
                            <label class="inline-flex items-center gap-1.5 text-xs bg-slate-50 border border-slate-200 px-2.5 py-1 rounded">
                                <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked($u->roles->contains('id', $role->id)) class="rounded text-[#004F85]">
                                <span>{{ $role->display_name_en ?? $role->name }}</span>
                            </label>
                        @endforeach
                        <span class="ml-auto text-[11px] text-slate-400">เข้าใช้ล่าสุด: {{ $u->last_login_at?->format('d/m/Y H:i') ?? 'ยังไม่เคยเข้าใช้' }}</span>
                    </div>
                </form>
            @endforeach
        </div>
        <div class="px-5 py-3 border-t border-[#DCE6EF]">{{ $users->links() }}</div>
    </div>
</div>
@endsection
