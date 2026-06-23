import type { Ref } from 'vue';

export type OptimisticStatus = 'pending' | 'committed' | 'failed' | 'rolled_back';

export interface OptimisticOperation<TResponse = unknown> {
  id: string;
  key?: string;
  status: OptimisticStatus;
  startedAt: number;
  finishedAt?: number;
  error?: string;
  response?: TResponse;
}

export interface OptimisticRunOptions<TResponse = unknown> {
  key?: string;
  apply: () => void;
  action: () => Promise<TResponse>;
  rollback: () => void;
  commit?: (response: TResponse) => void;
  onSuccess?: (response: TResponse) => void;
  onError?: (error: unknown) => void;
  allowParallelByKey?: boolean;
}

export interface UseOptimisticActionResult {
  operations: Ref<OptimisticOperation[]>;
  pendingOperations: Ref<OptimisticOperation[]>;
  run: <TResponse = unknown>(options: OptimisticRunOptions<TResponse>) => Promise<TResponse | null>;
  isPendingByKey: (key: string) => boolean;
  clearFinished: () => void;
}
