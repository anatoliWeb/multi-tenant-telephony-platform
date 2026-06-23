<template>
  <article class="billing-metric c-card">
    <p class="billing-metric__label">{{ label }}</p>
    <p class="billing-metric__value">{{ value }}</p>
    <p class="billing-metric__meta">{{ meta }}</p>
  </article>
</template>

<script setup lang="ts">
interface Props {
  label: string;
  value: string;
  meta: string;
}

defineProps<Props>();
</script>

<style scoped>
.billing-metric{margin-top:0;display:grid;gap:6px}
.billing-metric__label{margin:0;color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:.04em}
.billing-metric__value{margin:0;color:#f8fafc;font-size:20px;font-weight:700}
.billing-metric__meta{margin:0;color:#94a3b8;font-size:12px}
</style>

