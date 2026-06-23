<template>
  <BaseForm :model="form.model" @submit="handleSubmit">
    <BaseFormSection title="Edit user" layout="grid">
      <BaseFormField label="Name" :required="true" :error="form.getFieldError('name')">
        <input :value="String(form.model.name)" @input="form.setField('name', ($event.target as HTMLInputElement).value)" />
      </BaseFormField>
      <BaseFormField label="Status">
        <input :value="String(form.model.status)" @input="form.setField('status', ($event.target as HTMLInputElement).value)" />
      </BaseFormField>
    </BaseFormSection>
    <BaseFormActions :loading="asyncForm.isSubmitting.value" :submit-disabled="!form.isDirty.value" @cancel="close" />
  </BaseForm>
</template>

<script setup lang="ts">
import type { FormSubmitContext } from '../../../shared/forms';
import { BaseForm, BaseFormActions, BaseFormField, BaseFormSection, useAsyncForm, useForm } from '../../../shared/forms';
import { useToast } from '../../../shared/toast';
import type { UserListItem } from '../types/users.types';

const props = defineProps<{
  closeDrawer?: () => void;
  user: UserListItem;
  onUpdated?: (item: UserListItem) => void;
}>();

const form = useForm({ name: props.user.name, status: props.user.status });
const asyncForm = useAsyncForm();
const toast = useToast();

const close = (): void => props.closeDrawer?.();

const handleSubmit = async ({ model }: FormSubmitContext<Record<string, unknown>>): Promise<void> => {
  const result = await asyncForm.submit(async () => {
    await new Promise((resolve) => setTimeout(resolve, 200));
    const updated: UserListItem = {
      ...props.user,
      name: String(model.name),
      status: (String(model.status) === 'inactive' ? 'inactive' : 'active') as 'active' | 'inactive',
    };
    props.onUpdated?.(updated);
    return updated;
  });

  if (result) {
    toast.success({ title: 'User updated', message: 'User edit shell completed.' });
    close();
  }
};
</script>
