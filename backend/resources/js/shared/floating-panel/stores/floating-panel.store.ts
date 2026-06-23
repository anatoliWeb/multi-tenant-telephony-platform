import { computed, reactive } from 'vue';

import type { FloatingPanelItem, FloatingPanelOptions } from '../types/floating-panel.types';

/**
 * Global floating-panel manager.
 *
 * Centralizing lightweight contextual panels keeps preview/inspector behavior
 * consistent and avoids each module implementing custom overlay logic.
 */
const state = reactive({
  items: [] as FloatingPanelItem[],
});

const createItem = (options: FloatingPanelOptions): FloatingPanelItem => ({
  id: `${Date.now()}-${Math.random().toString(16).slice(2, 10)}`,
  component: options.component,
  props: options.props ?? {},
  title: options.title ?? '',
  subtitle: options.subtitle ?? '',
  size: options.size ?? 'md',
  loading: options.loading ?? false,
  empty: options.empty ?? false,
  emptyText: options.emptyText ?? 'No data available.',
  showHeader: options.showHeader ?? true,
  showFooter: options.showFooter ?? false,
  closable: options.closable ?? true,
  persistent: options.persistent ?? false,
  trigger: options.trigger ?? null,
  placement: options.placement ?? 'bottom-end',
  offset: options.offset ?? 8,
  position: options.position,
});

const closeById = (id: string): void => {
  const index = state.items.findIndex((item) => item.id === id);
  if (index >= 0) {
    state.items.splice(index, 1);
  }
};

export const floatingPanelStore = {
  items: computed(() => state.items),

  open(options: FloatingPanelOptions): string {
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
    if (top) {
      closeById(top.id);
    }
  },

  closeAll(): void {
    state.items.splice(0, state.items.length);
  },

  update(id: string, patch: Partial<FloatingPanelItem>): void {
    const item = state.items.find((entry) => entry.id === id);
    if (!item) return;
    Object.assign(item, patch);
  },
};
