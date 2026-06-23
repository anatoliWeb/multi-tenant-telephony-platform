import type { BackendValidationError, ValidationErrors } from '../types/validation.types';

/**
 * Centralized validation normalization layer.
 *
 * WHY:
 * Laravel returns field errors as arrays, while UI fields typically need a
 * single display-ready message. Normalizing once in shared utilities keeps form
 * components simple and avoids ad-hoc error parsing in each CRUD module.
 */
export const normalizeValidationErrors = (input: unknown): ValidationErrors => {
  if (!input || typeof input !== 'object') {
    return {};
  }

  const source =
    (input as BackendValidationError).errors && typeof (input as BackendValidationError).errors === 'object'
      ? ((input as BackendValidationError).errors as Record<string, unknown>)
      : (input as Record<string, unknown>);

  const normalized: ValidationErrors = {};

  Object.entries(source).forEach(([field, value]) => {
    if (Array.isArray(value)) {
      normalized[field] = String(value[0] ?? '').trim();
      return;
    }

    if (typeof value === 'string') {
      normalized[field] = value.trim();
      return;
    }

    normalized[field] = String(value ?? '').trim();
  });

  return normalized;
};

export const getFirstFieldError = (errors: ValidationErrors, field: string): string => {
  return errors[field] ?? '';
};

export const hasFieldError = (errors: ValidationErrors, field: string): boolean => {
  return Boolean(errors[field]);
};

export const clearFieldError = (errors: ValidationErrors, field: string): ValidationErrors => {
  const next = { ...errors };
  delete next[field];
  return next;
};

export const clearAllErrors = (): ValidationErrors => {
  return {};
};

