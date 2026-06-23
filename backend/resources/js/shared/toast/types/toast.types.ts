export type ToastVariant = 'success' | 'error' | 'warning' | 'info';

export interface ToastAction {
  label: string;
  onClick?: () => void;
}

export interface ToastOptions {
  title: string;
  message?: string;
  duration?: number;
  action?: ToastAction;
}

export interface ToastItem extends ToastOptions {
  id: string;
  variant: ToastVariant;
  createdAt: number;
}
