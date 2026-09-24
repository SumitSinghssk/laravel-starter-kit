<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommonStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserStoreRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    private const PROTECTED_ROLES = ['super admin', 'super-admin'];

    public function index(Request $request)
    {
        Gate::authorize('admin.users.view');

        $query = User::query();

        if ($request->filled('search')) {
            $term = '%'.addcslashes($request->search, '%_\\').'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('role')) {
            $query->role($request->role);
        }

        $users = $query->latest()
            ->paginate(20)
            ->withQueryString();

        $roles = Role::all();

        return view('admin.users.index', compact('users', 'roles'));
    }

    public function create()
    {
        Gate::authorize('admin.users.create');

        $roles = Role::with('permissions')->orderBy('name')->get();
        $permissions = Permission::orderBy('name')->get();

        $groupedPermissions = $permissions->groupBy(function ($permission) {
            $parts = explode('.', $permission->name);
            if ($parts[0] === 'admin' && isset($parts[1])) {
                return ucfirst(str_replace('-', ' ', $parts[1]));
            }

            return ucfirst(str_replace('-', ' ', $parts[0]));
        })->sortKeys();

        $rolesWithPermissions = $roles->map(fn ($r) => [
            'name' => $r->name,
            'permissions' => $r->permissions->pluck('name')->values()->toArray(),
            'count' => $r->permissions->count(),
        ])->values()->toArray();

        return view('admin.users.create', compact(
            'roles', 'groupedPermissions', 'rolesWithPermissions'
        ));
    }

    public function store(UserStoreRequest $request)
    {
        Gate::authorize('admin.users.create');

        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'status' => $request->status,
            'bio' => $request->bio,
            'avatar' => $avatarPath,
        ]);

        $user->syncRoles($this->resolveRoles((array) $request->roles));
        $user->syncPermissions($this->resolvePermissions((array) $request->permissions));

        if (Gate::allows('admin.users.edit')) {
            return to_route('admin.users.edit', $user)->with('success', 'User Created');
        }

        return to_route('admin.users.index')->with('success', 'User Created');
    }

    public function edit(User $user)
    {
        Gate::authorize('admin.users.edit');

        $roles = Role::with('permissions')->orderBy('name')->get();
        $permissions = Permission::orderBy('name')->get();

        $groupedPermissions = $permissions->groupBy(function ($permission) {
            $parts = explode('.', $permission->name);
            if ($parts[0] === 'admin' && isset($parts[1])) {
                return ucfirst(str_replace('-', ' ', $parts[1]));
            }

            return ucfirst(str_replace('-', ' ', $parts[0]));
        })->sortKeys();

        $rolesWithPermissions = $roles->map(fn ($r) => [
            'name' => $r->name,
            'permissions' => $r->permissions->pluck('name')->values()->toArray(),
            'count' => $r->permissions->count(),
        ])->values()->toArray();

        $userRoles = $user->roles->pluck('name')->values()->toArray();
        $userPermissions = $user->getDirectPermissions()->pluck('name')->values()->toArray();

        return view('admin.users.edit', compact(
            'user', 'roles', 'groupedPermissions',
            'rolesWithPermissions', 'userRoles', 'userPermissions'
        ));
    }

    public function update(UserUpdateRequest $request, User $user)
    {
        Gate::authorize('admin.users.edit');

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'status' => $request->status,
            'bio' => $request->bio,
        ];

        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        } elseif ($request->boolean('remove_avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = null;
        }

        $user->update($data);
        $user->syncRoles($this->resolveRoles((array) ($request->roles ?? []), $user));
        $user->syncPermissions($this->resolvePermissions((array) ($request->permissions ?? []), $user));

        return back()->with('success', "User \"{$user->name}\" updated successfully.");
    }

    public function destroy(User $user)
    {
        Gate::authorize('admin.users.delete');

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return to_route('admin.users.index')->with('success', "User \"{$user->name}\" deleted successfully.");
    }

    public function toggleStatus(User $user)
    {
        Gate::authorize('admin.users.toogle-status');

        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'You cannot change your own account status.',
            ], 422);
        }

        if ($user->hasRole(self::PROTECTED_ROLES) && ! auth()->user()->hasRole(self::PROTECTED_ROLES)) {
            return response()->json([
                'message' => 'You are not allowed to change this account\'s status.',
            ], 403);
        }

        $user->status = $user->status === CommonStatusEnum::ACTIVE ? CommonStatusEnum::INACTIVE : CommonStatusEnum::ACTIVE;
        $user->save();

        return response()->json([
            'status' => $user->status,
            'message' => 'Status updated successfully',
        ]);
    }

    private function resolveRoles(array $requested, ?User $target = null): array
    {
        if (auth()->user()->hasRole(self::PROTECTED_ROLES)) {
            return $requested;
        }

        $requested = array_values(array_diff($requested, self::PROTECTED_ROLES));

        if ($target) {
            $existingProtected = $target->roles->pluck('name')
                ->intersect(self::PROTECTED_ROLES)->all();
            $requested = array_values(array_unique(array_merge($requested, $existingProtected)));
        }

        return $requested;
    }

    private function resolvePermissions(array $requested, ?User $target = null): array
    {
        $actor = auth()->user();

        if ($actor->hasRole(self::PROTECTED_ROLES)) {
            return $requested;
        }

        $allowed = $actor->getAllPermissions()->pluck('name')->all();
        $requested = array_values(array_intersect($requested, $allowed));

        if ($target) {
            $existingBeyondActor = $target->getDirectPermissions()->pluck('name')
                ->diff($allowed)->all();
            $requested = array_values(array_unique(array_merge($requested, $existingBeyondActor)));
        }

        return $requested;
    }
}
