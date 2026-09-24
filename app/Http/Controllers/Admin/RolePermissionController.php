<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionController extends Controller
{
    private const PROTECTED_ROLES = ['super admin', 'super-admin'];

    public function index()
    {
        Gate::authorize('admin.roles.view');

        $roles = Role::with('permissions')->orderBy('name')->get();
        $permissions = Permission::withCount('roles')->orderBy('name')->get();

        $groupedPermissions = $permissions->groupBy(function ($permission) {
            $parts = explode('.', $permission->name);
            if ($parts[0] === 'admin' && isset($parts[1])) {
                return ucfirst(str_replace('-', ' ', $parts[1]));
            }

            return ucfirst(str_replace('-', ' ', $parts[0]));
        })->sortKeys();

        return view('admin.roles.index', compact('roles', 'permissions', 'groupedPermissions'));
    }

    public function storeRole(Request $request)
    {
        Gate::authorize('admin.roles.create');

        $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name'],
        ]);

        $role = Role::create(['name' => $request->name, 'guard_name' => 'web']);

        return back()->with('success', "Role \"{$role->name}\" created successfully.");
    }

    public function toggleTwoFactor(Request $request, Role $role)
    {
        Gate::authorize('admin.roles.update');

        $request->validate(['required' => ['required', 'boolean']]);

        $role->forceFill(['requires_two_factor' => $request->boolean('required')])->save();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $missing = $role->users()->whereNull('two_factor_confirmed_at')->count();

        return back()->with('success', $role->requires_two_factor
            ? "Two-factor sign-in is now required for \"{$role->name}\".".($missing ? " {$missing} ".Str::plural('user', $missing).' will be asked to set it up.' : '')
            : "Two-factor sign-in is now optional for \"{$role->name}\".");
    }

    public function destroyRole(Role $role)
    {
        Gate::authorize('admin.roles.delete');

        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            return back()->with('error', 'The super admin role cannot be deleted.');
        }

        $name = $role->name;
        $role->delete();

        return back()->with('success', "Role \"{$name}\" deleted successfully.");
    }

    public function storePermission(Request $request)
    {
        Gate::authorize('admin.permissions.create');

        $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:permissions,name'],
        ]);

        $permission = Permission::create(['name' => $request->name, 'guard_name' => 'web']);

        return back()->with('success', "Permission \"{$permission->name}\" created successfully.");
    }

    public function destroyPermission(Permission $permission)
    {
        Gate::authorize('admin.permissions.delete');

        if ($permission->roles()->exists() || $permission->users()->exists()) {
            return back()->with('error', "Permission \"{$permission->name}\" is still assigned to one or more roles or users and cannot be deleted.");
        }

        $name = $permission->name;
        $permission->delete();

        return back()->with('success', "Permission \"{$name}\" deleted successfully.");
    }

    public function togglePermission(Request $request, Role $role)
    {
        Gate::authorize('admin.roles.update');

        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'The super admin role permissions cannot be changed.',
            ], 403);
        }

        $request->validate([
            'permission' => ['required', 'string', 'exists:permissions,name'],
        ]);

        $permissionName = $request->permission;

        if ($role->hasPermissionTo($permissionName)) {
            $role->revokePermissionTo($permissionName);
            $action = 'revoked';
        } else {
            $role->givePermissionTo($permissionName);
            $action = 'granted';
        }

        return response()->json([
            'success' => true,
            'action' => $action,
            'role' => $role->name,
            'permission' => $permissionName,
        ]);
    }
}
