import { confirmStore } from '../stores/confirm.store';
import type { ConfirmDialogOptions } from '../types/confirm.types';

/**
 * Narrow confirm API for feature modules.
 * Usage: const accepted = await confirm.open({ ... })
 */
export const useConfirm = () => {
  return {
    open: (options: ConfirmDialogOptions) => confirmStore.open(options),
    close: () => confirmStore.close(),
    cancel: () => confirmStore.cancel(),
    confirm: () => confirmStore.confirm(),
  };
};
