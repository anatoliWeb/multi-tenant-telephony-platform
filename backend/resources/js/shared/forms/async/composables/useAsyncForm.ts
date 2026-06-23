import { computed, ref } from 'vue';

import type { AsyncFormStatus, AsyncSubmitOptions, UseAsyncFormResult } from '../types/async-form.types';
import { toErrorMessage } from '../utils/async-form.utils';

/**
 * Async form lifecycle manager.
 *
 * DESIGN GOALS:
 * - block duplicate submits to avoid race conditions/double writes
 * - keep retry semantics explicit and deterministic
 * - isolate async lifecycle from view layer so it can be reused in pages,
 *   modals, and drawers with identical UX behavior
 *
 * FUTURE PREPARATION:
 * - stores last submit runner for safe retry
 * - ready for AbortController/cancel-token plumbing without API changes
 * - compatible with toast/optimistic/autosave policies at composition layer
 */
export const useAsyncForm = (): UseAsyncFormResult => {
  const status = ref<AsyncFormStatus>('idle');
  const lastError = ref<string | null>(null);
  const lastSuccess = ref<string | null>(null);
  const submitCount = ref(0);
  const retryCount = ref(0);

  const lastRunner = ref<(() => Promise<unknown>) | null>(null);

  const isSubmitting = computed(() => status.value === 'submitting');
  const isSuccess = computed(() => status.value === 'success');
  const isError = computed(() => status.value === 'error');
  const canRetry = computed(() => isError.value && Boolean(lastRunner.value));

  const markIdle = (): void => {
    status.value = 'idle';
  };

  const resetState = (): void => {
    status.value = 'idle';
    lastError.value = null;
    lastSuccess.value = null;
    retryCount.value = 0;
  };

  const runSubmit = async <T>(
    runner: () => Promise<T> | T,
    options?: AsyncSubmitOptions,
    isRetry = false,
  ): Promise<T | null> => {
    if (isSubmitting.value) {
      return null;
    }

    status.value = 'submitting';
    lastError.value = null;

    if (!isRetry) {
      submitCount.value += 1;
    } else {
      retryCount.value += 1;
    }

    const wrappedRunner = async (): Promise<T> => Promise.resolve(runner());
    lastRunner.value = wrappedRunner as () => Promise<unknown>;

    try {
      const result = await wrappedRunner();
      status.value = 'success';
      lastSuccess.value = options?.successMessage ?? null;
      return result;
    } catch (error) {
      status.value = 'error';
      lastSuccess.value = null;
      lastError.value = options?.errorMessage ?? toErrorMessage(error);
      return null;
    }
  };

  const submit = async <T>(runner: () => Promise<T> | T, options?: AsyncSubmitOptions): Promise<T | null> => {
    return runSubmit(runner, options, false);
  };

  const retry = async <T>(options?: AsyncSubmitOptions): Promise<T | null> => {
    if (!lastRunner.value) {
      return null;
    }

    return runSubmit(() => lastRunner.value?.() as Promise<T>, options, true);
  };

  return {
    status,
    isSubmitting,
    isSuccess,
    isError,
    canRetry,
    lastError,
    lastSuccess,
    submitCount,
    retryCount,
    submit,
    retry,
    resetState,
    markIdle,
  };
};
