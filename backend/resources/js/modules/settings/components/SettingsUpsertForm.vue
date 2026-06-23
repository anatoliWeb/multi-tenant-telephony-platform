<template>
  <BaseForm :model="form.model" layout="grid" @submit="handleSubmit">
    <BaseFormSection title="Setting identity" class="full">
      <BaseFormField label="Key" :required="true" :error="form.getFieldError('key')">
        <input :value="String(form.model.key)" :disabled="isEdit" @input="form.setField('key', ($event.target as HTMLInputElement).value)" />
      </BaseFormField>
      <BaseFormField label="Label" :required="true" :error="form.getFieldError('label')">
        <input :value="String(form.model.label)" @input="form.setField('label', ($event.target as HTMLInputElement).value)" />
      </BaseFormField>
      <BaseFormField label="Group" :required="true" :error="form.getFieldError('group')">
        <input :value="String(form.model.group)" @input="form.setField('group', ($event.target as HTMLInputElement).value)" />
      </BaseFormField>
      <BaseFormField label="Type">
        <select :value="String(form.model.type)" @change="form.setField('type', ($event.target as HTMLSelectElement).value)">
          <option v-for="type in types" :key="type" :value="type">{{ type }}</option>
        </select>
      </BaseFormField>
      <BaseFormField label="Description" class="span-2">
        <textarea rows="2" :value="String(form.model.description || '')" @input="form.setField('description', ($event.target as HTMLTextAreaElement).value)" />
      </BaseFormField>
    </BaseFormSection>

    <BaseFormSection title="Values" class="full">
      <BaseFormField label="Value">
        <input :value="String(form.model.value ?? '')" @input="form.setField('value', ($event.target as HTMLInputElement).value)" />
      </BaseFormField>
      <BaseFormField label="Default value">
        <input :value="String(form.model.default_value ?? '')" @input="form.setField('default_value', ($event.target as HTMLInputElement).value)" />
      </BaseFormField>
      <BaseFormField label="Priority">
        <input type="number" min="0" :value="String(form.model.priority ?? 100)" @input="form.setField('priority', Number(($event.target as HTMLInputElement).value || 0))" />
      </BaseFormField>
      <BaseFormField label="Scope type">
        <select :value="scopeType" @change="onScopeTypeChange(($event.target as HTMLSelectElement).value as ScopeType)">
          <option value="global">Global</option>
          <option value="role">Role</option>
          <option value="permission">Permission</option>
          <option value="user">User</option>
        </select>
      </BaseFormField>
      <BaseFormField v-if="scopeType === 'role'" label="Role scope" class="span-2">
        <select :value="String(form.model.scope_role_id ?? '')" @change="form.setField('scope_role_id', normalizeNullableNumber(($event.target as HTMLSelectElement).value))">
          <option value="">Select role</option>
          <option v-for="role in roles" :key="role.id" :value="String(role.id)">{{ role.label || role.name }}</option>
        </select>
      </BaseFormField>
      <BaseFormField v-if="scopeType === 'permission'" label="Permission scope" class="span-2">
        <select :value="String(form.model.scope_permission_id ?? '')" @change="form.setField('scope_permission_id', normalizeNullableNumber(($event.target as HTMLSelectElement).value))">
          <option value="">Select permission</option>
          <option v-for="permission in permissions" :key="permission.id" :value="String(permission.id)">{{ permission.label || permission.name }}</option>
        </select>
      </BaseFormField>
      <BaseFormField v-if="scopeType === 'user'" label="User scope" class="span-2">
        <select :value="String(form.model.scope_user_id ?? '')" @change="form.setField('scope_user_id', normalizeNullableNumber(($event.target as HTMLSelectElement).value))">
          <option value="">Select user</option>
          <option v-for="user in users" :key="user.id" :value="String(user.id)">{{ user.name }} ({{ user.email }})</option>
        </select>
      </BaseFormField>
    </BaseFormSection>

    <BaseFormSection title="Flags" class="full">
      <label class="settings-flag"><input type="checkbox" :checked="Boolean(form.model.is_frontend)" @change="form.setField('is_frontend', ($event.target as HTMLInputElement).checked)" /> Frontend</label>
      <label class="settings-flag"><input type="checkbox" :checked="Boolean(form.model.is_backend)" @change="form.setField('is_backend', ($event.target as HTMLInputElement).checked)" /> Backend</label>
      <label class="settings-flag"><input type="checkbox" :checked="Boolean(form.model.is_public)" @change="form.setField('is_public', ($event.target as HTMLInputElement).checked)" /> Public</label>
      <label class="settings-flag"><input type="checkbox" :checked="Boolean(form.model.is_encrypted)" @change="form.setField('is_encrypted', ($event.target as HTMLInputElement).checked)" /> Encrypted</label>
      <label class="settings-flag"><input type="checkbox" :checked="Boolean(form.model.is_active)" @change="form.setField('is_active', ($event.target as HTMLInputElement).checked)" /> Active</label>
      <label class="settings-flag"><input type="checkbox" :checked="Boolean(form.model.is_system)" @change="form.setField('is_system', ($event.target as HTMLInputElement).checked)" /> System</label>
    </BaseFormSection>

    <BaseFormSection title="Translations" description="Localized labels/descriptions are presentation-only and do not change technical setting keys." class="full">
      <div class="locale-tabs">
        <button
          v-for="localeItem in enabledLocales"
          :key="localeItem.code"
          type="button"
          class="locale-tab"
          :class="{ 'is-active': activeLocale === localeItem.code }"
          @click="activeLocale = localeItem.code"
        >
          {{ localeItem.label }}
        </button>
      </div>

      <div v-for="localeItem in enabledLocales" v-show="activeLocale === localeItem.code" :key="`settings-${localeItem.code}`" class="locale-panel">
        <BaseFormField :label="`${localeItem.label} label`">
          <input
            :value="translations[localeItem.code].label"
            @input="translations[localeItem.code].label = ($event.target as HTMLInputElement).value"
          />
        </BaseFormField>
        <BaseFormField :label="`${localeItem.label} description`">
          <textarea
            rows="2"
            :value="translations[localeItem.code].description"
            @input="translations[localeItem.code].description = ($event.target as HTMLTextAreaElement).value"
          />
        </BaseFormField>
      </div>
    </BaseFormSection>

    <BaseFormActions
      :loading="asyncForm.isSubmitting.value || isMetaLoading"
      :submit-disabled="!form.isDirty.value"
      :submit-label="isEdit ? 'Update setting' : 'Create setting'"
      :loading-label="isEdit ? 'Updating...' : 'Creating...'"
      @cancel="close"
    />
  </BaseForm>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import type { FormSubmitContext } from '../../../shared/forms';
