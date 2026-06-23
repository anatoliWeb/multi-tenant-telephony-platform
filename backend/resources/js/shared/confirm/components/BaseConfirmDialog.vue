<template>
  <article class="confirm-dialog" :class="[`is-${state.variant}`, { 'is-loading': state.loading }]" role="dialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-message">
    <header class="confirm-dialog__header">
      <span class="confirm-dialog__icon" aria-hidden="true">{{ displayIcon }}</span>
      <div>
        <h3 id="confirm-title" class="confirm-dialog__title">{{ state.title }}</h3>
        <p id="confirm-message" class="confirm-dialog__message">{{ state.message }}</p>
      </div>
    </header>

    <footer class="confirm-dialog__actions">
      <button type="button" class="confirm-dialog__btn" :disabled="state.loading" @click="emit('cancel')">
        {{ state.cancelLabel }}
      </button>
      <button
        type="button"
        class="confirm-dialog__btn confirm-dialog__btn--confirm"
        :class="{ 'is-destructive': state.destructive }"
        :disabled="state.loading"
        @click="emit('confirm')"
      >
        <span v-if="state.loading" class="confirm-dialog__spinner" aria-hidden="true" />
        <span>{{ state.loading ? 'Processing...' : state.confirmLabel }}</span>
      </button>
    </footer>
  </article>
</template>

<script setup lang="ts">
import { computed } from 'vue';

import type { ConfirmDialogState } from '../types/confirm.types';

const props = defineProps<{
  state: ConfirmDialogState;
}>();

const emit = defineEmits<{
  confirm: [];
  cancel: [];
}>();

const displayIcon = computed(() => {
  if (props.state.icon) return props.state.icon;
  if (props.state.variant === 'danger') return '!';
  if (props.state.variant === 'warning') return '?';
  return 'i';
});
</script>

<style scoped>
.confirm-dialog{width:min(520px,calc(100vw - 24px));border:1px solid rgba(71,85,105,.6);border-radius:14px;background:rgba(15,23,42,.98);box-shadow:0 24px 50px rgba(2,6,23,.6);padding:14px;display:grid;gap:14px}
.confirm-dialog__header{display:grid;grid-template-columns:auto 1fr;gap:10px;align-items:flex-start}
.confirm-dialog__icon{width:28px;height:28px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;background:rgba(100,116,139,.22);color:#dbeafe;font-weight:700}
.confirm-dialog__title{margin:0;color:#f8fafc;font-size:16px}
.confirm-dialog__message{margin:6px 0 0;color:#cbd5e1;font-size:13px;line-height:1.45}
.confirm-dialog__actions{display:flex;justify-content:flex-end;gap:8px}
.confirm-dialog__btn{height:34px;border-radius:9px;border:1px solid rgba(71,85,105,.55);background:rgba(30,41,59,.8);color:#e2e8f0;padding:0 12px;font-size:12px;display:inline-flex;align-items:center;gap:6px}
.confirm-dialog__btn--confirm{border-color:rgba(59,130,246,.55);background:rgba(59,130,246,.2);color:#bfdbfe}
.confirm-dialog__btn--confirm.is-destructive{border-color:rgba(239,68,68,.55);background:rgba(239,68,68,.2);color:#fecaca}
.confirm-dialog__btn:disabled{opacity:.6;cursor:not-allowed}
.confirm-dialog__spinner{width:12px;height:12px;border-radius:999px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;animation:spin .9s linear infinite}
.confirm-dialog.is-danger .confirm-dialog__icon{background:rgba(239,68,68,.22);color:#fca5a5}
.confirm-dialog.is-warning .confirm-dialog__icon{background:rgba(245,158,11,.22);color:#fcd34d}
.confirm-dialog.is-info .confirm-dialog__icon{background:rgba(59,130,246,.22);color:#bfdbfe}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
