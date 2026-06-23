<template>
  <article class="settings-group c-card">
    <header class="settings-group__header">
      <h3 class="settings-group__title">{{ title }}</h3>
      <p class="settings-group__description">{{ description }}</p>
    </header>
    <div class="settings-group__body">
      <slot />
    </div>
  </article>
</template>

<script setup lang="ts">
interface Props {
  title: string;
  description: string;
}

defineProps<Props>();
</script>

<style scoped>
.settings-group{margin-top:0;display:grid;gap:10px}
.settings-group__title{margin:0;color:#f8fafc;font-size:15px}
.settings-group__description{margin:6px 0 0;color:#94a3b8;font-size:12px}
.settings-group__body{display:grid;gap:8px}
</style>
