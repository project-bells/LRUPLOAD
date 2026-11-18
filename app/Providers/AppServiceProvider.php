<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\FileScannerInterface;
use App\Contracts\PatientLookupInterface;
use App\Integrations\NullFileScanner;
use App\Integrations\SqlServer2014PatientLookup;
use App\Models\AuditLog;
use App\Models\BrandingSetting;
use App\Models\DeleteRequest;
use App\Models\Document;
use App\Models\Patient;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\DeleteRequestPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\PatientPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PatientLookupInterface::class, SqlServer2014PatientLookup::class);
        $this->app->bind(FileScannerInterface::class, NullFileScanner::class);
    }

    public function boot(): void
    {
        Gate::policy(Patient::class, PatientPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(DeleteRequest::class, DeleteRequestPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);

        // Register Gate abilities matching every RBAC permission name
        $permissions = [
            'patients.lookup',
            'patients.view',
            'documents.view',
            'documents.preview',
            'documents.download',
            'documents.create',
            'documents.edit_metadata',
            'documents.create_revision',
            'documents.request_delete',
            'documents.soft_delete',
            'documents.restore',
            'documents.permanent_delete',
            'revisions.view_all',
            'delete_requests.review',
            'audit.view_department',
            'audit.view_all',
            'users.manage',
            'roles.manage',
            'document_types.manage',
            'branding.manage',
            'settings.manage',
        ];

        foreach ($permissions as $permission) {
            Gate::define($permission, static fn(User $user): bool => $user->hasPermission($permission));
        }

        // Login brute-force protection
        RateLimiter::for('login', static function (Request $request): array {
            $loginKey = strtolower((string) $request->input('login', 'guest'));

            return [
                Limit::perMinute(5)->by($loginKey . '|' . $request->ip()),
                Limit::perMinute(20)->by('ip|' . $request->ip()),
            ];
        });

        // HN Lookup rate limiting per user and per IP (Section 8)
        RateLimiter::for('patient-lookup', static function (Request $request): array {
            $perUser = (int) config('patient_lookup.rate_limit_per_user_per_minute', 30);
            $perIp = (int) config('patient_lookup.rate_limit_per_ip_per_minute', 60);
            $userId = $request->user()?->id ?? 'anon';

            return [
                Limit::perMinute($perUser)->by('lookup-user:' . $userId),
                Limit::perMinute($perIp)->by('lookup-ip:' . $request->ip()),
            ];
        });

        // Document & revision upload rate limiting to prevent disk exhaustion / flood
        RateLimiter::for('document-upload', static function (Request $request): array {
            $userId = $request->user()?->id ?? 'anon';

            return [
                Limit::perMinute(20)->by('upload-user:' . $userId),
                Limit::perMinute(40)->by('upload-ip:' . $request->ip()),
            ];
        });

        // Share Aikchol Hospital branding across all Blade views
        View::composer('*', static function ($view): void {
            static $cachedBranding = null;
            if ($cachedBranding === null) {
                $cachedBranding = BrandingSetting::current();
            }
            $view->with('branding', $cachedBranding);
        });
    }
}
