import type { ComputedRef, Ref } from 'vue';
import type { ValidationErrors } from '../../validation/types/validation.types';

export type FormLayout = 'vertical' | 'grid';

export interface FormSubmitContext<TModel extends Record<string, unknown>> {
  model: TModel;
  reset: () => void;
}

export interface UseFormResult<TModel extends Record<string, unknown>> {
  model: TModel;
  initialModel: TModel;
  touched: Partial<Record<keyof TModel, boolean>>;
  dirtyFields: Partial<Record<keyof TModel, boolean>>;
  isDirty: ComputedRef<boolean>;
  isSubmitting: Ref<boolean>;
  errors: Ref<ValidationErrors>;
  hasErrors: ComputedRef<boolean>;
  getFieldError: (field: keyof TModel | string) => string;
  setErrors: (errors: unknown) => void;
  clearErrors: () => void;
  clearFieldError: (field: keyof TModel | string) => void;
  touchField: (field: keyof TModel) => void;
  setField: <K extends keyof TModel>(field: K, value: TModel[K]) => void;
  reset: () => void;
  submit: (handler: (model: TModel) => Promise<void> | void) => Promise<void>;
}
