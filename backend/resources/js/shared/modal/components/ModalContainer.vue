<template>
  <Teleport to="body">
    <TransitionGroup name="modal-stack" tag="div">
      <section
        v-for="(item, index) in items"
        :key="item.id"
        class="modal-overlay"
        :style="overlayStyle(index)"
        @click="onBackdrop(item)"
      >
        <Transition name="modal-scale" appear>
          <div class="modal-overlay__dialog" @click.stop>
            <BaseModal :item="item" @close="close(item.id)" @action="(actionIndex) => onAction(item.id, actionIndex)">
              <component
                :is="item.component"
                v-bind="item.props"
                :modal-id="item.id"
                :close-modal="() => close(item.id)"
              />
            </BaseModal>
          </div>
        </Transition>
      </section>
    </TransitionGroup>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';

import BaseModal from './BaseModal.vue';
import { modalStore } from '../stores/modal.store';

/**
 * Global modal overlay container.
 *
 * - Teleport avoids layout clipping and keeps overlays shell-independent.
 * - Stack indexing is explicit so nested workflows can be layered predictably.
 * - Scroll locking protects background context during focused modal interaction.
 */
const items = computed(() => modalStore.items.value);

const close = (id: string): void => {
  modalStore.close(id);
};

const onBackdrop = (item: { id: string; closeOnBackdrop?: boolean; loading?: boolean }): void => {
  if (!item.closeOnBackdrop || item.loading) return;
  close(item.id);
};

const onAction = async (id: string, actionIndex: number): Promise<void> => {
  const item = items.value.find((entry) => entry.id === id);
  const action = item?.actions?.[actionIndex];
  if (!item || !action || item.loading || action.disabled) return;

  modalStore.update(id, { loading: true });

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
      modalStore.update(id, { loading: false });
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

const overlayStyle = (index: number): Record<string, string> => {
  return {
    zIndex: String(2000 + index),
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
.modal-overlay{position:fixed;inset:0;background:rgba(2,6,23,.56);backdrop-filter:blur(3px);display:flex;align-items:center;justify-content:center;padding:8px}
.modal-overlay__dialog{max-width:100%;max-height:100%;}
.modal-stack-enter-active,.modal-stack-leave-active{transition:opacity .2s ease}
.modal-stack-enter-from,.modal-stack-leave-to{opacity:0}
.modal-scale-enter-active,.modal-scale-leave-active{transition:transform .2s ease, opacity .2s ease}
.modal-scale-enter-from,.modal-scale-leave-to{opacity:0;transform:translateY(8px) scale(.985)}
</style>
