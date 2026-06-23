<template>
  <BaseForm :model="form.model" @submit="handleSubmit">
    <BaseFormSection title="Create role">
      <BaseFormField label="Role name" :required="true" :error="form.getFieldError('name')">
        <input :value="String(form.model.name)" @input="form.setField('name', ($event.target as HTMLInputElement).value)" />
      </BaseFormField>
      <BaseFormField label="Internal description">
        <input :value="String(form.model.description)" @input="form.setField('description', ($event.target as HTMLInputElement).value)" />
      </BaseFormField>
    </BaseFormSection>

    <BaseFormSection title="Role permissions" description="Assign permissions during role creation for create/edit workflow parity.">
      <div class="control">
        <input type="text" placeholder="Search permissions..." :value="query" @input="query = ($event.target as HTMLInputElement).value" />
      </div>

      <div class="groups">
        <section v-for="group in groupedPermissions" :key="group.module" class="group">
          <h4>{{ group.module }}</h4>
          <div class="grid">
            <button
              v-for="permission in group.permissions"
              :key="permission.name"
              type="button"
              class="perm-chip"
              :class="{ 'is-active': selectedPermissions.has(permission.name) }"
              :aria-pressed="selectedPermissions.has(permission.name)"
              @click="togglePermission(permission.name)"
            >
              <span class="perm-chip__label">{{ permission.label }}</span>
            </button>
          </div>
        </section>
      </div>
    </BaseFormSection>

    <BaseFormSection title="Translations" description="Localized presentation fields are managed separately from immutable technical role key.">
      <div class="locale-tabs">
        <button
          v-for="locale in enabledLocales"
          :key="locale.code"
          type="button"
          class="locale-tab"
          :class="{ 'is-active': activeLocale === locale.code }"
          @click="activeLocale = locale.code"
        >
          {{ locale.label }}
        </button>
      </div>

      <div v-for="locale in enabledLocales" v-show="activeLocale === locale.code" :key="`${locale.code}-fields`" class="locale-panel">
        <BaseFormField :label="`${locale.label} label`">
          <input
            :value="translations[locale.code].label"
            @input="translations[locale.code].label = ($event.target as HTMLInputElement).value"
          />
        </BaseFormField>
        <BaseFormField :label="`${locale.label} description`">
          <textarea
            rows="2"
            :value="translations[locale.code].description"
            @input="translations[locale.code].description = ($event.target as HTMLTextAreaElement).value"
          />
        </BaseFormField>
      </div>
    </BaseFormSection>
    <BaseFormActions :loading="asyncForm.isSubmitting.value || isMetaLoading" :submit-disabled="!form.isDirty.value" @cancel="close" />
  </BaseForm>
</template>
<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

import type { FormSubmitContext } from '../../../shared/forms';
import { BaseForm, BaseFormActions, BaseFormField, BaseFormSection, useAsyncForm, useForm } from '../../../shared/forms';
import { getEnabledLocales } from '../../../shared/i18n/helpers';
import type { LocaleCode } from '../../../shared/i18n/config';
import { useToast } from '../../../shared/toast';
import { permissionsService } from '../../permissions/services/permissions.service';
import { rolesService } from '../services/roles.service';
import type { RoleListItem } from '../types/roles.types';

type LocalizedTranslation = {
  label: string;
  description: string;
};

const props = defineProps<{ closeModal?: () => void; onCreated?: (item: RoleListItem) => void }>();
const form = useForm({ name: '', description: '' });
const asyncForm = useAsyncForm();
const toast = useToast();
const enabledLocales = getEnabledLocales();
const activeLocale = ref<LocaleCode>(enabledLocales[0]?.code ?? 'en');
const isMetaLoading = ref(true);
const query = ref('');
const allPermissions = ref<Array<{ name: string; label: string }>>([]);
const selectedPermissions = ref(new Set<string>());
const translations = ref<Record<LocaleCode, LocalizedTranslation>>({
  en: { label: '', description: '' },
  uk: { label: '', description: '' },
  de: { label: '', description: '' },
});
const close = (): void => props.closeModal?.();

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

const togglePermission = (permissionName: string): void => {
  if (selectedPermissions.value.has(permissionName)) {
    selectedPermissions.value.delete(permissionName);
    return;
  }

  selectedPermissions.value.add(permissionName);
};

const loadMeta = async (): Promise<void> => {
  try {
    isMetaLoading.value = true;
    const permissions = await permissionsService.fetchPermissions();
    allPermissions.value = permissions.map((entry) => ({ name: entry.name, label: entry.label }));
  } finally {
    isMetaLoading.value = false;
  }
};

const handleSubmit = async ({ model }: FormSubmitContext<Record<string, unknown>>): Promise<void> => {
  if (!String(model.name).trim()) { form.setErrors({ name: ['Role name is required.'] }); return; }
  const result = await asyncForm.submit(async () => {
    const translationPayload = Object.fromEntries(
      enabledLocales.map((locale) => [locale.code, { ...translations.value[locale.code] }]),
    );

    const created = await rolesService.createRole({
      name: String(model.name).trim(),
      description: String(model.description || ''),
      permissions: Array.from(selectedPermissions.value).sort((a, b) => a.localeCompare(b)),
      translations: translationPayload,
    });
    props.onCreated?.(created);
    return created;
  });
  if (result) { toast.success({ title: 'Role created', message: 'Role and permissions created successfully.' }); close(); }
};

onMounted(() => {
  void loadMeta();
});
</script>
<style scoped>
.control input{width:100%}
.groups{display:grid;gap:10px}
.group h4{margin:0 0 6px;color:#f8fafc;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
.perm-chip{display:grid;gap:3px;justify-items:start;text-align:left;padding:8px 10px;border-radius:10px;border:1px solid rgba(71,85,105,.55);background:rgba(15,23,42,.6);color:#cbd5e1}
.perm-chip.is-active{border-color:rgba(34,197,94,.55);background:rgba(22,163,74,.18);color:#dcfce7}
.perm-chip__label{font-size:12px;font-weight:600}
.locale-tabs{display:flex;gap:8px;flex-wrap:wrap}
.locale-tab{height:30px;padding:0 10px;border-radius:999px;border:1px solid rgba(71,85,105,.6);background:rgba(15,23,42,.65);color:#cbd5e1;font-size:12px}
.locale-tab.is-active{border-color:rgba(59,130,246,.55);background:rgba(59,130,246,.2);color:#bfdbfe}
.locale-panel{display:grid;gap:10px}
@media (max-width:860px){.grid{grid-template-columns:1fr}}
</style>
