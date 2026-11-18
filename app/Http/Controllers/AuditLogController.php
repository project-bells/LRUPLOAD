<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\HnSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly HnSecurityService $hnSecurity,
        private readonly AuditLogService $auditLog,
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

        // Only accept one-way hn_hash in GET query string; never accept plaintext HN in URL (SEC-M-04)
        $hnHashFilter = null;
        if ($request->filled('hn_hash')) {
            $candidateHash = trim((string) $request->input('hn_hash'));
            if (preg_match('/^[a-f0-9]{64}$/i', $candidateHash) === 1) {
                $hnHashFilter = strtolower($candidateHash);
                $query->where('hn_hash', $hnHashFilter);
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', (string) $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', (string) $request->input('date_to'));
        }

        $logs = $query->paginate(25)->withQueryString();

        // Audit viewing of the Audit Log itself (Section 15)
        $this->auditLog->record(
            action: 'view_audit_log',
            user: $user,
            outcome: 'success',
            hnHash: $hnHashFilter,
            afterData: [
                'scope' => $canViewAll ? 'all' : 'department',
                'filter_action' => $request->input('action') ?: null,
            ],
            request: $request,
        );

        $actions = [
            'login', 'login_failed', 'logout',
            'patient_lookup', 'patient_lookup_not_found', 'patient_lookup_failed',
            'patient_selected', 'patient_snapshot_created', 'patient_snapshot_updated', 'view_patient',
            'view_document', 'preview_document', 'download_document',
            'create_document', 'edit_metadata', 'create_revision',
            'request_delete', 'approve_delete', 'reject_delete',
            'soft_delete', 'restore_document', 'permanent_delete',
            'view_audit_log',
            'manage_user', 'change_role', 'change_permission',
            'change_setting', 'change_branding',
        ];

        return view('audit-logs.index', [
            'logs' => $logs,
            'actions' => $actions,
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'username']),
            'canViewAll' => $canViewAll,
        ]);
    }

    /**
     * POST /audit-logs/search-hn — Converts plaintext HN to one-way HMAC hash via POST+CSRF
     * so plaintext HN NEVER appears in URL query strings, browser history, or web server access logs.
     */
    public function searchByHn(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $validated = $request->validate([
            'hn' => ['required', 'string', 'max:30'],
        ]);

        $normalized = $this->hnSecurity->normalize((string) $validated['hn']);
        if (! $this->hnSecurity->isValid($normalized)) {
            return redirect()->route('audit-logs.index')->withErrors(['hn' => 'รูปแบบ HN ไม่ถูกต้อง']);
        }

        return redirect()->route('audit-logs.index', [
            'hn_hash' => $this->hnSecurity->hash($normalized),
        ]);
    }
}
