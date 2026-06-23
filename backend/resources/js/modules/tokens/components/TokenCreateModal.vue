<template>
  <BaseForm :model="form.model" @submit="handleSubmit">
    <BaseFormSection title="Create token">
      <BaseFormField label="Token name" :required="true" :error="form.getFieldError('name')">
        <input :value="String(form.model.name)" @input="form.setField('name', ($event.target as HTMLInputElement).value)" />
      </BaseFormField>
    </BaseFormSection>

    <BaseFormSection title="Scopes" description="Select scoped permissions for this token.">
      <div class="control">
        <input type="text" placeholder="Search scopes..." :value="query" @input="query = ($event.target as HTMLInputElement).value" />
      </div>

      <div class="groups">
        <section v-for="group in groupedPermissions" :key="group.module" class="group">
          <h4>{{ group.module }}</h4>
          <div class="grid">
            <button
              v-for="permission in group.permissions"
              :key="permission.name"
              type="button"
              class="scope-chip"
              :class="{ 'is-active': selectedScopes.has(permission.name) }"
              :aria-pressed="selectedScopes.has(permission.name)"
              @click="toggleScope(permission.name)"
            >
              <span class="scope-chip__label">{{ permission.label }}</span>
            </button>
          </div>
        </section>
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
import { permissionsService } from '../../permissions/services/permissions.service';
import { tokensService } from '../services/tokens.service';
import type { TokenListItem } from '../types/tokens.types';

const props = defineProps<{ closeModal?: () => void; onCreated?: (item: TokenListItem) => void }>();
const form = useForm({ name: '' });
const asyncForm = useAsyncForm();
const toast = useToast();
const close = (): void => props.closeModal?.();

const isMetaLoading = ref(true);
const query = ref('');
const allPermissions = ref<Array<{ name: string; label: string }>>([]);
const selectedScopes = ref(new Set<string>(['users.view']));

const groupedPermissions = computed(() => {
  const needle = query.value.trim().toLowerCase();
  const filtered = allPermissions.value.filter((permission) =>
    !needle || permission.name.toLowerCase().includes(needle) || permission.label.toLowerCase().includes(needle),
  );

  const bucket = new Map<string, Array<{ name: string; label: string }>>();
  filtered.forEach((permission) => {
    const module = permission.name.split('.')[0] || 'system';
    const current = bucket.get(module) ?? [];
    bucket.set(module, [...current, permission]);
  });

  return Array.from(bucket.entries())
    .map(([module, permissions]) => ({
      module,
      permissions: permissions.sort((a, b) => a.name.localeCompare(b.name)),
    }))
    .sort((a, b) => a.module.localeCompare(b.module));
});

const toggleScope = (scope: string): void => {
  if (selectedScopes.value.has(scope)) {
    selectedScopes.value.delete(scope);
    return;
  }

  selectedScopes.value.add(scope);
};

const loadPermissions = async (): Promise<void> => {
  try {
    isMetaLoading.value = true;
    const permissions = await permissionsService.fetchPermissions();
    allPermissions.value = permissions.map((permission) => ({
      name: permission.name,
      label: permission.label,
    }));
  } finally {
    isMetaLoading.value = false;
  }
};

const handleSubmit = async ({ model }: FormSubmitContext<Record<string, unknown>>): Promise<void> => {
  if (!String(model.name).trim()) { form.setErrors({ name: ['Token name is required.'] }); return; }
  const result = await asyncForm.submit(async () => {
    const created = await tokensService.createToken({
      name: String(model.name).trim(),
      scopes: Array.from(selectedScopes.value).sort((a, b) => a.localeCompare(b)),
    });
    props.onCreated?.(created);
    return created;
  });
  if (result) { toast.success({ title: 'Token created', message: 'Token scopes assigned successfully.' }); close(); }
};

onMounted(() => {
  void loadPermissions();
});
</script>
<style scoped>
.control input{width:100%}
.groups{display:grid;gap:10px}
.group h4{margin:0 0 6px;color:#f8fafc;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
.scope-chip{display:grid;gap:3px;justify-items:start;text-align:left;padding:8px 10px;border-radius:10px;border:1px solid rgba(71,85,105,.55);background:rgba(15,23,42,.6);color:#cbd5e1}
.scope-chip.is-active{border-color:rgba(14,165,233,.55);background:rgba(2,132,199,.22);color:#bae6fd}
.scope-chip__label{font-size:12px;font-weight:600}
@media (max-width:860px){.grid{grid-template-columns:1fr}}
</style>
