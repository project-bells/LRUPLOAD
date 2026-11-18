<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrandingSetting;
use App\Services\AuditLogService;
use finfo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BrandingController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {
    }

    public function index(): View
    {
        return view('admin.branding.index', [
            'brandingSetting' => BrandingSetting::current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hospital_name_th' => ['required', 'string', 'max:150'],
            'hospital_name_en' => ['required', 'string', 'max:150'],
            'department_name_th' => ['required', 'string', 'max:150'],
            'department_name_en' => ['required', 'string', 'max:150'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'reset_logo' => ['nullable', 'boolean'],
        ]);

        $setting = BrandingSetting::query()->first() ?? new BrandingSetting();
        $logoPath = $setting->logo_path;

        if ($request->boolean('reset_logo')) {
            if ($logoPath !== null && Storage::disk('private_branding')->exists($logoPath)) {
                Storage::disk('private_branding')->delete($logoPath);
            }
            $logoPath = null;
        } elseif ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string) $finfo->file((string) $file->getRealPath());
            if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
                return back()->withErrors(['logo' => 'ไฟล์โลโก้ต้องเป็นภาพ PNG, JPEG หรือ WEBP ที่ถูกต้องเท่านั้น']);
            }

            $ext = strtolower($file->getClientOriginalExtension());
            $filename = 'logo-' . Str::uuid() . '.' . $ext;
            Storage::disk('private_branding')->putFileAs('', $file, $filename);

            if ($logoPath !== null && Storage::disk('private_branding')->exists($logoPath)) {
                Storage::disk('private_branding')->delete($logoPath);
            }
            $logoPath = $filename;
        }

        $setting->fill([
            'hospital_name_th' => (string) $validated['hospital_name_th'],
            'hospital_name_en' => (string) $validated['hospital_name_en'],
            'department_name_th' => (string) $validated['department_name_th'],
            'department_name_en' => (string) $validated['department_name_en'],
            'logo_path' => $logoPath,
            'primary_color' => strtoupper((string) $validated['primary_color']),
            'secondary_color' => strtoupper((string) $validated['secondary_color']),
            'accent_color' => strtoupper((string) $validated['accent_color']),
            'updated_by' => $request->user()?->id,
        ]);
        $setting->save();

        $this->auditLog->record(
            action: 'change_branding',
            user: $request->user(),
            outcome: 'success',
            afterData: [
                'hospital_name_en' => $setting->hospital_name_en,
                'primary_color' => $setting->primary_color,
                'secondary_color' => $setting->secondary_color,
                'accent_color' => $setting->accent_color,
                'custom_logo' => $logoPath !== null,
            ],
            request: $request,
        );

        return redirect()->route('admin.branding.index')
            ->with('status', 'บันทึกการตั้งค่า Branding และโลโก้เรียบร้อยแล้ว');
    }

    /**
     * Serve the hospital logo cleanly (supports custom uploaded logo or unmodified default Aikchol logo).
     */
    public function logo(): BinaryFileResponse
    {
        $setting = BrandingSetting::current();
        if ($setting->logo_path !== null && Storage::disk('private_branding')->exists($setting->logo_path)) {
            return response()->file(Storage::disk('private_branding')->path($setting->logo_path), [
                'Cache-Control' => 'public, max-age=3600',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $defaultPath = public_path((string) config('branding.default_logo_path', 'images/aikchol-logo.png'));

        return response()->file($defaultPath, [
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
