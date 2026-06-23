import { computed, ref } from 'vue';

import type { ValidationErrors } from '../types/validation.types';
import {
  clearAllErrors,
  clearFieldError as clearFieldErrorUtil,
  getFirstFieldError,
  hasFieldError,
  normalizeValidationErrors,
} from '../utils/validation.utils';

/**
 * Shared validation state manager for page/modal/drawer forms.
 *
 * Keeps backend-error mapping and field-level error lifecycle in one place so
 * form flows remain consistent across all interaction surfaces.
 */
export const useValidation = () => {
  const errors = ref<ValidationErrors>({});

  const hasErrors = computed(() => Object.keys(errors.value).length > 0);

  const setErrors = (payload: unknown): void => {
    errors.value = normalizeValidationErrors(payload);
  };

  const getFieldError = (field: string): string => {
    return getFirstFieldError(errors.value, field);
  };

  const hasError = (field: string): boolean => {
    return hasFieldError(errors.value, field);
  };

  const clearField = (field: string): void => {
    errors.value = clearFieldErrorUtil(errors.value, field);
  };

  const clearErrors = (): void => {
    errors.value = clearAllErrors();
  };

  return {
    errors,
    hasErrors,
    setErrors,
    getFieldError,
    hasError,
    clearField,
    clearErrors,
  };
};

