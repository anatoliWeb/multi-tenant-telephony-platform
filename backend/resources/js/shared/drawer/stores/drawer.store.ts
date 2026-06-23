import { computed, reactive } from 'vue';

import type { DrawerItem, DrawerOptions } from '../types/drawer.types';

/**
 * Centralized drawer manager.
 *
 * Drawers provide contextual side workflows that preserve page context better
 * than full modal interruptions. This manager keeps stack, lifecycle, and
 * close/update policy centralized for consistent enterprise behavior.
 */
const state = reactive({
  items: [] as DrawerItem[],
});

const createItem = (options: DrawerOptions): DrawerItem => {
  return {
    id: `${Date.now()}-${Math.random().toString(16).slice(2, 10)}`,
    component: options.component,
    props: options.props ?? {},
    title: options.title ?? '',
    subtitle: options.subtitle ?? '',
    size: options.size ?? 'md',
    position: options.position ?? 'right',
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

const update = (id: string, patch: Partial<DrawerItem>): void => {
  const item = state.items.find((entry) => entry.id === id);
  if (!item) return;
  Object.assign(item, patch);
};

export const drawerStore = {
  items: computed(() => state.items),

  open(options: DrawerOptions): string {
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
