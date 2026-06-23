<template>
  <BaseForm :model="form.model" @submit="handleSubmit">
    <BaseFormSection title="Edit permission" description="Maintain RBAC capability contract for this permission.">
      <BaseFormField label="Permission key" :required="true" :error="form.getFieldError('name')"><input :value="String(form.model.name)" @input="form.setField('name', ($event.target as HTMLInputElement).value)" /></BaseFormField>
      <BaseFormField label="Internal description"><input :value="String(form.model.description)" @input="form.setField('description', ($event.target as HTMLInputElement).value)" /></BaseFormField>
    </BaseFormSection>
    <BaseFormSection title="Translations" description="Multilingual label/description editing is isolated from technical permission key.">
      <div class="locale-tabs">
        <button v-for="locale in enabledLocales" :key="locale.code" type="button" class="locale-tab" :class="{ 'is-active': activeLocale === locale.code }" @click="activeLocale = locale.code">{{ locale.label }}</button>
      </div>
      <div v-for="locale in enabledLocales" v-show="activeLocale === locale.code" :key="locale.code" class="locale-panel">
        <BaseFormField :label="`${locale.label} label`"><input :value="translations[locale.code].label" @input="translations[locale.code].label = ($event.target as HTMLInputElement).value" /></BaseFormField>
        <BaseFormField :label="`${locale.label} description`"><textarea rows="2" :value="translations[locale.code].description" @input="translations[locale.code].description = ($event.target as HTMLTextAreaElement).value" /></BaseFormField>
      </div>
    </BaseFormSection>
    <BaseFormActions :loading="asyncForm.isSubmitting.value" :submit-disabled="!form.isDirty.value" @cancel="close" />
  </BaseForm>
</template>
<script setup lang="ts">
import { ref } from 'vue';

import type { FormSubmitContext } from '../../../shared/forms';
import { BaseForm, BaseFormActions, BaseFormField, BaseFormSection, useAsyncForm, useForm } from '../../../shared/forms';
import { getEnabledLocales } from '../../../shared/i18n/helpers';
import type { LocaleCode } from '../../../shared/i18n/config';
import { useToast } from '../../../shared/toast';
import { permissionsService } from '../services/permissions.service';
import type { PermissionListItem } from '../types/permissions.types';

const props = defineProps<{ closeModal?: () => void; permission: PermissionListItem; onUpdated?: (item: PermissionListItem) => void }>();
const form = useForm({ name: props.permission.name, description: props.permission.description });
const asyncForm = useAsyncForm();
const toast = useToast();
const enabledLocales = getEnabledLocales();
const activeLocale = ref<LocaleCode>(enabledLocales[0]?.code ?? 'en');
const translations = ref<Record<LocaleCode, { label: string; description: string }>>({
  en: { label: props.permission.translations?.en?.label ?? props.permission.label ?? props.permission.name, description: props.permission.translations?.en?.description ?? props.permission.description ?? '' },
  uk: { label: props.permission.translations?.uk?.label ?? props.permission.label ?? props.permission.name, description: props.permission.translations?.uk?.description ?? props.permission.description ?? '' },
  de: { label: props.permission.translations?.de?.label ?? props.permission.label ?? props.permission.name, description: props.permission.translations?.de?.description ?? props.permission.description ?? '' },
});
const close = (): void => props.closeModal?.();

const handleSubmit = async ({ model }: FormSubmitContext<Record<string, unknown>>): Promise<void> => {
  const result = await asyncForm.submit(async () => {
    const translationPayload = Object.fromEntries(
      enabledLocales.map((locale) => [locale.code, { ...translations.value[locale.code] }]),
    );
    const updated = await permissionsService.updatePermission(props.permission.id, {
      description: String(model.description || ''),
      translations: translationPayload,
    });
    props.onUpdated?.(updated);
    return updated;
  });
  if (result) { toast.success({ title: 'Permission updated', message: 'Permission edit workflow completed.' }); close(); }
};
</script>
<style scoped>
.locale-tabs{display:flex;gap:8px;flex-wrap:wrap}
.locale-tab{height:30px;padding:0 10px;border-radius:999px;border:1px solid rgba(71,85,105,.6);background:rgba(15,23,42,.65);color:#cbd5e1;font-size:12px}
.locale-tab.is-active{border-color:rgba(59,130,246,.55);background:rgba(59,130,246,.2);color:#bfdbfe}
.locale-panel{display:grid;gap:10px}
</style>
