<template>
  <div class="settings-row" :class="{ 'is-disabled': !editable }">
    <div class="settings-row__meta">
      <p class="settings-row__label">{{ label }}</p>
      <p class="settings-row__description">{{ description }}</p>
    </div>
    <div class="settings-row__control">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
interface Props {
  label: string;
  description: string;
  editable?: boolean;
}

withDefaults(defineProps<Props>(), {
  editable: true,
});
</script>

<style scoped>
.settings-row{display:grid;grid-template-columns:minmax(220px,1fr) minmax(180px,260px);align-items:center;gap:12px;padding:10px;border:1px solid rgba(71,85,105,.45);border-radius:10px;background:rgba(15,23,42,.45)}
.settings-row.is-disabled{opacity:.7}
.settings-row__label{margin:0;color:#e2e8f0;font-size:13px;font-weight:600}
.settings-row__description{margin:4px 0 0;color:#94a3b8;font-size:11px;line-height:1.35}
.settings-row__control{display:flex;justify-content:flex-end}
@media (max-width:760px){.settings-row{grid-template-columns:1fr}.settings-row__control{justify-content:flex-start}}
</style>
