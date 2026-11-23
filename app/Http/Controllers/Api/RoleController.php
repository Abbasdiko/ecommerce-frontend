<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $this->authorizePermission($request, 'role.view');

        $roles = Role::with('permissions:id,name')->get()->map(fn ($role) => [
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name'),
            'users_count' => $role->users()->count(),
        ]);

        return $this->ok([
            'roles' => $roles,
            'permissions' => Permission::orderBy('name')->pluck('name'),
        ]);
    }
}
