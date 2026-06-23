<template>
  <BaseForm :model="form.model" @submit="handleSubmit">
    <BaseFormSection title="Edit user" description="Update identity, role assignments, and direct RBAC overrides." layout="grid">
      <BaseFormField label="Name" :required="true" :error="form.getFieldError('name')">
        <input :value="String(form.model.name)" @input="form.setField('name', ($event.target as HTMLInputElement).value)" />
      </BaseFormField>

      <BaseFormField label="Email" :required="true" :error="form.getFieldError('email')">
        <input :value="String(form.model.email)" @input="form.setField('email', ($event.target as HTMLInputElement).value)" />
      </BaseFormField>

      <BaseFormField label="Password (optional)" :error="form.getFieldError('password')">
        <input
          type="password"
          autocomplete="new-password"
          :value="String(form.model.password ?? '')"
          @input="form.setField('password', ($event.target as HTMLInputElement).value)"
        />
      </BaseFormField>
    </BaseFormSection>

    <BaseFormSection title="Role assignment" description="Assign roles that provide inherited permission sets.">
      <div class="rbac-control">
        <input
          type="text"
          placeholder="Search roles..."
          :value="roleQuery"
          @input="roleQuery = ($event.target as HTMLInputElement).value"
        />
      </div>

      <div class="rbac-grid">
        <button
          v-for="role in filteredRoles"
          :key="role.id"
          type="button"
          class="role-chip"
          :class="{ 'is-active': selectedRoleIds.has(role.id) }"
          :aria-pressed="selectedRoleIds.has(role.id)"
          @click="toggleRole(role.id)"
        >
          <span class="role-chip__label">{{ role.label }}</span>
        </button>
      </div>
    </BaseFormSection>

    <BaseFormSection title="Direct permissions" description="Direct grants and denies override role inheritance when needed.">
      <div class="rbac-control">
        <input
          type="text"
          placeholder="Search permissions..."
          :value="permissionQuery"
          @input="permissionQuery = ($event.target as HTMLInputElement).value"
        />
      </div>

      <div class="permission-groups">
        <section v-for="group in groupedPermissions" :key="group.module" class="permission-group">
          <h4>{{ group.module }}</h4>
          <div class="rbac-grid">
            <div v-for="permission in group.permissions" :key="permission.name" class="perm-toggle">
              <div class="perm-toggle__meta">
                <span class="perm-toggle__label">{{ permission.label }}</span>
              </div>
              <div class="perm-toggle__status">
                <span class="status-chip" :class="permissionResolution(permission.name).inherited ? 'is-ok' : 'is-muted'">
                  Inherited: {{ permissionResolution(permission.name).inherited ? 'Allow' : 'None' }}
                </span>
                <span class="status-chip" :class="permissionResolution(permission.name).overrideType === 'deny' ? 'is-deny' : permissionResolution(permission.name).overrideType === 'allow' ? 'is-allow' : 'is-muted'">
                  User override: {{ permissionResolution(permission.name).overrideLabel }}
                </span>
                <span class="status-chip" :class="permissionResolution(permission.name).finalAllowed ? 'is-ok' : 'is-deny'">
                  Final: {{ permissionResolution(permission.name).finalAllowed ? 'Allowed' : 'Denied' }}
                </span>
              </div>
              <div class="perm-toggle__actions" role="group" :aria-label="`Permission state for ${permission.name}`">
                <button type="button" class="state-btn" :class="{ 'is-active': permissionState(permission.name) === 'inherited', 'state-btn--inherit-allow': permissionState(permission.name) === 'inherited' && permissionResolution(permission.name).inherited }" @click="setPermissionState(permission.name, 'inherited')">Inherit</button>
                <button type="button" class="state-btn" :class="{ 'is-active state-btn--allow': permissionState(permission.name) === 'direct' }" @click="setPermissionState(permission.name, 'direct')">Allow</button>
                <button type="button" class="state-btn" :class="{ 'is-active state-btn--deny': permissionState(permission.name) === 'denied' }" @click="setPermissionState(permission.name, 'denied')">Deny</button>
              </div>
            </div>
          </div>
        </section>
      </div>
    </BaseFormSection>

    <BaseFormSection title="Effective permissions" description="Merged view of inherited role permissions and direct overrides.">
      <div class="effective-grid">
        <div v-for="permission in effectivePermissions" :key="permission.name" class="effective-item">
          <strong>{{ permission.label }}</strong>
          <div class="effective-item__meta">
            <span class="effective-meta">Inherited: {{ permission.inherited ? 'Allow' : 'None' }}</span>
            <span class="effective-meta">Override: {{ permission.overrideLabel }}</span>
            <span :class="permission.finalAllowed ? 'is-allowed' : 'is-denied'">Final: {{ permission.finalAllowed ? 'Allowed' : 'Denied' }}</span>
          </div>
        </div>
      </div>
    </BaseFormSection>

    <BaseFormActions :loading="asyncForm.isSubmitting.value || isMetaLoading" :submit-disabled="!form.isDirty.value" @cancel="close" />
  </BaseForm>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

