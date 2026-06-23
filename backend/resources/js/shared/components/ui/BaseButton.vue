<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="buttonClass"
    class="inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
  >
    <span
      v-if="loading"
      class="h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent"
      aria-hidden="true"
    />
    <slot />
  </button>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  type?: 'button' | 'submit' | 'reset';
  variant?: 'primary' | 'secondary' | 'danger' | 'ghost';
  disabled?: boolean;
  loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  type: 'button',
  variant: 'primary',
  disabled: false,
  loading: false,
});

const buttonClass = computed(() => {
  if (props.variant === 'secondary') return 'bg-slate-100 text-slate-800 hover:bg-slate-200 focus:ring-slate-300';
  if (props.variant === 'danger') return 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-400';
  if (props.variant === 'ghost') return 'bg-transparent text-slate-700 hover:bg-slate-100 focus:ring-slate-300';
  return 'bg-slate-900 text-white hover:bg-slate-800 focus:ring-slate-500';
});
</script>

