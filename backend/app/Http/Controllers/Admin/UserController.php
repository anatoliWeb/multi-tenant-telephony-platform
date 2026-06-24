<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin users management controller.
 *
 * Responsible for displaying users list in admin panel.
 */
class UserController extends Controller
{
    /**
     * User service instance.
     */
    protected UserService $userService;

    /**
     * Inject dependencies.
     *
     * @param UserService $userService
     */
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Display users list.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        try {
            $users = $this->userService->getUsersForAdmin();

            return view('admin.users.index', [
                'users' => $users
            ]);

        } catch (\Throwable $e) {

            Log::error('Failed to load users for admin', [
                'error' => $e->getMessage(),
            ]);

            abort(500);
        }
    }

    /**
     * Show user edit form.
     *
     * Loads user roles and direct permissions
     * for editing access control settings.
     */
    public function edit($id)
    {
        $user = User::with(['roles', 'permissions', 'deniedPermissions'])->findOrFail($id);

        $roles = Role::query()->where('scope', 'platform')->get();
        $permissions = Permission::query()->where('scope', 'platform')->get();
        $rolePermissions = Role::query()->where('scope', 'platform')->with('permissions:id,name')
            ->get()
            ->mapWithKeys(function (Role $role) {
                return [
                    $role->name => $role->permissions->pluck('name')->values()->all(),
                ];
            })
            ->all();

        return view('admin.users.edit', compact(
            'user',
            'roles',
            'permissions',
            'rolePermissions'
        ));
    }

    /**
     * Show create user form.
     */
    public function create()
    {
        $roles = Role::query()->where('scope', 'platform')->get();
        $permissions = Permission::query()->where('scope', 'platform')->get();

        return view('admin.users.create', compact(
            'roles',
            'permissions'
        ));
    }

    /**
     * Store newly created user.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'roles' => ['array'],
            'roles.*' => [
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('scope', 'platform')),
            ],
            'permissions' => ['array'],
            'permissions.*' => [
                Rule::exists('permissions', 'id')->where(fn ($query) => $query->where('scope', 'platform')),
            ],
            'denied_permissions' => ['array'],
            'denied_permissions.*' => [
                Rule::exists('permissions', 'id')->where(fn ($query) => $query->where('scope', 'platform')),
            ],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->roles()->sync($validated['roles'] ?? []);
        $user->permissions()->sync(
            Permission::query()->where('scope', 'platform')->whereIn('id', $validated['permissions'] ?? [])->pluck('id')
        );
        $user->deniedPermissions()->sync(
            Permission::query()->where('scope', 'platform')->whereIn('id', $validated['denied_permissions'] ?? [])->pluck('id')
        );

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User created successfully');
    }

    /**
     * Update user data, roles and permissions.
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        /**
         * Validate input
         */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'string', 'min:6'],
            'roles' => ['array'],
            'roles.*' => [
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('scope', 'platform')),
            ],
            'permissions' => ['array'],
            'permissions.*' => [
                Rule::exists('permissions', 'id')->where(fn ($query) => $query->where('scope', 'platform')),
            ],
            'denied_permissions' => ['array'],
            'denied_permissions.*' => [
                Rule::exists('permissions', 'id')->where(fn ($query) => $query->where('scope', 'platform')),
            ],
        ]);

        /**
         * Update basic info
         */
        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        // WHY:
        // Password is optional on update to avoid accidental overwrite.
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
            $user->save();
        }

        $isSelfUpdate = auth()->id() === $user->id;

        if (!$isSelfUpdate) {
            /**
             * Sync roles (replace existing)
             */
            $user->roles()->sync($validated['roles'] ?? []);

            /**
             * Sync direct permissions
             */
            $user->permissions()->sync(Permission::query()->where('scope', 'platform')->whereIn('id', $validated['permissions'] ?? [])->pluck('id'));

            /**
             * Sync denied permissions
             */
            $user->deniedPermissions()->sync(Permission::query()->where('scope', 'platform')->whereIn('id', $validated['denied_permissions'] ?? [])->pluck('id'));
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User updated successfully');
    }

    /**
     * Delete user from admin panel.
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if (auth()->id() === $user->id) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'You cannot delete your own account from this screen.');
        }

        $user->roles()->detach();
        $user->permissions()->detach();
        $user->deniedPermissions()->detach();
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User deleted successfully');
    }
}
