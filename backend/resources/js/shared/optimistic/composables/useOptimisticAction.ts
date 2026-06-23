import { computed, ref } from 'vue';

import type { OptimisticOperation, OptimisticRunOptions, UseOptimisticActionResult } from '../types/optimistic.types';
import { toOptimisticError } from '../utils/optimistic.utils';

/**
 * Reusable optimistic action runner.
 *
 * STRATEGY:
 * - apply optimistic mutation immediately for responsive UX
 * - execute async action
 * - commit on success, explicit rollback on failure
 * - track operation metadata for visibility and future reconciliation
 *
 * WHY EXPLICIT ROLLBACK:
 * Hidden/magic rollback mutates state unpredictably. Explicit rollback keeps
 * failure recovery deterministic and auditable in enterprise admin workflows.
 */
export const useOptimisticAction = (): UseOptimisticActionResult => {
  const operations = ref<OptimisticOperation[]>([]);

  const pendingOperations = computed(() => operations.value.filter((op) => op.status === 'pending'));

  const isPendingByKey = (key: string): boolean => {
    return operations.value.some((op) => op.status === 'pending' && op.key === key);
  };

  const run = async <TResponse = unknown>(options: OptimisticRunOptions<TResponse>): Promise<TResponse | null> => {
    const key = options.key;

    if (key && options.allowParallelByKey !== true && isPendingByKey(key)) {
      return null;
    }

    const operation: OptimisticOperation<TResponse> = {
      id: `${Date.now()}-${Math.random().toString(16).slice(2, 10)}`,
      key,
      status: 'pending',
      startedAt: Date.now(),
    };

    operations.value.unshift(operation as OptimisticOperation);

    options.apply();

    try {
      const response = await options.action();

      options.commit?.(response);
      options.onSuccess?.(response);

      operation.status = 'committed';
      operation.response = response;
      operation.finishedAt = Date.now();

      return response;
    } catch (error) {
      options.rollback();
      options.onError?.(error);

      operation.status = 'failed';
      operation.error = toOptimisticError(error);
      operation.finishedAt = Date.now();

      const rollbackRecord: OptimisticOperation = {
        id: `${operation.id}-rollback`,
        key,
        status: 'rolled_back',
        startedAt: operation.finishedAt,
        finishedAt: Date.now(),
      };

      operations.value.unshift(rollbackRecord);

      return null;
    }
  };

  const clearFinished = (): void => {
    operations.value = operations.value.filter((op) => op.status === 'pending');
  };

  return {
    operations,
    pendingOperations,
    run,
    isPendingByKey,
    clearFinished,
  };
};
