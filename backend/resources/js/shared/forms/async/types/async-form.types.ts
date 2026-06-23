import type { ComputedRef, Ref } from 'vue';

export type AsyncFormStatus = 'idle' | 'submitting' | 'success' | 'error';

export interface AsyncFormState {
  status: AsyncFormStatus;
  lastError: string | null;
  lastSuccess: string | null;
  submitCount: number;
  retryCount: number;
}

export interface AsyncSubmitOptions {
  successMessage?: string;
  errorMessage?: string;
}

export interface UseAsyncFormResult {
  status: Ref<AsyncFormStatus>;
  isSubmitting: ComputedRef<boolean>;
  isSuccess: ComputedRef<boolean>;
  isError: ComputedRef<boolean>;
  canRetry: ComputedRef<boolean>;
  lastError: Ref<string | null>;
  lastSuccess: Ref<string | null>;
  submitCount: Ref<number>;
  retryCount: Ref<number>;
  submit: <T = void>(runner: () => Promise<T> | T, options?: AsyncSubmitOptions) => Promise<T | null>;
  retry: <T = void>(options?: AsyncSubmitOptions) => Promise<T | null>;
  resetState: () => void;
  markIdle: () => void;
}
