<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;

/**
 * Manage roles and their permissions.
 */
class RoleController extends Controller
{
    /**
     * List all roles.
     */
    public function index()
    {
        $roles = Role::withCount('permissions')->get();

        return view('admin.roles.index', compact('roles'));
    }

    /**
     * Show role creation form.
     */
    public function create()
    {
        $permissions = Permission::all();

        return view('admin.roles.create', compact('permissions'));
    }

    /**
     * Store new role.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        $role->permissions()->sync($validated['permissions'] ?? []);

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Role created successfully');
    }

    /**
     * Edit role permissions.
     */
    public function edit($id)
    {
        $role = Role::findOrFail($id);
        $permissions = Permission::all();

        return view('admin.roles.edit', compact('role', 'permissions'));
    }

    /**
     * Update role permissions.
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $role->permissions()->sync($request->input('permissions', []));

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Permissions updated');
    }

    /**
     * Delete role.
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        // WHY:
        // Admin role is protected to prevent system lockout.
        if ($role->name === 'admin') {
            return back()->with('error', 'Admin role cannot be deleted');
        }

        if ($role->users()->exists()) {
            return back()->with('warning', 'Role has assigned users and cannot be deleted');
        }

        $role->permissions()->detach();
        $role->delete();

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Role deleted successfully');
    }
}
