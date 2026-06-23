<template>
  <Teleport to="body">
    <Transition name="confirm-fade">
      <section
        v-if="state.isOpen"
        class="confirm-overlay"
        role="presentation"
        @click="onBackdrop"
      >
        <Transition name="confirm-scale">
          <div v-if="state.isOpen" class="confirm-overlay__dialog" @click.stop>
            <BaseConfirmDialog :state="state" @confirm="onConfirm" @cancel="onCancel" />
          </div>
        </Transition>
      </section>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue';

import BaseConfirmDialog from './BaseConfirmDialog.vue';
import { confirmStore } from '../stores/confirm.store';

/**
 * Global confirm overlay container.
 *
 * Layering strategy keeps confirmation dialogs above dropdown/popover overlays
 * while leaving room for future fullscreen modal layers when required.
 */
const state = confirmStore.state;

const onConfirm = async (): Promise<void> => {
  await confirmStore.confirm();
};

const onCancel = (): void => {
  confirmStore.cancel();
};

const onBackdrop = (): void => {
  if (state.closeOnBackdrop && !state.loading) {
    confirmStore.close();
  }
};

const onKeydown = (event: KeyboardEvent): void => {
  if (!state.isOpen) return;

  if (event.key === 'Escape' && state.closeOnEsc && !state.loading) {
    event.preventDefault();
    confirmStore.close();
  }

  if (event.key === 'Enter' && !state.loading) {
    event.preventDefault();
    void confirmStore.confirm();
  }
};

onMounted(() => {
  document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown);
});
</script>

<style scoped>
.confirm-overlay{position:fixed;inset:0;z-index:2100;background:rgba(2,6,23,.56);backdrop-filter:blur(3px);display:flex;align-items:center;justify-content:center;padding:12px}
.confirm-overlay__dialog{max-width:100%;}
.confirm-fade-enter-active,.confirm-fade-leave-active{transition:opacity .18s ease}
.confirm-fade-enter-from,.confirm-fade-leave-to{opacity:0}
.confirm-scale-enter-active,.confirm-scale-leave-active{transition:transform .2s ease,opacity .2s ease}
.confirm-scale-enter-from,.confirm-scale-leave-to{opacity:0;transform:translateY(6px) scale(.985)}
</style>