import type { FormSubmitContext } from '../../../shared/forms';
import { BaseForm, BaseFormActions, BaseFormField, BaseFormSection, useAsyncForm, useForm } from '../../../shared/forms';
import { useToast } from '../../../shared/toast';
import { usersService } from '../services/users.service';
import type { UserListItem } from '../types/users.types';
import { isNormalizedApiError } from '../../../services/api/interceptors';
import { cacheStore } from '../../../shared/cache';

type RoleMeta = { id: number; name: string; label: string };
type PermissionMeta = { id: number; name: string; label: string };
type PermissionSource = 'inherited' | 'direct' | 'denied';

const props = defineProps<{
  closeModal?: () => void;
  user: UserListItem;
  onUpdated?: (item: UserListItem) => void;
}>();

const form = useForm({
  name: props.user.name,
  email: props.user.email,
  password: '',
});

const asyncForm = useAsyncForm();
const toast = useToast();

const isMetaLoading = ref(true);
const roleQuery = ref('');
const permissionQuery = ref('');
const roles = ref<RoleMeta[]>([]);
const permissions = ref<PermissionMeta[]>([]);
const rolePermissions = ref<Record<string, string[]>>({});

const selectedRoleIds = ref(new Set<number>());
const selectedDirectPermissions = ref(new Set<string>(props.user.permissions ?? []));
const selectedDeniedPermissions = ref(new Set<string>(props.user.denied_permissions ?? []));

const close = (): void => props.closeModal?.();

/**
 * RBAC INHERITANCE STRATEGY:
 * - roles provide inherited permission sets
 * - direct permissions grant explicit additions
 * - denied permissions explicitly override grants
 *
 * Keeping this logic in one modal keeps enterprise RBAC edits understandable
 * and avoids ambiguous permission state during admin operations.
 */
const inheritedPermissions = computed(() => {
  const roleNames = roles.value
    .filter((role) => selectedRoleIds.value.has(role.id))
    .map((role) => role.name);

  return new Set(
    roleNames.flatMap((roleName) => rolePermissions.value[roleName] ?? []),
  );
});

const effectivePermissions = computed(() => {
  const all = new Set<string>();
  inheritedPermissions.value.forEach((permission) => all.add(permission));
  selectedDirectPermissions.value.forEach((permission) => all.add(permission));
  selectedDeniedPermissions.value.forEach((permission) => all.add(permission));

  return Array.from(all)
    .map((name) => {
      const meta = permissions.value.find((permission) => permission.name === name);
      const resolution = permissionResolution(name);
      return {
        name,
        label: meta?.label ?? name,
        inherited: resolution.inherited,
        overrideLabel: resolution.overrideLabel,
        finalAllowed: resolution.finalAllowed,
      };
    })
    .sort((a, b) => a.name.localeCompare(b.name));
});

