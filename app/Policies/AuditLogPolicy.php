<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['audit.view_department', 'audit.view_all']);
    }

    public function viewAll(User $user): bool
    {
        return $user->hasPermission('audit.view_all');
    }
}
