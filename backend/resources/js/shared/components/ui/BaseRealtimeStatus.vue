<template>
  <div class="realtime-chip" :data-active="active" :title="title">
    <span class="realtime-chip__label">{{ label }}</span>
    <span class="realtime-chip__value">{{ count }}</span>
  </div>
</template>

<script setup lang="ts">
/**
 * Compact realtime status token for shell-level operational metrics.
 *
 * WHY SEPARATE COMPONENT:
 * Isolating status presentation into one primitive ensures backend/frontend
 * online counters can switch from mock values to websocket stream updates
 * without touching topbar layout composition.
 */
interface Props {
  label: string;
  count: number;
  active?: boolean;
  title?: string;
}

withDefaults(defineProps<Props>(), {
  active: false,
  title: '',
});
</script>

<style scoped>
.realtime-chip {
  height: 28px;
  border-radius: 999px;
  border: 1px solid rgba(148, 163, 184, 0.32);
  background: rgba(15, 23, 42, 0.72);
  color: #cbd5e1;
  padding: 0 8px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  line-height: 1;
}

.realtime-chip__label {
  color: #94a3b8;
  font-weight: 700;
}

.realtime-chip__value {
  color: #f8fafc;
  font-weight: 700;
}

.realtime-chip[data-active='true'] {
  border-color: rgba(16, 185, 129, 0.45);
  box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.2) inset;
}
</style>
