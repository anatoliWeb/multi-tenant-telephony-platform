export type ConfirmVariant = 'danger' | 'warning' | 'info';

export interface ConfirmDialogOptions {
  title: string;
  message: string;
  confirmLabel?: string;
  cancelLabel?: string;
  variant?: ConfirmVariant;
  destructive?: boolean;
  closeOnBackdrop?: boolean;
  closeOnEsc?: boolean;
  icon?: string;
  onConfirm?: () => Promise<void> | void;
}

export interface ConfirmDialogState extends ConfirmDialogOptions {
  isOpen: boolean;
  loading: boolean;
}
