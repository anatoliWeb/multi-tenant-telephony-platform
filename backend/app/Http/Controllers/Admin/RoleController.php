<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        $roles = Role::query()
            ->where('scope', 'platform')
            ->withCount('permissions')
            ->get();

        return view('admin.roles.index', compact('roles'));
    }

    /**
     * Show role creation form.
     */
    public function create()
    {
        $permissions = Permission::query()->where('scope', 'platform')->get();

        return view('admin.roles.create', compact('permissions'));
    }

    /**
     * Store new role.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->where(fn ($query) => $query
                        ->where('scope', 'platform')
                        ->where('scope_reference', 'platform')),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'scope' => 'platform',
            'scope_reference' => 'platform',
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
        $role = Role::query()->where('scope', 'platform')->findOrFail($id);
        $permissions = Permission::query()->where('scope', 'platform')->get();

        return view('admin.roles.edit', compact('role', 'permissions'));
    }

    /**
     * Update role permissions.
     */
    public function update(Request $request, $id)
    {
        $role = Role::query()->where('scope', 'platform')->findOrFail($id);

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
        $role = Role::query()->where('scope', 'platform')->findOrFail($id);

        // WHY:
        // System and protected roles are immutable to prevent lockout
        // or accidental removal of seeded platform access.
        if ($role->is_system || $role->is_protected || $role->name === 'admin') {
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
