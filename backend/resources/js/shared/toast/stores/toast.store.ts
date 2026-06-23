import { computed, reactive } from 'vue';

import type { ToastItem, ToastOptions, ToastVariant } from '../types/toast.types';
import { toastUtils } from '../utils/toast.utils';

/**
 * Centralized toast manager for async UX consistency.
 *
 * WHY CENTRALIZATION:
 * - async feedback (CRUD/API/realtime/jobs) must feel identical across modules
 * - decouples notification policy from feature components
 * - prepares a unified event surface for interceptors and realtime broadcasts
 */
const state = reactive({
  items: [] as ToastItem[],
});

const push = (variant: ToastVariant, options: ToastOptions): ToastItem => {
  const toast = toastUtils.createToast(variant, options);
  state.items.unshift(toast);
  return toast;
};

const remove = (id: string): void => {
  const index = state.items.findIndex((toast) => toast.id === id);
  if (index >= 0) {
    state.items.splice(index, 1);
  }
};

const clear = (): void => {
  state.items.splice(0, state.items.length);
};

export const toastStore = {
  items: computed(() => state.items),
  success: (options: ToastOptions) => push('success', options),
  error: (options: ToastOptions) => push('error', options),
  warning: (options: ToastOptions) => push('warning', options),
  info: (options: ToastOptions) => push('info', options),
  remove,
  clear,
};
