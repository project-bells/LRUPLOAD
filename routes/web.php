<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\BrandingController;
use App\Http\Controllers\Admin\DocumentTypeController;
use App\Http\Controllers\Admin\RoleManagementController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeleteRequestController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentFileController;
use App\Http\Controllers\DocumentRevisionController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientLookupController;
use Illuminate\Support\Facades\Route;

// Public logo endpoint (used on Login screen and print headers)
Route::get('/branding/logo', [BrandingController::class, 'logo'])->name('branding.logo');

// Guest authentication routes
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login.submit');
});

// Authenticated routes (strictly active users + server-side authorization + no-cache on sensitive patient pages)
Route::middleware(['auth', 'active'])->group(function (): void {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', static fn () => redirect()->route('dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('nocache')
        ->name('dashboard');

    // Patient HN Lookup (POST /patients/lookup with CSRF + rate limit; never leaks HN into browser history)
    Route::middleware('nocache')->group(function (): void {
        Route::get('/patients/lookup', [PatientLookupController::class, 'index'])
            ->middleware('permission:patients.lookup')
            ->name('patients.lookup.index');
        Route::post('/patients/lookup', [PatientLookupController::class, 'lookup'])
            ->middleware(['permission:patients.lookup', 'throttle:patient-lookup'])
            ->name('patients.lookup');
        Route::post('/patients/select', [PatientLookupController::class, 'select'])
            ->middleware('permission:patients.lookup')
            ->name('patients.select');

        // Patients & Mother-Child Relationships
        Route::get('/patients', [PatientController::class, 'index'])
            ->middleware('permission:patients.view')
            ->name('patients.index');
        Route::post('/patients/search-local', [PatientController::class, 'searchLocal'])
            ->middleware('permission:patients.view')
            ->name('patients.search-local');
        Route::get('/patients/{patient}', [PatientController::class, 'show'])
            ->middleware('permission:patients.view')
            ->name('patients.show');
        Route::delete('/patients/{patient}', [PatientController::class, 'destroy'])
            ->middleware('permission:patients.delete|documents.create|users.manage|delete_requests.review')
            ->name('patients.destroy');
        Route::get('/patients/{patient}/relationships', [PatientController::class, 'relationships'])
            ->middleware('permission:patients.view')
            ->name('patients.relationships');
        Route::post('/patients/{patient}/relationships', [PatientController::class, 'storeRelationship'])
            ->middleware('permission:documents.create|documents.edit_metadata')
            ->name('patients.relationships.store');

        // Documents & Upload / Review Before Save
        Route::get('/documents', [DocumentController::class, 'index'])
            ->middleware('permission:documents.view')
            ->name('documents.index');
        Route::get('/patients/{patient}/documents/create', [DocumentController::class, 'create'])
            ->middleware('permission:documents.create')
            ->name('documents.create');
        Route::post('/documents/stage', [DocumentController::class, 'stage'])
            ->middleware(['permission:documents.create', 'throttle:document-upload'])
            ->name('documents.stage');
        Route::get('/documents/review/{token}', [DocumentController::class, 'review'])
            ->middleware('permission:documents.create')
            ->name('documents.review');
        Route::get('/documents/staged/{token}/stream', [DocumentController::class, 'streamStaged'])
            ->middleware('permission:documents.create|documents.create_revision')
            ->name('documents.staged.stream');
        Route::post('/documents/confirm', [DocumentController::class, 'confirm'])
            ->middleware('permission:documents.create')
            ->name('documents.confirm');

        // Trash & Delete Requests (registered before {document} wildcard routes)
        Route::get('/documents/trash', [DeleteRequestController::class, 'trash'])
            ->middleware('permission:documents.restore|documents.soft_delete|documents.permanent_delete')
            ->name('documents.trash');
        Route::post('/documents/trash/{documentId}/restore', [DeleteRequestController::class, 'restore'])
            ->middleware('permission:documents.restore')
            ->name('documents.restore');
        Route::delete('/documents/trash/{documentId}/permanent', [DeleteRequestController::class, 'permanentDelete'])
            ->middleware('permission:documents.permanent_delete')
            ->name('documents.permanent-delete');

        Route::get('/delete-requests', [DeleteRequestController::class, 'index'])
            ->middleware('permission:delete_requests.review')
            ->name('delete-requests.index');
        Route::post('/delete-requests/{deleteRequest}/review', [DeleteRequestController::class, 'review'])
            ->middleware('permission:delete_requests.review')
            ->name('delete-requests.review');

        // Individual Document Actions
        Route::get('/documents/{document}', [DocumentController::class, 'show'])
            ->middleware('permission:documents.view')
            ->name('documents.show');
        Route::get('/documents/{document}/edit-metadata', [DocumentController::class, 'editMetadata'])
            ->middleware('permission:documents.edit_metadata')
            ->name('documents.edit-metadata');
        Route::put('/documents/{document}/metadata', [DocumentController::class, 'updateMetadata'])
            ->middleware('permission:documents.edit_metadata')
            ->name('documents.update-metadata');

        // Revisions
        Route::get('/documents/{document}/revisions', [DocumentRevisionController::class, 'index'])
            ->middleware('permission:documents.view')
            ->name('documents.revisions.index');
        Route::get('/documents/{document}/revisions/create', [DocumentRevisionController::class, 'create'])
            ->middleware('permission:documents.create_revision')
            ->name('documents.revisions.create');
        Route::post('/documents/{document}/revisions/stage', [DocumentRevisionController::class, 'stage'])
            ->middleware(['permission:documents.create_revision', 'throttle:document-upload'])
            ->name('documents.revisions.stage');
        Route::get('/documents/{document}/revisions/review/{token}', [DocumentRevisionController::class, 'review'])
            ->middleware('permission:documents.create_revision')
            ->name('documents.revisions.review');
        Route::post('/documents/{document}/revisions/confirm', [DocumentRevisionController::class, 'confirm'])
            ->middleware('permission:documents.create_revision')
            ->name('documents.revisions.confirm');

        // Authorized PDF.js Preview & Download
        Route::get('/documents/{document}/revisions/{revision}/preview', [DocumentFileController::class, 'preview'])
            ->middleware('permission:documents.preview')
            ->name('documents.revisions.preview');
        Route::get('/documents/{document}/revisions/{revision}/stream', [DocumentFileController::class, 'stream'])
            ->middleware('permission:documents.preview')
            ->name('documents.revisions.stream');
        Route::get('/documents/{document}/revisions/{revision}/download', [DocumentFileController::class, 'download'])
            ->middleware('permission:documents.download')
            ->name('documents.revisions.download');

        // Delete Request Submission & Direct Soft Delete
        Route::get('/documents/{document}/delete-request', [DeleteRequestController::class, 'create'])
            ->middleware('permission:documents.request_delete')
            ->name('documents.delete-request.create');
        Route::post('/documents/{document}/delete-request', [DeleteRequestController::class, 'store'])
            ->middleware('permission:documents.request_delete')
            ->name('documents.delete-request.store');
        Route::post('/documents/{document}/soft-delete', [DeleteRequestController::class, 'directSoftDelete'])
            ->middleware('permission:documents.soft_delete')
            ->name('documents.soft-delete');
    });

    // Audit Log (Read-Only; HN search uses POST to avoid exposing plaintext HN in GET URLs)
    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware(['nocache', 'permission:audit.view_department|audit.view_all'])
        ->name('audit-logs.index');
    Route::post('/audit-logs/search-hn', [AuditLogController::class, 'searchByHn'])
        ->middleware(['nocache', 'permission:audit.view_department|audit.view_all', 'throttle:patient-lookup'])
        ->name('audit-logs.search-hn');

    // Admin Management Module
    Route::prefix('admin')->name('admin.')->middleware('nocache')->group(function (): void {
        Route::get('/users', [UserManagementController::class, 'index'])
            ->middleware('permission:users.manage')
            ->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])
            ->middleware('permission:users.manage')
            ->name('users.store');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])
            ->middleware('permission:users.manage')
            ->name('users.update');

        Route::get('/roles', [RoleManagementController::class, 'index'])
            ->middleware('permission:roles.manage')
            ->name('roles.index');
        Route::put('/roles/{role}/permissions', [RoleManagementController::class, 'updatePermissions'])
            ->middleware('permission:roles.manage')
            ->name('roles.permissions.update');

        Route::get('/document-types', [DocumentTypeController::class, 'index'])
            ->middleware('permission:document_types.manage')
            ->name('document-types.index');
        Route::post('/document-types', [DocumentTypeController::class, 'store'])
            ->middleware('permission:document_types.manage')
            ->name('document-types.store');
        Route::put('/document-types/{documentType}', [DocumentTypeController::class, 'update'])
            ->middleware('permission:document_types.manage')
            ->name('document-types.update');

        Route::get('/settings', [SystemSettingController::class, 'index'])
            ->middleware('permission:settings.manage')
            ->name('settings.index');
        Route::put('/settings', [SystemSettingController::class, 'update'])
            ->middleware('permission:settings.manage')
            ->name('settings.update');

        Route::get('/branding', [BrandingController::class, 'index'])
            ->middleware('permission:branding.manage')
            ->name('branding.index');
        Route::put('/branding', [BrandingController::class, 'update'])
            ->middleware('permission:branding.manage')
            ->name('branding.update');
    });
});
