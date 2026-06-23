import { computed, onBeforeUnmount, onMounted } from 'vue';

import { commandPaletteStore } from '../stores/command-palette.store';
import { isEditableTarget } from '../utils/command-palette.utils';

/**
 * Keyboard-first entrypoint for admin productivity UX.
 *
 * Ctrl/Cmd+K is reserved for global command navigation and is ignored while
 * typing in editable controls to prevent conflict with form workflows.
 */
export const useCommandPalette = () => {
  const onHotkey = (event: KeyboardEvent): void => {
    const isMac = navigator.platform.toLowerCase().includes('mac');
    const comboPressed = isMac ? event.metaKey && event.key.toLowerCase() === 'k' : event.ctrlKey && event.key.toLowerCase() === 'k';

    if (!comboPressed) return;
    if (isEditableTarget(event.target)) return;

    event.preventDefault();
    commandPaletteStore.toggle();
  };

  onMounted(() => {
    document.addEventListener('keydown', onHotkey);
  });

  onBeforeUnmount(() => {
    document.removeEventListener('keydown', onHotkey);
  });

  return {
    isOpen: computed(() => commandPaletteStore.state.isOpen),
    query: computed(() => commandPaletteStore.state.query),
    items: computed(() => commandPaletteStore.items.value),
    activeIndex: computed(() => commandPaletteStore.state.activeIndex),
    open: () => commandPaletteStore.open(),
    close: () => commandPaletteStore.close(),
    toggle: () => commandPaletteStore.toggle(),
    setQuery: (value: string) => commandPaletteStore.setQuery(value),
    moveNext: () => commandPaletteStore.moveNext(),
    movePrev: () => commandPaletteStore.movePrev(),
    executeActive: () => commandPaletteStore.executeActive(),
  };
};
