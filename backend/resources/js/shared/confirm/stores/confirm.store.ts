import { reactive } from 'vue';

import type { ConfirmDialogOptions, ConfirmDialogState } from '../types/confirm.types';

/**
 * Promise-based confirm store.
 *
 * WHY THIS ARCHITECTURE:
 * - feature modules can await a user decision without coupling to dialog UI
 * - async-safe confirm handling prevents duplicated destructive requests
 * - centralized policy keeps interaction behavior consistent across the app
 */
const defaultState: ConfirmDialogState = {
  isOpen: false,
  loading: false,
  title: '',
  message: '',
  confirmLabel: 'Confirm',
  cancelLabel: 'Cancel',
  variant: 'info',
  destructive: false,
  closeOnBackdrop: true,
  closeOnEsc: true,
  icon: '',
};

const state = reactive<ConfirmDialogState>({ ...defaultState });

let resolver: ((accepted: boolean) => void) | null = null;

const applyOptions = (options: ConfirmDialogOptions): void => {
  state.title = options.title;
  state.message = options.message;
  state.confirmLabel = options.confirmLabel ?? 'Confirm';
  state.cancelLabel = options.cancelLabel ?? 'Cancel';
  state.variant = options.variant ?? 'info';
  state.destructive = options.destructive ?? options.variant === 'danger';
  state.closeOnBackdrop = options.closeOnBackdrop ?? true;
  state.closeOnEsc = options.closeOnEsc ?? true;
  state.icon = options.icon ?? '';
  state.loading = false;
};

const closeInternal = (): void => {
  state.isOpen = false;
  state.loading = false;
};

const resolvePending = (accepted: boolean): void => {
  if (resolver) {
    resolver(accepted);
    resolver = null;
  }
};

export const confirmStore = {
  state,

  open(options: ConfirmDialogOptions): Promise<boolean> {
    if (resolver) {
      resolvePending(false);
    }

    applyOptions(options);
    state.isOpen = true;

    return new Promise<boolean>((resolve) => {
      resolver = resolve;
    });
  },

  async confirm(): Promise<void> {
    if (!state.isOpen || state.loading) return;

    state.loading = true;

    try {
      await Promise.resolve(undefined);
      closeInternal();
      resolvePending(true);
    } catch {
      state.loading = false;
    }
  },

  cancel(): void {
    if (!state.isOpen || state.loading) return;
    closeInternal();
    resolvePending(false);
  },

  close(): void {
    if (!state.isOpen || state.loading) return;
    closeInternal();
    resolvePending(false);
  },
};
