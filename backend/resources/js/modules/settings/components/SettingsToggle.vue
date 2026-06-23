<template>
  <button
    type="button"
    class="settings-toggle"
    :class="{ 'is-enabled': modelValue, 'is-disabled': disabled }"
    :disabled="disabled"
    @click="$emit('update:modelValue', !modelValue)"
  >
    <span class="settings-toggle__track">
      <span class="settings-toggle__thumb" />
    </span>
    <span class="settings-toggle__text">{{ modelValue ? 'Enabled' : 'Disabled' }}</span>
  </button>
</template>

<script setup lang="ts">
interface Props {
  modelValue: boolean;
  disabled?: boolean;
}

withDefaults(defineProps<Props>(), {
  disabled: false,
});

defineEmits<{ 'update:modelValue': [value: boolean] }>();
</script>

<style scoped>
.settings-toggle{display:inline-flex;align-items:center;gap:8px;border:0;background:transparent;color:#e2e8f0;padding:0;cursor:pointer}
.settings-toggle.is-disabled{cursor:not-allowed;opacity:.6}
.settings-toggle__track{width:40px;height:22px;border-radius:999px;background:rgba(71,85,105,.8);padding:2px;display:flex;align-items:center;transition:background-color .2s ease}
.settings-toggle__thumb{width:18px;height:18px;border-radius:999px;background:#e2e8f0;transition:transform .2s ease}
.settings-toggle.is-enabled .settings-toggle__track{background:rgba(34,197,94,.65)}
.settings-toggle.is-enabled .settings-toggle__thumb{transform:translateX(18px)}
.settings-toggle__text{font-size:12px;color:#94a3b8}
</style>
