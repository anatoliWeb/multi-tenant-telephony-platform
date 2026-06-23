<template>
  <aside v-if="open" class="roles-panel c-card">
    <div class="roles-panel__head">
      <h3 class="roles-panel__title">{{ title }}</h3>
      <button type="button" class="roles-panel__close" @click="$emit('close')">Close</button>
    </div>
    <p class="roles-panel__subtitle">Inline role workflows (view/edit/permissions) will be implemented in this panel architecture.</p>
  </aside>
</template>

<script setup lang="ts">
/**
 * Side-panel placeholder for inline RBAC workflows.
 *
 * WHY:
 * Enterprise admin tools benefit from contextual panels instead of full-page
 * navigations. This keeps the table as source context while editing actions.
 */
interface Props {
  open: boolean;
  title: string;
}

defineProps<Props>();
defineEmits<{ close: [] }>();
</script>

<style scoped>
.roles-panel{margin-top:0;display:grid;gap:8px;border:1px solid rgba(71,85,105,.55)}
.roles-panel__head{display:flex;align-items:center;justify-content:space-between;gap:8px}
.roles-panel__title{margin:0;color:#f8fafc;font-size:15px}
.roles-panel__subtitle{margin:0;color:#94a3b8;font-size:13px}
.roles-panel__close{height:30px;border-radius:8px;border:1px solid rgba(71,85,105,.6);background:rgba(15,23,42,.7);color:#e2e8f0;padding:0 10px;font-size:12px}
</style>