const filteredRoles = computed(() => {
  const needle = roleQuery.value.trim().toLowerCase();
  if (!needle) return roles.value;
  return roles.value.filter((role) => role.name.toLowerCase().includes(needle) || role.label.toLowerCase().includes(needle));
});

const groupedPermissions = computed(() => {
  const needle = permissionQuery.value.trim().toLowerCase();
  const filtered = permissions.value.filter((permission) =>
    !needle || permission.name.toLowerCase().includes(needle) || permission.label.toLowerCase().includes(needle),
  );

  const bucket = new Map<string, PermissionMeta[]>();
  filtered.forEach((permission) => {
    const module = permission.name.split('.')[0] || 'system';
    const current = bucket.get(module) ?? [];
    bucket.set(module, [...current, permission]);
  });

  return Array.from(bucket.entries())
    .map(([module, list]) => ({
      module,
      permissions: list.sort((a, b) => a.name.localeCompare(b.name)),
    }))
    .sort((a, b) => a.module.localeCompare(b.module));
});

const toggleRole = (roleId: number): void => {
  if (selectedRoleIds.value.has(roleId)) {
    selectedRoleIds.value.delete(roleId);
  } else {
    selectedRoleIds.value.add(roleId);
  }
};

const permissionState = (permission: string): PermissionSource => {
  if (selectedDeniedPermissions.value.has(permission)) return 'denied';
  if (selectedDirectPermissions.value.has(permission)) return 'direct';
  return 'inherited';
};

const permissionResolution = (permission: string): {
  inherited: boolean;
  overrideType: 'none' | 'allow' | 'deny';
  overrideLabel: 'None' | 'Allow' | 'Deny';
  finalAllowed: boolean;
} => {
  const inherited = inheritedPermissions.value.has(permission);
  const isDenied = selectedDeniedPermissions.value.has(permission);
  const isDirect = selectedDirectPermissions.value.has(permission);

  if (isDenied) {
    return { inherited, overrideType: 'deny', overrideLabel: 'Deny', finalAllowed: false };
  }

  if (isDirect) {
    return { inherited, overrideType: 'allow', overrideLabel: 'Allow', finalAllowed: true };
  }

  return {
    inherited,
    overrideType: 'none',
    overrideLabel: 'None',
    finalAllowed: inherited,
  };
};

const setPermissionState = (permission: string, state: PermissionSource): void => {
  selectedDirectPermissions.value.delete(permission);
  selectedDeniedPermissions.value.delete(permission);
  if (state === 'direct') selectedDirectPermissions.value.add(permission);
  if (state === 'denied') selectedDeniedPermissions.value.add(permission);
};

const loadMeta = async (): Promise<void> => {
  try {
    isMetaLoading.value = true;
    const meta = await usersService.fetchRbacMeta();
    roles.value = meta.roles.map((entry) => ({
      id: entry.id,
      name: entry.name,
      label: entry.label ?? entry.name,
    }));
    permissions.value = meta.permissions.map((entry) => ({
      id: entry.id,
      name: entry.name,
      label: entry.label ?? entry.name,
    }));
    rolePermissions.value = meta.role_permissions;

    const initialRoleIds = meta.roles
      .filter((role) => props.user.roles.includes(role.name))
      .map((role) => role.id);
    selectedRoleIds.value = new Set(initialRoleIds);
  } finally {
    isMetaLoading.value = false;
  }
};

