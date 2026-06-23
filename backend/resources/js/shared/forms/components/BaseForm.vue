<template>
  <form class="base-form" :class="[`is-${layout}`]" @submit.prevent="handleSubmit">
    <slot />
  </form>
</template>

<script setup lang="ts">
import type { FormSubmitContext, FormLayout } from '../types/form.types';

interface Props<TModel extends Record<string, unknown>> {
  model: TModel;
  disabled?: boolean;
  loading?: boolean;
  layout?: FormLayout;
  reset?: () => void;
}

const props = withDefaults(defineProps<Props<Record<string, unknown>>>(), {
  disabled: false,
  loading: false,
  layout: 'vertical',
  reset: undefined,
});

const emit = defineEmits<{
  submit: [payload: FormSubmitContext<Record<string, unknown>>];
}>();

/**
 * Base form wrapper delegates field rendering via slots while centralizing
 * submit lifecycle entrypoint for page, modal, and drawer form reuse.
 */
const handleSubmit = (): void => {
  if (props.disabled || props.loading) return;

  emit('submit', {
    model: props.model,
    reset: props.reset ?? (() => undefined),
  });
};
</script>

<style scoped>
.base-form{display:grid;gap:12px}
.base-form.is-grid{grid-template-columns:repeat(2,minmax(0,1fr));column-gap:12px;row-gap:10px}
@media (max-width:860px){.base-form.is-grid{grid-template-columns:1fr}}
</style>
