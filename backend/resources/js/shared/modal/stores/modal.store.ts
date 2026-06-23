import { computed, reactive } from 'vue';

import type { ModalItem, ModalOptions } from '../types/modal.types';

/**
 * Centralized modal manager.
 *
 * WHY CENTRALIZED:
 * - modal orchestration (open/close/stack) should be policy-driven, not per-page
 * - dynamic component injection enables reusable workflow modals across modules
 * - stack-ready structure prepares nested enterprise flows without rewrites
 */
const state = reactive({
  items: [] as ModalItem[],
});

const createItem = (options: ModalOptions): ModalItem => {
  return {
    id: `${Date.now()}-${Math.random().toString(16).slice(2, 10)}`,
    component: options.component,
    props: options.props ?? {},
    title: options.title ?? '',
    subtitle: options.subtitle ?? '',
    size: options.size ?? 'md',
    loading: options.loading ?? false,
    closeOnBackdrop: options.closeOnBackdrop ?? true,
    closeOnEsc: options.closeOnEsc ?? true,
    stickyHeader: options.stickyHeader ?? false,
    stickyFooter: options.stickyFooter ?? false,
    actions: options.actions ?? [],
  };
};

const closeById = (id: string): void => {
  const index = state.items.findIndex((item) => item.id === id);
  if (index >= 0) {
    state.items.splice(index, 1);
  }
};

const update = (id: string, patch: Partial<ModalItem>): void => {
  const item = state.items.find((entry) => entry.id === id);
  if (!item) return;
  Object.assign(item, patch);
};

export const modalStore = {
  items: computed(() => state.items),

  open(options: ModalOptions): string {
    const item = createItem(options);
    state.items.push(item);
    return item.id;
  },

  close(id?: string): void {
    if (id) {
      closeById(id);
      return;
    }

    const top = state.items[state.items.length - 1];
    if (!top) return;
    closeById(top.id);
  },

  closeAll(): void {
    state.items.splice(0, state.items.length);
  },

  update,
};
