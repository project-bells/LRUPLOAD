<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\HnSecurityService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly HnSecurityService $hnSecurity,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        /** @var User $user */
        $user = $request->user();
        $canViewAll = $user->hasPermission('audit.view_all');

        $query = AuditLog::query()
            ->with(['user', 'department'])
            ->latest('created_at');

        if (! $canViewAll && $user->department_id !== null) {
            $query->where('department_id', $user->department_id);
        }

        if ($request->filled('action')) {
            $query->where('action', (string) $request->input('action'));
        }

        if ($request->filled('outcome')) {
            $query->where('outcome', (string) $request->input('outcome'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('hn_hash')) {
            $query->where('hn_hash', trim((string) $request->input('hn_hash')));
        } elseif ($request->filled('hn_search')) {
            // Allow authorized auditor to filter by HN via one-way HMAC hash without storing plaintext HN
            $normalized = $this->hnSecurity->normalize((string) $request->input('hn_search'));
            if ($normalized !== '') {
                $query->where('hn_hash', $this->hnSecurity->hash($normalized));
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', (string) $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', (string) $request->input('date_to'));
        }

        $actions = [
            'login', 'login_failed', 'logout',
            'patient_lookup', 'patient_lookup_not_found', 'patient_lookup_failed',
            'patient_selected', 'patient_snapshot_created', 'patient_snapshot_updated',
            'view_document', 'preview_document', 'download_document',
            'create_document', 'edit_metadata', 'create_revision',
            'request_delete', 'approve_delete', 'reject_delete',
            'soft_delete', 'restore_document', 'permanent_delete',
            'manage_user', 'change_role', 'change_permission',
            'change_setting', 'change_branding',
        ];

        return view('audit-logs.index', [
            'logs' => $query->paginate(25)->withQueryString(),
            'actions' => $actions,
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'username']),
            'canViewAll' => $canViewAll,
        ]);
    }
}
