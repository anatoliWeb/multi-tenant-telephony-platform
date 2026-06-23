import { toastStore } from '../stores/toast.store';
import type { ToastOptions } from '../types/toast.types';

/**
 * useToast provides a narrow interface for feature modules.
 * Components remain unaware of rendering/stacking details and only express intent.
 */
export const useToast = () => {
  return {
    success: (options: ToastOptions) => toastStore.success(options),
    error: (options: ToastOptions) => toastStore.error(options),
    warning: (options: ToastOptions) => toastStore.warning(options),
    info: (options: ToastOptions) => toastStore.info(options),
    remove: (id: string) => toastStore.remove(id),
    clear: () => toastStore.clear(),
  };
};