import { BaseForm, BaseFormActions, BaseFormField, BaseFormSection, useAsyncForm, useForm } from '../../../shared/forms';
import { getEnabledLocales } from '../../../shared/i18n/helpers';
import type { LocaleCode } from '../../../shared/i18n/config';
import { api } from '../../../services/api/client';
import { settingsService } from '../services/settings.service';
import type { SettingValueType, SystemSettingRecord, UpsertSettingPayload } from '../types/settings.types';

type ScopeType = 'global' | 'role' | 'permission' | 'user';
interface MetaRef { id: number; name: string; label?: string }
interface UserOption { id: number; name: string; email: string }
type LocalizedTranslation = { label: string; description: string };

const props = defineProps<{
  mode: 'create' | 'edit';
  setting?: SystemSettingRecord;
  closeModal?: () => void;
  onSaved?: (setting: SystemSettingRecord) => void;
}>();

const roles = ref<MetaRef[]>([]);
const permissions = ref<MetaRef[]>([]);
const users = ref<UserOption[]>([]);
const isMetaLoading = ref(true);
const asyncForm = useAsyncForm();
const { locale } = useI18n({ useScope: 'global' });
const enabledLocales = getEnabledLocales();
const activeLocale = ref<LocaleCode>(enabledLocales[0]?.code ?? 'en');
const isEdit = computed(() => props.mode === 'edit');
const types: SettingValueType[] = ['string', 'integer', 'float', 'boolean', 'json', 'array', 'enum', 'color', 'select', 'textarea', 'toggle'];

