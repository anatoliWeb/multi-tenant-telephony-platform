@extends('layouts.app')

@section('title', 'Edit User')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['label' => 'Users', 'url' => route('admin.users.index')],
        ['label' => 'Edit']
    ]" />
@endsection

@section('content')
    @php
        $isSelfEdit = auth()->id() === $user->id;
        $selectedRoleIds = old('roles', $user->roles->pluck('id')->all());
        $selectedPermissionIds = old('permissions', $user->permissions->pluck('id')->all());
        $selectedDeniedPermissionIds = old('denied_permissions', $user->deniedPermissions->pluck('id')->all());
    @endphp

    <header class="page-header">
        <div>
            <h1 class="page-title">Edit User</h1>
            <p class="page-subtitle">Update profile, role bindings, and direct permissions.</p>
        </div>
    </header>

    @if ($errors->any())
        <div class="c-alert c-alert--error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('admin.users.update', $user->id) }}"
        class="c-form"
        data-rbac-form
        data-role-permissions='@json($rolePermissions)'
        data-self-edit="{{ $isSelfEdit ? '1' : '0' }}"
    >
        @csrf
        @method('PUT')

        <section class="c-form__section">
            <h2 class="c-form__title">Basic Info</h2>

            <div class="c-form__grid">
                <div class="c-form__group">
                    <label for="name" class="c-form__label">Name</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" class="c-form__input">
                </div>

                <div class="c-form__group">
                    <label for="email" class="c-form__label">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" class="c-form__input">
                </div>

                <div class="c-form__group">
                    <label for="password" class="c-form__label">Password</label>
                    <input id="password" type="password" name="password" class="c-form__input" placeholder="New password">
                </div>
            </div>
        </section>

        <section class="c-form__section" data-rbac-roles>
            <h2 class="c-form__title">Roles</h2>
            <p class="c-form__hint">Assign global role presets for this user.</p>

            @if($isSelfEdit)
                <p class="c-form__hint c-form__hint--warning">Self-protection enabled: you cannot edit your own roles and permissions.</p>
            @endif

            <div class="c-chip-grid">
                @foreach($roles as $role)
                    <label class="permission-chip permission-chip--role {{ in_array($role->id, $selectedRoleIds) ? 'is-active' : '' }} {{ $isSelfEdit ? 'is-disabled' : '' }}" data-role-chip data-role-id="{{ $role->id }}" data-role-name="{{ $role->name }}">
                        <input
                            type="checkbox"
                            name="roles[]"
                            value="{{ $role->id }}"
                            {{ in_array($role->id, $selectedRoleIds) ? 'checked' : '' }}
                            {{ $isSelfEdit ? 'disabled' : '' }}
                            hidden
                        >
                        <span>{{ $role->name }}</span>
                    </label>
                @endforeach
            </div>
        </section>

        <section class="c-form__section" data-rbac-permissions>
            <h2 class="c-form__title">Permissions</h2>
            <p class="c-form__hint">Fine-grained overrides beyond role inheritance.</p>

            @php
                $permissionsByDomain = $permissions->groupBy(function ($permission) {
                    return explode('.', $permission->name)[0] ?? 'general';
                });
            @endphp

            <div class="permission-groups">
                @foreach($permissionsByDomain as $domain => $domainPermissions)
                    <div class="permission-group">
                        <p class="permission-group__title">{{ ucfirst($domain) }}</p>
                        <div class="permission-group__items">
                            @foreach($domainPermissions as $permission)
                                <label
                                    class="permission-chip permission-chip--permission {{ in_array($permission->id, $selectedPermissionIds) ? 'is-active' : '' }} {{ in_array($permission->id, $selectedDeniedPermissionIds) ? 'is-denied' : '' }} {{ $isSelfEdit ? 'is-disabled' : '' }}"
                                    data-permission-chip
                                    data-permission-id="{{ $permission->id }}"
                                    data-permission-name="{{ $permission->name }}"
                                >
                                    <input
                                        type="checkbox"
                                        name="permissions[]"
                                        value="{{ $permission->id }}"
                                        {{ in_array($permission->id, $selectedPermissionIds) ? 'checked' : '' }}
                                        hidden
                                        data-allow-input
                                    >
                                    <input
                                        type="checkbox"
                                        name="denied_permissions[]"
                                        value="{{ $permission->id }}"
                                        {{ in_array($permission->id, $selectedDeniedPermissionIds) ? 'checked' : '' }}
                                        hidden
                                        data-deny-input
                                    >

                                    <span>{{ $permission->name }}</span>
                                    <small class="via-role" data-via-role hidden>(via role)</small>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="c-form__actions">
            @can('users.delete')
                {{-- WHY:
                     UI elements are permission-controlled to prevent unauthorized actions.
                     Blade uses @can to reflect backend RBAC rules in UI. --}}
                <form method="POST" action="{{ route('admin.users.destroy', $user->id) }}" onsubmit="return confirm('Are you sure?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="c-btn c-btn--danger">Delete User</button>
                </form>
            @endcan
            <button type="submit" class="c-btn c-btn--primary">Save Changes</button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-rbac-form]');
            if (!form) return;

            const isSelfEdit = form.dataset.selfEdit === '1';
            const rolePermissions = JSON.parse(form.dataset.rolePermissions || '{}');

            const roleChips = Array.from(form.querySelectorAll('[data-role-chip]'));
            const permissionChips = Array.from(form.querySelectorAll('[data-permission-chip]'));

            const manuallyAddedPermissions = new Set();
            const manuallyRemovedPermissions = new Set();
            const deniedPermissions = new Set();

            const permissionById = new Map();
            permissionChips.forEach((chip) => {
                const id = Number(chip.dataset.permissionId);
                const name = chip.dataset.permissionName;
                permissionById.set(id, { id, name, chip });
            });

            function getSelectedRoleNames() {
                return roleChips
                    .filter((chip) => chip.querySelector('input[type="checkbox"]').checked)
                    .map((chip) => chip.dataset.roleName);
            }

            function getRoleDerivedPermissions() {
                const selectedRoleNames = getSelectedRoleNames();
                return [...new Set(selectedRoleNames.flatMap((roleName) => rolePermissions[roleName] || []))];
            }

            function refreshUi() {
                const roleDerived = new Set(getRoleDerivedPermissions());
                const finalAllowed = new Set(
                    [...roleDerived]
                        .filter((permissionName) => !manuallyRemovedPermissions.has(permissionName))
                        .concat([...manuallyAddedPermissions])
                        .filter((permissionName) => !deniedPermissions.has(permissionName))
                );

                permissionById.forEach(({ name, chip }) => {
                    const allowInput = chip.querySelector('[data-allow-input]');
                    const denyInput = chip.querySelector('[data-deny-input]');
                    const viaRole = chip.querySelector('[data-via-role]');

                    allowInput.checked = finalAllowed.has(name);
                    denyInput.checked = deniedPermissions.has(name);

                    chip.classList.toggle('is-active', finalAllowed.has(name));
                    chip.classList.toggle('is-role', roleDerived.has(name) && finalAllowed.has(name));
                    chip.classList.toggle('is-manual', manuallyAddedPermissions.has(name) && !roleDerived.has(name) && finalAllowed.has(name));
                    chip.classList.toggle('is-denied', deniedPermissions.has(name));
                    chip.classList.toggle('is-removed', roleDerived.has(name) && manuallyRemovedPermissions.has(name) && !deniedPermissions.has(name));

                    viaRole.hidden = !(roleDerived.has(name) && !deniedPermissions.has(name));
                });
            }

            function initializeFromEditState() {
                const roleDerived = new Set(getRoleDerivedPermissions());

                permissionById.forEach(({ name, chip }) => {
                    const allowInput = chip.querySelector('[data-allow-input]');
                    const denyInput = chip.querySelector('[data-deny-input]');

                    if (allowInput.checked && !roleDerived.has(name)) {
                        manuallyAddedPermissions.add(name);
                    }

                    if (denyInput.checked) {
                        deniedPermissions.add(name);
                        if (roleDerived.has(name)) {
                            manuallyRemovedPermissions.add(name);
                        }
                    }
                });

                refreshUi();
            }

            roleChips.forEach((chip) => {
                chip.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (isSelfEdit) return;

                    const input = chip.querySelector('input[type="checkbox"]');
                    input.checked = !input.checked;
                    chip.classList.toggle('is-active', input.checked);

                    refreshUi();
                });
            });

            permissionChips.forEach((chip) => {
                chip.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (isSelfEdit) return;

                    const name = chip.dataset.permissionName;
                    const roleDerived = new Set(getRoleDerivedPermissions());
                    const isRoleDerived = roleDerived.has(name);
                    const isDenied = deniedPermissions.has(name);
                    const isManual = manuallyAddedPermissions.has(name);

                    // WHY:
                    // Blade version mirrors React RBAC behavior
                    // to keep consistent user experience across admin UI.
                    if (isDenied) {
                        deniedPermissions.delete(name);
                        if (isRoleDerived) {
                            manuallyRemovedPermissions.delete(name);
                        } else {
                            manuallyAddedPermissions.add(name);
                        }
                        refreshUi();
                        return;
                    }

                    if (isRoleDerived) {
                        deniedPermissions.add(name);
                        manuallyRemovedPermissions.add(name);
                        manuallyAddedPermissions.delete(name);
                        refreshUi();
                        return;
                    }

                    if (isManual) {
                        manuallyAddedPermissions.delete(name);
                    } else {
                        manuallyAddedPermissions.add(name);
                    }

                    refreshUi();
                });
            });

            initializeFromEditState();
        });
    </script>
@endpush
