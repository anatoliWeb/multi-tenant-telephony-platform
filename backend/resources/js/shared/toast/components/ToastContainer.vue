<template>
  <Teleport to="body">
    <section class="toast-container" aria-live="polite" aria-atomic="false">
      <TransitionGroup name="toast-stack" tag="div" class="toast-container__stack">
        <BaseToast
          v-for="toast in toasts"
          :key="toast.id"
          :toast="toast"
          @close="handleClose"
        />
      </TransitionGroup>
    </section>
  </Teleport>
</template>

<script setup lang="ts">
import { computed } from 'vue';

import BaseToast from './BaseToast.vue';
import { toastStore } from '../stores/toast.store';

/**
 * Global toast overlay layer.
 *
 * Teleport keeps notifications outside local layout overflow contexts and aligns
 * with enterprise overlay layering used by dropdowns/popovers/modals.
 */
const toasts = computed(() => toastStore.items.value);

const handleClose = (id: string): void => {
  toastStore.remove(id);
};
</script>

<style scoped>
.toast-container{position:fixed;top:16px;right:16px;z-index:2200;pointer-events:none;width:min(380px,calc(100vw - 24px))}
.toast-container__stack{display:grid;gap:8px}
.toast-container :deep(.base-toast){pointer-events:auto}
.toast-stack-enter-active,.toast-stack-leave-active{transition:all .22s ease}
.toast-stack-enter-from{opacity:0;transform:translate3d(16px,-6px,0) scale(.98)}
.toast-stack-leave-to{opacity:0;transform:translate3d(18px,-4px,0) scale(.98)}
.toast-stack-move{transition:transform .22s ease}
@media (max-width:640px){.toast-container{left:12px;right:12px;top:12px;width:auto}}
</style>
