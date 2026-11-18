<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401, 'กรุณาเข้าสู่ระบบ');
        }

        $allowedPermissions = array_map('trim', explode('|', $permission));

        if (! $user->hasAnyPermission($allowedPermissions)) {
            abort(403, 'คุณไม่มีสิทธิ์ดำเนินการในส่วนนี้');
        }

        return $next($request);
    }
}
