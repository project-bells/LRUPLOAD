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
        return $user->hasPermission('patients.view');
    }

    public function linkRelationship(User $user): bool
    {
        return $user->hasAnyPermission(['documents.create', 'documents.edit_metadata']);
    }
}
