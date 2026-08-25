<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;

class ListUserIdsWithPermissionAction
{
    /**
     * @return list<int>
     */
    public function execute(string $permission): array
    {
        $permissionId = DB::table('permissions')->where('name', $permission)->value('id');
        if ($permissionId === null) {
            return [];
        }

        $direct = DB::table('model_has_permissions')
            ->where('permission_id', $permissionId)
            ->pluck('model_id');

        $viaRoles = DB::table('model_has_roles')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
            ->where('role_has_permissions.permission_id', $permissionId)
            ->pluck('model_has_roles.model_id');

        return $direct->merge($viaRoles)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter(fn (int $id) => User::query()->whereKey($id)->exists())
            ->values()
            ->all();
    }
}
