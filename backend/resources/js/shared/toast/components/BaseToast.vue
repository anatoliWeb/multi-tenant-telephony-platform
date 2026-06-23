<template>
  <article
    class="base-toast"
    :class="[`is-${toast.variant}`]"
    @mouseenter="pauseTimer"
    @mouseleave="resumeTimer"
  >
    <div class="base-toast__icon" aria-hidden="true">{{ icon }}</div>

    <div class="base-toast__content">
      <h4 class="base-toast__title">{{ toast.title }}</h4>
      <p v-if="toast.message" class="base-toast__message">{{ toast.message }}</p>

      <button
        v-if="toast.action"
        type="button"
        class="base-toast__action"
        @click="handleAction"
      >
        {{ toast.action.label }}
      </button>
    </div>

    <button type="button" class="base-toast__close" aria-label="Close notification" @click="emit('close', toast.id)">
      ?
    </button>
  </article>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import type { ToastItem } from '../types/toast.types';

/**
 * Toast item renderer for async feedback.
 *
 * Timer lifecycle is local to each toast so hover pause and cleanup are isolated
 * and predictable, which is critical for high-frequency async event bursts.
 */
const props = defineProps<{
  toast: ToastItem;
}>();

const emit = defineEmits<{
  close: [id: string];
}>();

const timer = ref<ReturnType<typeof setTimeout> | null>(null);
const remaining = ref(props.toast.duration ?? 0);
const startedAt = ref(0);

const icon = computed(() => {
  switch (props.toast.variant) {
    case 'success':
      return '?';
    case 'error':
      return '!';
    case 'warning':
      return '?';
    default:
      return 'i';
  }
});

const clearTimer = (): void => {
  if (timer.value) {
    clearTimeout(timer.value);
    timer.value = null;
  }
};

const startTimer = (): void => {
  if (!remaining.value || remaining.value <= 0) return;
  startedAt.value = Date.now();
  timer.value = setTimeout(() => emit('close', props.toast.id), remaining.value);
};

const pauseTimer = (): void => {
  if (!timer.value) return;
  clearTimer();
  const elapsed = Date.now() - startedAt.value;
  remaining.value = Math.max(0, remaining.value - elapsed);
};

const resumeTimer = (): void => {
  if (remaining.value <= 0) {
    emit('close', props.toast.id);
    return;
  }
  startTimer();
};

const handleAction = (): void => {
  props.toast.action?.onClick?.();
  emit('close', props.toast.id);
};

onMounted(() => {
  startTimer();
});

onBeforeUnmount(() => {
  clearTimer();
});
</script>

<style scoped>
.base-toast{display:grid;grid-template-columns:auto 1fr auto;gap:10px;align-items:flex-start;padding:11px 12px;border:1px solid rgba(71,85,105,.55);border-radius:12px;background:rgba(15,23,42,.96);box-shadow:0 14px 30px rgba(2,6,23,.55);color:#e2e8f0;min-width:280px;max-width:360px}
.base-toast__icon{width:24px;height:24px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;background:rgba(100,116,139,.25);color:#dbeafe}
.base-toast__content{min-width:0}
.base-toast__title{margin:0;font-size:13px;font-weight:700;color:#f8fafc}
.base-toast__message{margin:4px 0 0;font-size:12px;line-height:1.4;color:#cbd5e1}
.base-toast__action{margin-top:8px;height:28px;border-radius:8px;border:1px solid rgba(71,85,105,.55);background:rgba(51,65,85,.75);color:#e2e8f0;font-size:12px;padding:0 10px}
.base-toast__close{border:0;background:transparent;color:#94a3b8;font-size:18px;line-height:1;padding:0 4px}
.base-toast__close:hover{color:#e2e8f0}
.base-toast.is-success .base-toast__icon{background:rgba(34,197,94,.2);color:#86efac}
.base-toast.is-error .base-toast__icon{background:rgba(239,68,68,.22);color:#fca5a5}
.base-toast.is-warning .base-toast__icon{background:rgba(245,158,11,.22);color:#fcd34d}
.base-toast.is-info .base-toast__icon{background:rgba(59,130,246,.22);color:#bfdbfe}
@media (max-width:640px){.base-toast{min-width:0;max-width:none;width:100%}}
</style>
