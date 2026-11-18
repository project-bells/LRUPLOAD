<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Services\DeleteRequestService;

class DocumentPolicy
{
    public function __construct(
        private readonly DeleteRequestService $deleteRequestService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('documents.view');
    }

    public function view(User $user, Document $document): bool
    {
        if (! $user->hasPermission('documents.view')) {
            return false;
        }

        if ($document->trashed() && ! $user->hasAnyPermission(['documents.restore', 'documents.soft_delete', 'audit.view_all'])) {
            return false;
        }

        return $this->belongsToAccessibleDepartment($user, $document);
    }

    public function preview(User $user, Document $document): bool
    {
        if (! $user->hasPermission('documents.preview')) {
            return false;
        }

        if ($document->trashed() && ! $user->hasAnyPermission(['documents.restore', 'documents.soft_delete', 'audit.view_all'])) {
            return false;
        }

        return $this->belongsToAccessibleDepartment($user, $document);
    }

    public function download(User $user, Document $document): bool
    {
        if (! $user->hasPermission('documents.download')) {
            return false;
        }

        if ($document->trashed() && ! $user->hasAnyPermission(['documents.restore', 'audit.view_all'])) {
            return false;
        }

        return $this->belongsToAccessibleDepartment($user, $document);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('documents.create');
    }

    public function updateMetadata(User $user, Document $document): bool
    {
        if ($document->trashed()) {
            return false;
        }

        return $user->hasPermission('documents.edit_metadata')
            && $this->belongsToAccessibleDepartment($user, $document);
    }

    public function createRevision(User $user, Document $document): bool
    {
        if ($document->trashed()) {
            return false;
        }

        return $user->hasPermission('documents.create_revision')
            && $this->belongsToAccessibleDepartment($user, $document);
    }

    public function viewAllRevisions(User $user, Document $document): bool
    {
        return $user->hasPermission('revisions.view_all')
            && $this->belongsToAccessibleDepartment($user, $document);
    }

    public function requestDelete(User $user, Document $document): bool
    {
        if ($document->trashed()) {
            return false;
        }

        return $user->hasPermission('documents.request_delete')
            && $this->belongsToAccessibleDepartment($user, $document);
    }

    public function softDelete(User $user, Document $document): bool
    {
        if ($document->trashed()) {
            return false;
        }

        return $user->hasPermission('documents.soft_delete')
            && $this->belongsToAccessibleDepartment($user, $document);
    }

    public function restore(User $user, Document $document): bool
    {
        return $document->trashed()
            && $user->hasPermission('documents.restore')
            && $this->belongsToAccessibleDepartment($user, $document);
    }

    public function forceDelete(User $user, Document $document): bool
    {
        return $document->trashed()
            && $user->hasPermission('documents.permanent_delete')
            && $this->belongsToAccessibleDepartment($user, $document)
            && $this->deleteRequestService->isPermanentDeleteEnabled();
    }

    /**
     * Prevent cross-department IDOR: users can only access documents within their department
     * unless they hold system-wide audit/admin oversight permissions.
     */
    private function belongsToAccessibleDepartment(User $user, Document $document): bool
    {
        if ($user->hasPermission('audit.view_all') || $user->hasRole('administrator')) {
            return true;
        }

        if ($document->department_id === null || $user->department_id === null) {
            return true;
        }

        return (int) $document->department_id === (int) $user->department_id;
    }
}
