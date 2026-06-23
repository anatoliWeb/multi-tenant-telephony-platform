/**
 * Generic list utilities for optimistic CRUD updates.
 *
 * These helpers avoid module-specific array mutation logic so optimistic flows
 * remain predictable across users/roles/permissions/tokens and future modules.
 */
export const replaceById = <T extends Record<string, unknown>>(list: T[], id: string | number, nextItem: T): T[] => {
  return list.map((item) => (item.id === id ? nextItem : item));
};

export const removeById = <T extends Record<string, unknown>>(list: T[], id: string | number): T[] => {
  return list.filter((item) => item.id !== id);
};

export const insertAtTop = <T>(list: T[], item: T): T[] => {
  return [item, ...list];
};

export const optimisticCreate = <T>(list: T[], item: T): T[] => {
  return insertAtTop(list, item);
};

export const optimisticUpdate = <T extends Record<string, unknown>>(
  list: T[],
  id: string | number,
  patch: Partial<T>,
): T[] => {
  return list.map((item) => (item.id === id ? ({ ...item, ...patch } as T) : item));
};

export const optimisticDelete = <T extends Record<string, unknown>>(list: T[], id: string | number): T[] => {
  return removeById(list, id);
};

export const toOptimisticError = (error: unknown): string => {
  if (typeof error === 'string') return error;
  if (error && typeof error === 'object' && 'message' in error) {
    return String((error as { message?: unknown }).message ?? 'Operation failed');
  }
  return 'Operation failed';
};
