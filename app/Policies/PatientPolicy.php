<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    public function lookup(User $user): bool
    {
        return $user->hasPermission('patients.lookup');
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('patients.view');
    }

    public function view(User $user, Patient $patient): bool
    {
        if (! $user->hasPermission('patients.view')) {
            return false;
        }

        if ($user->hasPermission('audit.view_all') || $user->hasRole('administrator') || $user->department_id === null) {
            return true;
        }

        // Allow newly selected patients (0 documents yet) or patients having at least one document in user's department
        $hasAnyDocuments = $patient->documents()->withTrashed()->exists();
        if (! $hasAnyDocuments) {
            return true;
        }

        return $patient->documents()
            ->withTrashed()
            ->where('department_id', $user->department_id)
            ->exists();
    }

    public function linkRelationship(User $user): bool
    {
        return $user->hasAnyPermission(['documents.create', 'documents.edit_metadata']);
    }
}
