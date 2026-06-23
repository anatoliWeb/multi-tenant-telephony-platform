import { computed, reactive } from 'vue';
import type { Router } from 'vue-router';

import type {
  CommandPaletteItem,
  CommandPaletteProvider,
  CommandPaletteState,
  RegisterNavigationCommandInput,
} from '../types/command-palette.types';
import { matchCommandItem, normalizeQuery } from '../utils/command-palette.utils';

/**
 * Provider-based command palette state.
 *
 * WHY PROVIDERS:
 * - keeps command registration modular per feature/domain
 * - enables future dynamic sources (API search, AI suggestions, realtime hints)
 * - avoids hardcoding command sets into one monolithic component
 */
const state = reactive<CommandPaletteState>({
  isOpen: false,
  query: '',
  loading: false,
  items: [],
  activeIndex: 0,
});

const providers: CommandPaletteProvider[] = [];
let routerRef: Router | null = null;

const collectItems = (): CommandPaletteItem[] => {
  if (!routerRef) return state.items;

  return providers.flatMap((provider) => provider({ router: routerRef as Router }));
};

const recalculateItems = (): void => {
  const all = collectItems();
  const query = normalizeQuery(state.query);
  state.items = all.filter((item) => matchCommandItem(item, query));

  if (state.activeIndex >= state.items.length) {
    state.activeIndex = Math.max(0, state.items.length - 1);
  }
};

export const commandPaletteStore = {
  state,

  init(router: Router): void {
    routerRef = router;
    recalculateItems();
  },

  open(): void {
    state.isOpen = true;
    recalculateItems();
  },

  close(): void {
    state.isOpen = false;
    state.query = '';
    state.activeIndex = 0;
  },

  toggle(): void {
    if (state.isOpen) {
      this.close();
      return;
    }

    this.open();
  },

  setItems(items: CommandPaletteItem[]): void {
    state.items = items;
    state.activeIndex = 0;
  },

  setQuery(value: string): void {
    state.query = value;
    state.activeIndex = 0;
    recalculateItems();
  },

  moveNext(): void {
    if (!state.items.length) return;
    state.activeIndex = (state.activeIndex + 1) % state.items.length;
  },

  movePrev(): void {
    if (!state.items.length) return;
    state.activeIndex = (state.activeIndex - 1 + state.items.length) % state.items.length;
  },

  async executeActive(): Promise<void> {
    const item = state.items[state.activeIndex];
    if (!item) return;
    await item.action();
    this.close();
  },

  registerProvider(provider: CommandPaletteProvider): void {
    providers.push(provider);
    recalculateItems();
  },

  registerNavigation(input: RegisterNavigationCommandInput): void {
    this.registerProvider(({ router }) => [
      {
        id: input.id,
        title: input.title,
        subtitle: input.subtitle,
        icon: input.icon,
        keywords: input.keywords,
        group: input.group ?? 'Navigation',
        type: 'navigation',
        action: () => router.push(input.to),
      },
    ]);
  },

  clearProviders(): void {
    providers.splice(0, providers.length);
    recalculateItems();
  },

  items: computed(() => state.items),
  groupedItems: computed(() => state.items),
};
