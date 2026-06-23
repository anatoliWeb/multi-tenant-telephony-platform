<template>
  <Teleport to="body">
    <TransitionGroup name="drawer-stack" tag="div">
      <section
        v-for="(item, index) in items"
        :key="item.id"
        class="drawer-overlay"
        :style="overlayStyle(index)"
        @click="onBackdrop(item)"
      >
        <Transition :name="transitionName(item.position)" appear>
          <div class="drawer-overlay__panel" :class="`is-${item.position}`" @click.stop>
            <BaseDrawer :item="item" @close="close(item.id)" @action="(actionIndex) => onAction(item.id, actionIndex)">
              <component
                :is="item.component"
                v-bind="item.props"
                :drawer-id="item.id"
                :close-drawer="() => close(item.id)"
              />
            </BaseDrawer>
          </div>
        </Transition>
      </section>
    </TransitionGroup>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';

import BaseDrawer from './BaseDrawer.vue';
import { drawerStore } from '../stores/drawer.store';
import type { DrawerPosition } from '../types/drawer.types';

/**
 * Global drawer overlay container.
 *
 * Drawers are contextual layers that should coexist with modals/confirms/toasts.
 * Teleport + explicit z-index tiering prevents clipping and keeps interactions
 * deterministic across stacked enterprise workflows.
 */
const items = computed(() => drawerStore.items.value);

const close = (id: string): void => {
  drawerStore.close(id);
};

const onBackdrop = (item: { id: string; closeOnBackdrop?: boolean; loading?: boolean }): void => {
  if (!item.closeOnBackdrop || item.loading) return;
  close(item.id);
};

const onAction = async (id: string, actionIndex: number): Promise<void> => {
  const item = items.value.find((entry) => entry.id === id);
  const action = item?.actions?.[actionIndex];
  if (!item || !action || item.loading || action.disabled) return;

  drawerStore.update(id, { loading: true });

  try {
    await action.onClick?.({
      id,
      close: () => close(id),
    });

    if (action.closeOnClick ?? false) {
      close(id);
    }
  } finally {
    const stillOpen = items.value.some((entry) => entry.id === id);
    if (stillOpen) {
      drawerStore.update(id, { loading: false });
    }
  }
};

const onKeydown = (event: KeyboardEvent): void => {
  const top = items.value[items.value.length - 1];
  if (!top) return;

  if (event.key === 'Escape' && top.closeOnEsc && !top.loading) {
    event.preventDefault();
    close(top.id);
  }
};

const transitionName = (position: DrawerPosition): string => {
  if (position === 'left') return 'drawer-slide-left';
  if (position === 'bottom') return 'drawer-slide-bottom';
  return 'drawer-slide-right';
};

const overlayStyle = (index: number): Record<string, string> => {
  return {
    zIndex: String(1900 + index),
  };
};

watch(
  () => items.value.length,
  (count) => {
    document.body.style.overflow = count > 0 ? 'hidden' : '';
  },
  { immediate: true },
);

onMounted(() => {
  document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown);
  document.body.style.overflow = '';
});
</script>

<style scoped>
.drawer-overlay{position:fixed;inset:0;background:rgba(2,6,23,.44);backdrop-filter:blur(2px);display:flex}
.drawer-overlay__panel{max-width:100%;max-height:100%;display:flex}
.drawer-overlay__panel.is-right{margin-left:auto}
.drawer-overlay__panel.is-left{margin-right:auto}
.drawer-overlay__panel.is-bottom{margin-top:auto;width:100%}
.drawer-stack-enter-active,.drawer-stack-leave-active{transition:opacity .2s ease}
.drawer-stack-enter-from,.drawer-stack-leave-to{opacity:0}
.drawer-slide-right-enter-active,.drawer-slide-right-leave-active,.drawer-slide-left-enter-active,.drawer-slide-left-leave-active,.drawer-slide-bottom-enter-active,.drawer-slide-bottom-leave-active{transition:transform .24s ease,opacity .24s ease}
.drawer-slide-right-enter-from,.drawer-slide-right-leave-to{transform:translateX(16px);opacity:0}
.drawer-slide-left-enter-from,.drawer-slide-left-leave-to{transform:translateX(-16px);opacity:0}
.drawer-slide-bottom-enter-from,.drawer-slide-bottom-leave-to{transform:translateY(16px);opacity:0}
</style>
