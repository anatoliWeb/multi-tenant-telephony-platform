export { useOptimisticAction } from './composables/useOptimisticAction';
export {
  insertAtTop,
  optimisticCreate,
  optimisticDelete,
  optimisticUpdate,
  removeById,
  replaceById,
  toOptimisticError,
} from './utils/optimistic.utils';
export type {
  OptimisticOperation,
  OptimisticRunOptions,
  OptimisticStatus,
  UseOptimisticActionResult,
} from './types/optimistic.types';
