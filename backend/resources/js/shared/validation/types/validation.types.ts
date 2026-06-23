export type FieldErrorMap = Record<string, string[]>;

export type ValidationErrors = Record<string, string>;

export interface ValidationState {
  errors: ValidationErrors;
  hasErrors: boolean;
}

export interface BackendValidationError {
  success?: boolean;
  message?: string;
  errors?: FieldErrorMap | ValidationErrors;
}

