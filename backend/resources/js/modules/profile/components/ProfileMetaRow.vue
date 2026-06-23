<template>
  <div class="profile-meta-row">
    <span class="profile-meta-row__label">{{ label }}</span>
    <span class="profile-meta-row__value" :title="value">{{ value }}</span>
  </div>
</template>

<script setup lang="ts">
interface Props {
  label: string;
  value: string;
}

defineProps<Props>();
</script>

<style scoped>
.profile-meta-row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px solid rgba(71,85,105,.35)}
.profile-meta-row:last-child{border-bottom:0}
.profile-meta-row__label{font-size:12px;color:#94a3b8}
.profile-meta-row__value{font-size:12px;color:#e2e8f0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
</style>

