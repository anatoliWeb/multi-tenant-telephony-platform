import { computed, reactive, ref } from 'vue';

import { useValidation } from '../../validation';
import type { UseFormResult } from '../types/form.types';
import { formUtils } from '../utils/form.utils';

/**
 * Reusable form state manager.
 *
 * ARCHITECTURE NOTES:
 * - dirty tracking is field-aware for unsaved-change workflows
 * - touch tracking is independent from validation so we can plug backend/schema
 *   validation later without replacing form-state contracts
 * - submit lifecycle is centralized for consistent async UX in CRUD modules
 */
export const useForm = <TModel extends Record<string, unknown>>(initialState: TModel): UseFormResult<TModel> => {
  const initialModel = formUtils.cloneModel(initialState);
  const model = reactive(formUtils.cloneModel(initialState)) as TModel;

  const touched = reactive({} as Partial<Record<keyof TModel, boolean>>);
  const dirtyFields = reactive({} as Partial<Record<keyof TModel, boolean>>);
  const isSubmitting = ref(false);
  const validation = useValidation();

  const recomputeDirty = (): void => {
    (Object.keys(model) as Array<keyof TModel>).forEach((key) => {
      dirtyFields[key] = !formUtils.isEqual(model[key], initialModel[key]);
    });
  };

  const isDirty = computed(() => {
    return Object.values(dirtyFields).some(Boolean);
  });

  const touchField = (field: keyof TModel): void => {
    touched[field] = true;
  };

  const setField = <K extends keyof TModel>(field: K, value: TModel[K]): void => {
    model[field] = value;
    touchField(field);
    dirtyFields[field] = !formUtils.isEqual(model[field], initialModel[field]);
    validation.clearField(String(field));
  };

  const reset = (): void => {
    (Object.keys(model) as Array<keyof TModel>).forEach((key) => {
      model[key] = formUtils.cloneModel(initialModel[key]) as TModel[typeof key];
      touched[key] = false;
      dirtyFields[key] = false;
    });
    validation.clearErrors();
  };

  const submit = async (handler: (currentModel: TModel) => Promise<void> | void): Promise<void> => {
    if (isSubmitting.value) return;

    isSubmitting.value = true;

    try {
      await handler(model);
      (Object.keys(model) as Array<keyof TModel>).forEach((key) => {
        initialModel[key] = formUtils.cloneModel(model[key]) as TModel[typeof key];
      });
      recomputeDirty();
    } finally {
      isSubmitting.value = false;
    }
  };

  recomputeDirty();

  return {
    model,
    initialModel,
    errors: validation.errors,
    hasErrors: validation.hasErrors,
    getFieldError: (field) => validation.getFieldError(String(field)),
    setErrors: (errors) => validation.setErrors(errors),
    clearErrors: () => validation.clearErrors(),
    clearFieldError: (field) => validation.clearField(String(field)),
    touched,
    dirtyFields,
    isDirty,
    isSubmitting,
    touchField,
    setField,
    reset,
    submit,
  };
};
