import { modalStore } from '../stores/modal.store';
import type { ModalOptions } from '../types/modal.types';

/**
 * Feature-facing modal API.
 * Keeps module code focused on intent (open workflow) instead of overlay details.
 */
export const useModal = () => {
  return {
    open: (options: ModalOptions) => modalStore.open(options),
    close: (id?: string) => modalStore.close(id),
    closeAll: () => modalStore.closeAll(),
    update: (id: string, patch: Parameters<typeof modalStore.update>[1]) => modalStore.update(id, patch),
  };
};
