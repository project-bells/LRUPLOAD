<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {
    }

    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $login = trim((string) $request->validated('login'));
        $password = (string) $request->validated('password');
        $remember = (bool) $request->boolean('remember');

        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $candidate = User::query()->where($field, $login)->first();
        if ($candidate !== null && ! $candidate->is_active) {
            $this->auditLog->record(
                action: 'login_failed',
                user: $candidate,
                outcome: 'denied',
                afterData: ['reason' => 'account_inactive'],
                request: $request,
            );

            return back()
                ->withInput($request->only('login'))
                ->withErrors(['login' => 'บัญชีผู้ใช้งานถูกระงับ กรุณาติดต่อผู้ดูแลระบบ']);
        }

        if (! Auth::attempt([$field => $login, 'password' => $password, 'is_active' => true], $remember)) {
            $this->auditLog->record(
                action: 'login_failed',
                user: $candidate,
                outcome: 'failed',
                afterData: ['login_field' => $field],
                request: $request,
            );

            return back()
                ->withInput($request->only('login'))
                ->withErrors(['login' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง']);
        }

        // Prevent session fixation
        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->save();

        $this->auditLog->record(
            action: 'login',
            user: $user,
            outcome: 'success',
            request: $request,
        );

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->auditLog->record(
                action: 'logout',
                user: $user,
                outcome: 'success',
                request: $request,
            );
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'ออกจากระบบเรียบร้อยแล้ว');
    }
}
