<template>
  <div ref="root" class="base-dropdown" :class="{ 'is-open': isOpen }">
    <div ref="trigger" class="base-dropdown__trigger" @click="toggle">
      <slot name="trigger" :is-open="isOpen" />
    </div>

    <Teleport to="body">
      <transition
        enter-active-class="base-dropdown-enter-active"
        enter-from-class="base-dropdown-enter-from"
        enter-to-class="base-dropdown-enter-to"
        leave-active-class="base-dropdown-leave-active"
        leave-from-class="base-dropdown-leave-from"
        leave-to-class="base-dropdown-leave-to"
      >
        <div
          v-if="isOpen"
          ref="menu"
          class="base-dropdown__menu"
          :class="`is-${verticalDirection}`"
          role="menu"
          :style="menuStyle"
          :data-ready="isPositionReady ? 'true' : 'false'"
        >
          <slot :close="close" />
        </div>
      </transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * Centralized floating dropdown engine.
 *
 * WHY TELEPORT/FLOATING OVERLAYS:
 * Inline dropdowns inside tables and scroll containers get clipped by overflow
 * contexts. Rendering menus in `document.body` with viewport-aware positioning
 * is the industry-standard approach for reliable enterprise admin UX.
 *
 * LAYERING STRATEGY:
 * Dropdown overlays are elevated above content shells/cards/tables, but keep a
 * z-index lower than modal dialogs so future dialog systems can stack safely.
 */
const isOpen = ref(false);
const root = ref<HTMLElement | null>(null);
const trigger = ref<HTMLElement | null>(null);
const menu = ref<HTMLElement | null>(null);

const verticalDirection = ref<'down' | 'up'>('down');
const menuStyle = ref<Record<string, string>>({});
const isPositionReady = ref(false);
let rafId: number | null = null;

const close = (): void => {
  isOpen.value = false;
};

const toggle = (): void => {
  isOpen.value = !isOpen.value;
};

const updatePosition = (): void => {
  if (!trigger.value || !menu.value) {
    return;
  }

  /**
   * Floating overlay coordinate strategy:
   * - trigger rect is measured in viewport coordinates
   * - teleported menu uses `position: fixed` (also viewport coordinates)
   * - no manual page scroll offsets are needed in fixed mode
   * - we clamp X/Y into viewport bounds to avoid clipping
   */
  const triggerRect = trigger.value.getBoundingClientRect();
  const menuWidth = menu.value.offsetWidth;
  const menuHeight = menu.value.offsetHeight;

  if (menuWidth === 0 || menuHeight === 0) {
    isPositionReady.value = false;
    return;
  }

  const viewportHeight = window.innerHeight;
  const viewportWidth = window.innerWidth;
  const gap = 8;

  const spaceBelow = viewportHeight - triggerRect.bottom;
  const spaceAbove = triggerRect.top;

  verticalDirection.value =
    spaceBelow >= menuHeight + gap || spaceBelow >= spaceAbove ? 'down' : 'up';

  const maxHeight = Math.max((verticalDirection.value === 'down' ? spaceBelow : spaceAbove) - gap - 4, 120);

  const top =
    verticalDirection.value === 'down'
      ? triggerRect.bottom + gap
      : Math.max(triggerRect.top - menuHeight - gap, gap);

  const minWidth = Math.max(triggerRect.width, 120);
  const targetWidth = Math.max(menuWidth, minWidth);
  const preferredLeft = triggerRect.right - targetWidth;
  const clampedLeft = Math.min(
    Math.max(preferredLeft, gap),
    Math.max(viewportWidth - targetWidth - gap, gap),
  );

  menuStyle.value = {
    position: 'fixed',
    top: `${Math.round(top)}px`,
    left: `${Math.round(clampedLeft)}px`,
    minWidth: `${Math.round(minWidth)}px`,
    maxHeight: `${Math.floor(maxHeight)}px`,
    overflowY: 'auto',
  };
  isPositionReady.value = true;
};

const onDocumentClick = (event: MouseEvent): void => {
  const target = event.target as Node;
  if (root.value?.contains(target) || menu.value?.contains(target)) {
    return;
  }

  close();
};

const onEscape = (event: KeyboardEvent): void => {
  if (event.key === 'Escape') {
    close();
  }
};

const onViewportChange = (): void => {
  if (!isOpen.value) {
    return;
  }

  if (rafId !== null) {
    cancelAnimationFrame(rafId);
  }

  rafId = window.requestAnimationFrame(() => {
    updatePosition();
    rafId = null;
  });
};

const prepareInitialPosition = async (): Promise<void> => {
  /**
   * Teleported overlays require a 2-phase measurement on first open:
   * 1) wait for Vue/Teleport mount cycle
   * 2) wait for browser layout/paint stabilization
   *
   * This avoids classic "first click at 0,0 / detached" behavior.
   */
  isPositionReady.value = false;
  menuStyle.value = {
    position: 'fixed',
    left: '-9999px',
    top: '-9999px',
    visibility: 'hidden',
  };

  await nextTick();

  window.requestAnimationFrame(() => {
    updatePosition();
    window.requestAnimationFrame(() => {
      updatePosition();
    });
  });
};

watch(isOpen, async (opened) => {
  if (!opened) {
    isPositionReady.value = false;
    menuStyle.value = {};
    return;
  }

  await prepareInitialPosition();
});

onMounted(() => {
  document.addEventListener('click', onDocumentClick);
  document.addEventListener('keydown', onEscape);
  window.addEventListener('resize', onViewportChange);
  window.addEventListener('scroll', onViewportChange, true);
});

onBeforeUnmount(() => {
  if (rafId !== null) {
    cancelAnimationFrame(rafId);
  }
  document.removeEventListener('click', onDocumentClick);
  document.removeEventListener('keydown', onEscape);
  window.removeEventListener('resize', onViewportChange);
  window.removeEventListener('scroll', onViewportChange, true);
});
</script>

<style scoped>
.base-dropdown {
  position: relative;
  display: inline-flex;
}

.base-dropdown__trigger {
  display: inline-flex;
}

.base-dropdown__menu {
  overflow: hidden;
  border-radius: 10px;
  border: 1px solid rgba(71, 85, 105, 0.7);
  background: rgba(15, 23, 42, 0.98);
  box-shadow: 0 14px 30px rgba(2, 6, 23, 0.55);
  z-index: 80;
  padding: 6px;
}

.base-dropdown__menu[data-ready='false'] {
  opacity: 0;
  pointer-events: none;
}

.base-dropdown-enter-active,
.base-dropdown-leave-active {
  transition: opacity 0.14s ease, transform 0.14s ease;
}

.base-dropdown-enter-from,
.base-dropdown-leave-to {
  opacity: 0;
  transform: translateY(4px);
}

.base-dropdown__menu.is-up.base-dropdown-enter-from,
.base-dropdown__menu.is-up.base-dropdown-leave-to {
  transform: translateY(-4px);
}

.base-dropdown-enter-to,
.base-dropdown-leave-from {
  opacity: 1;
  transform: translateY(0);
}
</style>
