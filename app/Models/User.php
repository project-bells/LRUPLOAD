<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /** @var array<int, string>|null */
    private ?array $cachedPermissionNames = null;

    protected $fillable = [
        'department_id',
        'employee_code',
        'username',
        'name',
        'email',
        'password',
        'is_active',
        'last_login_at',
        'password_changed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'created_by');
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles->contains('name', $roleName);
    }

    /**
     * @return array<int, string>
     */
    public function permissionNames(): array
    {
        if ($this->cachedPermissionNames !== null) {
            return $this->cachedPermissionNames;
        }

        $this->loadMissing('roles.permissions');

        $names = [];
        foreach ($this->roles as $role) {
            foreach ($role->permissions as $permission) {
                $names[$permission->name] = true;
            }
        }

        $this->cachedPermissionNames = array_keys($names);

        return $this->cachedPermissionNames;
    }

    public function hasPermission(string $permissionName): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return in_array($permissionName, $this->permissionNames(), true);
    }

    /**
     * @param array<int, string> $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function clearPermissionCache(): void
    {
        $this->cachedPermissionNames = null;
        $this->unsetRelation('roles');
    }

    public function primaryRoleLabel(): string
    {
        $this->loadMissing('roles');
        $first = $this->roles->first();

        return $first ? $first->display_name_th : 'ไม่ระบุบทบาท';
    }
}
