<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DeleteRequestStatus;
use App\Models\DeleteRequest;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $docQuery = Document::query()->with(['documentType', 'currentRevision.storedFile', 'patients', 'creator']);
        $trashedQuery = Document::onlyTrashed();
        $pendingDeleteQuery = DeleteRequest::query()
            ->where('status', DeleteRequestStatus::Pending->value);

        if (! $user->hasPermission('audit.view_all') && ! $user->hasRole('administrator') && $user->department_id !== null) {
            $deptId = $user->department_id;
            $docQuery->where('department_id', $deptId);
            $trashedQuery->where('department_id', $deptId);
            $pendingDeleteQuery->whereHas('document', static fn ($q) => $q->withTrashed()->where('department_id', $deptId));
        }

        $activeDocuments = (clone $docQuery)->count();
        $trashedDocuments = (clone $trashedQuery)->count();

        $stats = [
            'active_documents' => $activeDocuments,
            'today_documents' => (clone $docQuery)->whereDate('created_at', today())->count(),
            'total_documents' => $activeDocuments + $trashedDocuments,
            'pending_delete_requests' => $pendingDeleteQuery->count(),
            'trashed_documents' => $trashedDocuments,
        ];

        $recentDocuments = (clone $docQuery)->latest()->limit(20)->get();

        return view('dashboard', [
            'stats' => $stats,
            'recentDocuments' => $recentDocuments,
            'hisEnabled' => (bool) config('patient_lookup.enabled', false),
        ]);
    }
}