const handleSubmit = async ({ model }: FormSubmitContext<Record<string, unknown>>): Promise<void> => {
  form.clearErrors();

  const payload = {
    name: String(model.name ?? '').trim(),
    email: String(model.email ?? '').trim(),
    password: String(model.password ?? '').trim() || undefined,
    roles: Array.from(selectedRoleIds.value),
    permissions: Array.from(selectedDirectPermissions.value),
    denied_permissions: Array.from(selectedDeniedPermissions.value),
  };

  const result = await asyncForm.submit(async () => {
    return usersService.updateUser(props.user.id, payload);
  });

  if (!result) {
    const error = asyncForm.lastError.value;
    if (isNormalizedApiError(error) && error.code === 'validation' && error.errors) {
      form.setErrors(error.errors);
    }
    toast.error({ title: 'Update failed', message: 'Unable to update user RBAC data.' });
    return;
  }

  props.onUpdated?.({
    ...result,
    status: props.user.status,
  });

  cacheStore.invalidatePrefix('users.');
  cacheStore.invalidatePrefix('roles.');
  cacheStore.invalidatePrefix('permissions.');
  cacheStore.invalidatePrefix('dashboard.');

  toast.success({ title: 'User updated', message: 'RBAC assignments updated successfully.' });
  close();
};

onMounted(() => {
  void loadMeta();
});
</script>

<style scoped>
.rbac-control input{width:100%}
.rbac-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
.role-chip{display:grid;gap:3px;justify-items:start;text-align:left;padding:8px 10px;border-radius:10px;border:1px solid rgba(71,85,105,.55);background:rgba(15,23,42,.6);color:#cbd5e1}
.role-chip.is-active{border-color:rgba(34,197,94,.55);background:rgba(22,163,74,.18);color:#dcfce7}
.role-chip__label{font-size:12px;font-weight:600}
.permission-groups{display:grid;gap:12px}
.permission-group h4{margin:0 0 6px;color:#f8fafc;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
.perm-toggle{display:grid;gap:7px;border:1px solid rgba(71,85,105,.45);border-radius:10px;padding:9px}
.perm-toggle__meta{display:grid;gap:2px}
.perm-toggle__label{font-size:12px;color:#e2e8f0;font-weight:600}
.perm-toggle__actions{display:flex;gap:6px;flex-wrap:wrap}
.perm-toggle__status{display:flex;gap:6px;flex-wrap:wrap}
.status-chip{font-size:10px;border-radius:999px;padding:2px 8px;border:1px solid rgba(71,85,105,.45);color:#94a3b8;background:rgba(15,23,42,.45)}
.status-chip.is-ok{border-color:rgba(34,197,94,.55);color:#bbf7d0;background:rgba(22,163,74,.16)}
.status-chip.is-allow{border-color:rgba(34,197,94,.55);color:#bbf7d0;background:rgba(22,163,74,.16)}
.status-chip.is-deny{border-color:rgba(239,68,68,.55);color:#fecaca;background:rgba(185,28,28,.2)}
.status-chip.is-muted{border-color:rgba(71,85,105,.45);color:#94a3b8;background:rgba(15,23,42,.45)}
.state-btn{height:28px;padding:0 10px;border-radius:999px;border:1px solid rgba(71,85,105,.55);background:rgba(15,23,42,.6);color:#cbd5e1;font-size:11px}
.state-btn.is-active{border-color:rgba(59,130,246,.55);background:rgba(59,130,246,.18);color:#bfdbfe}
.state-btn--inherit-allow.is-active{border-color:rgba(34,197,94,.6);background:rgba(22,163,74,.2);color:#bbf7d0}
.state-btn--allow.is-active{border-color:rgba(34,197,94,.6);background:rgba(22,163,74,.2);color:#bbf7d0}
.state-btn--deny.is-active{border-color:rgba(239,68,68,.6);background:rgba(185,28,28,.24);color:#fecaca}
.effective-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
.effective-item{display:grid;gap:5px;border:1px solid rgba(71,85,105,.5);border-radius:8px;padding:7px 9px}
.effective-item strong{color:#e2e8f0;font-size:12px}
.effective-item__meta{display:flex;gap:8px;flex-wrap:wrap}
.effective-meta{font-size:11px;color:#94a3b8}
.is-allowed{color:#86efac;font-size:11px;text-transform:capitalize}
.is-denied{color:#fca5a5;font-size:11px;text-transform:capitalize}
@media (max-width:860px){.rbac-grid,.effective-grid{grid-template-columns:1fr}}
</style>
