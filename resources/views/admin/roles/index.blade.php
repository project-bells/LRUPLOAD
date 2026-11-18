@extends('layouts.app')

@section('title', 'จัดการบทบาทและสิทธิ์ (RBAC)')

@section('breadcrumbs')
    <span>/</span>
    <span class="text-[#004F85] font-medium">จัดการบทบาทและสิทธิ์</span>
@endsection

@section('content')
<div class="space-y-6">
    <div>
        <h2 class="text-xl font-bold text-[#004F85]">จัดการบทบาทและสิทธิ์การเข้าถึง (Roles & Permissions)</h2>
        <p class="text-xs text-slate-500 mt-0.5">ทุกสิทธิ์ถูกบังคับใช้ฝั่งเซิร์ฟเวอร์ผ่าน Policies และ Middleware • ทุกการปรับสิทธิ์จะถูกบันทึกใน Audit Log</p>
    </div>

    <div class="space-y-5">
        @foreach($roles as $role)
            <form method="POST" action="{{ route('admin.roles.permissions.update', $role) }}" class="aikchol-card overflow-hidden">
                @csrf
                @method('PUT')
                <div class="px-6 py-4 bg-[#EBF4FA]/60 border-b border-[#DCE6EF] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <h3 class="text-base font-bold text-[#004F85]">{{ $role->display_name_th }}</h3>
                        <p class="text-xs text-slate-600">{{ $role->description }} • ผู้ใช้งานในบทบาทนี้: {{ $role->users_count }} คน</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <input type="password" name="current_password" required autocomplete="current-password" placeholder="รหัสผ่าน Admin เพื่อยืนยัน *" class="aikchol-input text-xs !w-52">
                        <button type="submit" class="aikchol-btn-primary px-4 py-2 rounded-lg text-xs font-semibold cursor-pointer">
                            บันทึกสิทธิ์ของ {{ $role->display_name_en }}
                        </button>
                    </div>
                </div>

                <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach($permissionsGrouped as $groupName => $perms)
                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#004F85] mb-2">{{ $groupName }}</p>
                            <div class="space-y-1.5">
                                @foreach($perms as $perm)
                                    <label class="flex items-start gap-2 text-xs cursor-pointer">
                                        <input
                                            type="checkbox"
                                            name="permission_ids[]"
                                            value="{{ $perm->id }}"
                                            @checked($role->permissions->contains('id', $perm->id))
                                            class="mt-0.5 rounded text-[#004F85]"
                                        >
                                        <span>
                                            <strong class="font-mono text-[11px] text-slate-800">{{ $perm->name }}</strong>
                                            <span class="block text-[11px] text-slate-500">{{ $perm->display_name_th }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </form>
        @endforeach
    </div>
</div>
@endsection