const form = useForm<UpsertSettingPayload>({
  key: props.setting?.key ?? '',
  label: props.setting?.label ?? '',
  group: props.setting?.group ?? 'general',
  description: props.setting?.description ?? '',
  type: props.setting?.type ?? 'string',
  value: props.setting?.value ?? '',
  default_value: props.setting?.default_value ?? '',
  is_frontend: props.setting?.is_frontend ?? true,
  is_backend: props.setting?.is_backend ?? true,
  is_public: props.setting?.is_public ?? false,
  is_encrypted: props.setting?.is_encrypted ?? false,
  priority: props.setting?.priority ?? 100,
  is_active: props.setting?.is_active ?? true,
  is_system: props.setting?.is_system ?? false,
  scope_user_id: props.setting?.scope.user_id ?? null,
  scope_role_id: props.setting?.scope.role_id ?? null,
  scope_permission_id: props.setting?.scope.permission_id ?? null,
});

const translations = ref<Record<LocaleCode, LocalizedTranslation>>({
  en: { label: '', description: '' },
  uk: { label: '', description: '' },
  de: { label: '', description: '' },
});

const scopeType = computed<ScopeType>({
  get: () => {
    if (form.model.scope_user_id) return 'user';
    if (form.model.scope_permission_id) return 'permission';
    if (form.model.scope_role_id) return 'role';
    return 'global';
  },
  set: (value) => onScopeTypeChange(value),
});

const close = (): void => props.closeModal?.();

const normalizeNullableNumber = (value: string): number | null => {
  if (!value) return null;
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : null;
};

const onScopeTypeChange = (value: ScopeType): void => {
  if (value !== 'user') form.setField('scope_user_id', null);
  if (value !== 'role') form.setField('scope_role_id', null);
  if (value !== 'permission') form.setField('scope_permission_id', null);
};

const loadMeta = async (): Promise<void> => {
  try {
    isMetaLoading.value = true;
    const [meta, usersResponse] = await Promise.all([
      api.get<{ roles?: MetaRef[]; permissions?: MetaRef[] }>('/v1/meta'),
      api.get<Array<{ id: number; name: string; email: string }>>('/v1/users'),
    ]);
    roles.value = meta.data?.roles ?? [];
    permissions.value = meta.data?.permissions ?? [];
    users.value = usersResponse.data ?? [];
  } finally {
    isMetaLoading.value = false;
  }
};

const handleSubmit = async ({ model }: FormSubmitContext<Record<string, unknown>>): Promise<void> => {
  if (!String(model.key || '').trim()) {
    form.setErrors({ key: ['Key is required.'] });
    return;
  }
  if (!String(model.label || '').trim()) {
    form.setErrors({ label: ['Label is required.'] });
    return;
  }

  const payload = { ...model } as UpsertSettingPayload;
  payload.translations = Object.fromEntries(
    enabledLocales.map((localeItem) => [localeItem.code, { ...translations.value[localeItem.code] }]),
  );
  const result = await asyncForm.submit(async () => {
    if (props.mode === 'edit' && props.setting) {
      return await settingsService.updateSetting(props.setting.id, payload);
    }
    return await settingsService.createSetting(payload);
  });

  if (result) {
    props.onSaved?.(result);
    close();
  }
};

onMounted(() => {
  if (props.setting) {
    const activeCode = locale.value as LocaleCode;
    if (translations.value[activeCode]) {
      translations.value[activeCode].label = props.setting.label;
      translations.value[activeCode].description = props.setting.description ?? '';
    }
  }
  void loadMeta();
});
</script>

<style scoped>
.full{grid-column:1 / -1}
.span-2{grid-column:1 / -1}
.settings-flag{display:flex;align-items:center;gap:8px;color:#cbd5e1;font-size:12px}
input,select,textarea{width:100%;border-radius:8px;border:1px solid rgba(71,85,105,.6);background:rgba(15,23,42,.7);color:#e2e8f0;padding:8px 10px;font-size:12px}
textarea{resize:vertical}
.locale-tabs{display:flex;gap:8px;flex-wrap:wrap}
.locale-tab{height:30px;padding:0 10px;border-radius:999px;border:1px solid rgba(71,85,105,.6);background:rgba(15,23,42,.65);color:#cbd5e1;font-size:12px}
.locale-tab.is-active{border-color:rgba(59,130,246,.55);background:rgba(59,130,246,.2);color:#bfdbfe}
.locale-panel{display:grid;gap:10px}
</style>
