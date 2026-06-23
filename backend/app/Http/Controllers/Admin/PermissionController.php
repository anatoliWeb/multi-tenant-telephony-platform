<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;

/**
 * Handles CRUD operations for admin-managed permissions.
 */
class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::all();

        return view('admin.permissions.index', compact('permissions'));
    }

    public function create()
    {
        return view('admin.permissions.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:permissions,name',
        ]);

        Permission::create([
            'name' => $request->name,
            // WHY:
            // Keep description aligned with the stable permission key
            // until localized RBAC descriptions are introduced.
            'description' => $request->name,
        ]);

        return redirect()
            ->route('admin.permissions.index')
            ->with('success', 'Permission created');
    }

    public function edit($id)
    {
        $permission = Permission::findOrFail($id);

        return view('admin.permissions.edit', compact('permission'));
    }

    public function update(Request $request, $id)
    {
        $permission = Permission::findOrFail($id);

        $request->validate([
            'name' => 'required|unique:permissions,name,' . $permission->id,
        ]);

        $permission->update([
            'name' => $request->name,
            'description' => $request->name,
        ]);

        return redirect()
            ->route('admin.permissions.index')
            ->with('success', 'Permission updated');
    }

    public function destroy($id)
    {
        Permission::findOrFail($id)->delete();

        return back()->with('success', 'Permission deleted');
    }
}
