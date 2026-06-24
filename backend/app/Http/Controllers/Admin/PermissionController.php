<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Handles CRUD operations for admin-managed permissions.
 */
class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::query()->where('scope', 'platform')->get();

        return view('admin.permissions.index', compact('permissions'));
    }

    public function create()
    {
        return view('admin.permissions.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => [
                'required',
                Rule::unique('permissions', 'name')
                    ->where(fn ($query) => $query
                        ->where('scope', 'platform')
                        ->where('scope_reference', 'platform')),
            ],
        ]);

        Permission::create([
            'name' => $request->name,
            'scope' => 'platform',
            'scope_reference' => 'platform',
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
        $permission = Permission::query()->where('scope', 'platform')->findOrFail($id);

        return view('admin.permissions.edit', compact('permission'));
    }

    public function update(Request $request, $id)
    {
        $permission = Permission::query()->where('scope', 'platform')->findOrFail($id);

        $request->validate([
            'name' => [
                'required',
                Rule::unique('permissions', 'name')
                    ->ignore($permission->id)
                    ->where(fn ($query) => $query
                        ->where('scope', 'platform')
                        ->where('scope_reference', 'platform')),
            ],
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
        Permission::query()->where('scope', 'platform')->findOrFail($id)->delete();

        return back()->with('success', 'Permission deleted');
    }
}
