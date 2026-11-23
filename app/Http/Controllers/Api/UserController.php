<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $this->authorizePermission($request, 'user.view');

        $users = User::query()
            ->with('agent')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('first_name', 'like', "%$s%")
                ->orWhere('last_name', 'like', "%$s%")
                ->orWhere('email', 'like', "%$s%")))
            ->when($request->role, fn ($q, $role) => $q->role($role))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($this->perPage($request));

        return UserResource::collection($users);
    }

    public function show(Request $request, User $user)
    {
        $this->authorizePermission($request, 'user.view');

        return $this->ok(new UserResource($user->load('agent')));
    }

    public function store(Request $request)
    {
        $this->authorizePermission($request, 'user.create');

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(Role::pluck('name'))],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'suspended', 'pending'])],
        ]);

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'status' => $data['status'] ?? 'active',
        ]);

        $user->syncRoles($data['roles']);
        AuditLogger::record('user.created', $user, [], $user->only(['email', 'status']));

        return $this->created(new UserResource($user), 'User created');
    }

    public function update(Request $request, User $user)
    {
        $this->authorizePermission($request, 'user.update');

        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(Role::pluck('name'))],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'suspended', 'pending'])],
        ]);

        $old = $user->only(['first_name', 'last_name', 'email', 'status']);

        $user->fill(collect($data)->except(['password', 'roles'])->toArray());
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->name = trim($user->first_name . ' ' . $user->last_name);
        $user->save();

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        AuditLogger::record('user.updated', $user, $old, $user->only(['first_name', 'last_name', 'email', 'status']));

        return $this->ok(new UserResource($user->fresh('agent')), 'User updated');
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorizePermission($request, 'user.delete');

        if ($user->id === $request->user()->id) {
            return $this->fail('You cannot delete your own account.', null, 409);
        }

        $user->delete();
        AuditLogger::record('user.deleted', $user);

        return $this->ok(null, 'User deleted');
    }
}
