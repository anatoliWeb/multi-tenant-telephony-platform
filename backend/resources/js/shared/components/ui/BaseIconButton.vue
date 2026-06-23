<template>
  <button
    type="button"
    class="topbar-icon-btn"
    :title="title"
    :aria-label="title"
    :data-active="active"
  >
    <slot />
  </button>
</template>

<script setup lang="ts">
interface Props {
  title: string;
  active?: boolean;
}

withDefaults(defineProps<Props>(), {
  active: false,
});
</script>

<style scoped>
.topbar-icon-btn {
  width: 34px;
  height: 34px;
  border-radius: 999px;
  border: 1px solid rgba(148, 163, 184, 0.35);
  background: rgba(15, 23, 42, 0.7);
  color: #cbd5e1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}

.topbar-icon-btn:focus-visible {
  outline: 2px solid rgba(96, 165, 250, 0.55);
  outline-offset: 1px;
}

.topbar-icon-btn:hover {
  background: rgba(51, 65, 85, 0.85);
  color: #f8fafc;
  border-color: rgba(96, 165, 250, 0.45);
}

.topbar-icon-btn[data-active='true'] {
  color: #dbeafe;
  border-color: rgba(96, 165, 250, 0.6);
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15) inset;
}
</style>
