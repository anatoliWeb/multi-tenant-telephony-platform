<template>
  <div class="base-form-field" :class="{ 'has-error': !!displayError }">
    <header class="base-form-field__head">
      <label v-if="label" class="base-form-field__label">
        <span>{{ label }}</span>
        <span v-if="required" class="base-form-field__required">*</span>
      </label>
      <p v-if="description" class="base-form-field__description">{{ description }}</p>
    </header>

    <div class="base-form-field__control">
      <slot />
    </div>

    <p v-if="displayError" class="base-form-field__error">{{ displayError }}</p>
    <p v-else-if="help" class="base-form-field__help">{{ help }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  label?: string;
  description?: string;
  help?: string;
  error?: string | string[];
  required?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  label: '',
  description: '',
  help: '',
  error: '',
  required: false,
});

const displayError = computed(() => {
  if (Array.isArray(props.error)) {
    return String(props.error[0] ?? '').trim();
  }

  return String(props.error ?? '').trim();
});
</script>

<style scoped>
.base-form-field{display:grid;gap:7px;min-width:0}
.base-form-field__head{display:grid;gap:4px}
.base-form-field__label{display:inline-flex;align-items:center;gap:4px;color:#e2e8f0;font-size:12px;font-weight:600}
.base-form-field__required{color:#fca5a5}
.base-form-field__description{margin:0;color:#94a3b8;font-size:11px;line-height:1.4}
.base-form-field__control{min-width:0}
.base-form-field__control :deep(input),.base-form-field__control :deep(select),.base-form-field__control :deep(textarea){box-sizing:border-box;display:block;min-width:0;width:100%;max-width:100%;height:38px;border-radius:10px;border:1px solid rgba(71,85,105,.55);background:rgba(15,23,42,.75);color:#e2e8f0;padding:0 12px;font-size:12px;outline:none;transition:border-color .2s ease, box-shadow .2s ease}
.base-form-field__control :deep(input:focus),.base-form-field__control :deep(select:focus),.base-form-field__control :deep(textarea:focus){border-color:rgba(96,165,250,.55);box-shadow:0 0 0 3px rgba(59,130,246,.15)}
.base-form-field__control :deep(textarea){height:auto;min-height:94px;padding:10px}
.base-form-field__error{margin:0;color:#fca5a5;font-size:11px}
.base-form-field__help{margin:0;color:#94a3b8;font-size:11px}
.base-form-field.has-error .base-form-field__control :deep(input),.base-form-field.has-error .base-form-field__control :deep(select),.base-form-field.has-error .base-form-field__control :deep(textarea){border-color:rgba(239,68,68,.55)}
</style>
