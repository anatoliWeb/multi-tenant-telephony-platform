import type { RouteLocationRaw, Router } from 'vue-router';

export type CommandItemType = 'navigation' | 'action' | 'command' | 'recent' | 'entity';

export interface CommandPaletteItem {
  id: string;
  title: string;
  subtitle?: string;
  icon?: string;
  keywords?: string[];
  group?: string;
  type?: CommandItemType;
  action: () => void | Promise<void>;
}

export interface CommandPaletteProviderContext {
  router: Router;
}

export type CommandPaletteProvider = (ctx: CommandPaletteProviderContext) => CommandPaletteItem[];

export interface CommandPaletteState {
  isOpen: boolean;
  query: string;
  loading: boolean;
  items: CommandPaletteItem[];
  activeIndex: number;
}

export interface RegisterNavigationCommandInput {
  id: string;
  title: string;
  subtitle?: string;
  icon?: string;
  keywords?: string[];
  group?: string;
  to: RouteLocationRaw;
}
