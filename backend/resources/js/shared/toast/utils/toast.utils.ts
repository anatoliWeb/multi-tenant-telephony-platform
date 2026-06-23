import type { ToastItem, ToastOptions, ToastVariant } from '../types/toast.types';

const DEFAULT_DURATION = 4500;

export const toastUtils = {
  createToast(variant: ToastVariant, options: ToastOptions): ToastItem {
    return {
      id: `${Date.now()}-${Math.random().toString(16).slice(2, 10)}`,
      variant,
      createdAt: Date.now(),
      duration: options.duration ?? DEFAULT_DURATION,
      title: options.title,
      message: options.message,
      action: options.action,
    };
  },
};
