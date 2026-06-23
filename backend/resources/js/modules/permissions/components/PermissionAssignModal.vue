<template>
  <section class="assign-modal">
    <p class="assign-modal__text">
      Assign permission <strong>{{ permission.label }}</strong> to roles.
    </p>

    <div class="assign-modal__roles">
      <label v-for="role in roles" :key="role.id" class="assign-role">
        <input
          type="checkbox"
          :checked="selectedRoleNames.has(role.name)"
          @change="toggleRole(role.name)"
        />
        <span>{{ role.label }}</span>
      </label>
    </div>

    <div class="assign-modal__actions">
      <button type="button" class="assign-btn" :disabled="isSaving" @click="save">{{ isSaving ? 'Saving...' : 'Save assignments' }}</button>
    </div>
  </section>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';

import { useToast } from '../../../shared/toast';
import type { PermissionListItem } from '../types/permissions.types';
import { rolesService } from '../../roles/services/roles.service';

const props = defineProps<{ permission: PermissionListItem }>();

const toast = useToast();
const roles = ref<Array<{ id: number; name: string; label: string }>>([]);
const selectedRoleNames = ref(new Set<string>(props.permission.used_by_roles));
const isSaving = ref(false);

const toggleRole = (roleName: string): void => {
  if (selectedRoleNames.value.has(roleName)) {
    selectedRoleNames.value.delete(roleName);
  } else {
    selectedRoleNames.value.add(roleName);
  }
};

const loadRoles = async (): Promise<void> => {
  const roleItems = await rolesService.fetchRoles();
  roles.value = roleItems.map((entry) => ({ id: entry.id, name: entry.name, label: entry.label }));
};

const save = async (): Promise<void> => {
  isSaving.value = true;
  try {
    await new Promise((resolve) => setTimeout(resolve, 200));
    toast.success({
      title: 'Assignment prepared',
      message: `Selected roles: ${Array.from(selectedRoleNames.value).join(', ') || 'none'}`,
    });
  } finally {
    isSaving.value = false;
  }
};

onMounted(() => {
  void loadRoles();
});
</script>

<style scoped>
.assign-modal{display:grid;gap:10px}
.assign-modal__text{margin:0;color:#cbd5e1;font-size:13px;line-height:1.5}
.assign-modal__text strong{color:#f8fafc}
.assign-modal__roles{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
.assign-role{display:flex;gap:8px;align-items:center;color:#cbd5e1;font-size:12px}
.assign-modal__actions{display:flex;justify-content:flex-end}
.assign-btn{height:32px;border-radius:8px;border:1px solid rgba(59,130,246,.55);background:rgba(59,130,246,.2);color:#bfdbfe;padding:0 11px;font-size:12px;font-weight:600}
@media (max-width:700px){.assign-modal__roles{grid-template-columns:1fr}}
</style>
