import { drawerStore } from '../stores/drawer.store';
import type { DrawerOptions } from '../types/drawer.types';

/**
 * Feature-facing drawer API.
 * Modules request contextual side panels without owning overlay mechanics.
 */
export const useDrawer = () => {
  return {
    open: (options: DrawerOptions) => drawerStore.open(options),
    close: (id?: string) => drawerStore.close(id),
    closeAll: () => drawerStore.closeAll(),
    update: (id: string, patch: Parameters<typeof drawerStore.update>[1]) => drawerStore.update(id, patch),
  };
};
