<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DeleteRequest;
use App\Models\User;

class DeleteRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('delete_requests.review');
    }

    public function review(User $user, DeleteRequest $deleteRequest): bool
    {
        if (! $user->hasPermission('delete_requests.review')) {
            return false;
        }

        if ($user->hasPermission('audit.view_all') || $user->hasRole('administrator')) {
            return true;
        }

        $doc = $deleteRequest->document;
        if ($doc === null || $doc->department_id === null || $user->department_id === null) {
            return true;
        }

        return (int) $doc->department_id === (int) $user->department_id;
    }
}
