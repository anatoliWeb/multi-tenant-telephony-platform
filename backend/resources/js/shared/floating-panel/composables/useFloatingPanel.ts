import { floatingPanelStore } from '../stores/floating-panel.store';
import type { FloatingPanelOptions } from '../types/floating-panel.types';

/**
 * Feature-facing composable for contextual overlays.
 *
 * Distinct from dropdowns: supports richer component content.
 * Distinct from modals/drawers: lightweight, anchored, non-disruptive UX.
 */
export const useFloatingPanel = () => {
  return {
    open: (options: FloatingPanelOptions) => floatingPanelStore.open(options),
    close: (id?: string) => floatingPanelStore.close(id),
    closeAll: () => floatingPanelStore.closeAll(),
    update: (id: string, patch: Parameters<typeof floatingPanelStore.update>[1]) =>
      floatingPanelStore.update(id, patch),
  };
};
