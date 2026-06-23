<template>
  <article class="base-floating-panel" :class="[`is-${item.size}`]" role="dialog" aria-modal="false">
    <header v-if="item.showHeader" class="base-floating-panel__header">
      <div class="base-floating-panel__titles">
        <h4 v-if="item.title" class="base-floating-panel__title">{{ item.title }}</h4>
        <p v-if="item.subtitle" class="base-floating-panel__subtitle">{{ item.subtitle }}</p>
      </div>
      <button
        v-if="item.closable"
        type="button"
        class="base-floating-panel__close"
        :disabled="item.loading"
        aria-label="Close panel"
        @click="emit('close')"
      >
        ?
      </button>
    </header>

    <section class="base-floating-panel__content">
      <div v-if="item.loading" class="base-floating-panel__state">Loading...</div>
      <div v-else-if="item.empty" class="base-floating-panel__state">{{ item.emptyText }}</div>
      <slot v-else />
    </section>

    <footer v-if="item.showFooter" class="base-floating-panel__footer">
      <slot name="footer" />
    </footer>
  </article>
</template>

<script setup lang="ts">
import type { FloatingPanelItem } from '../types/floating-panel.types';

const props = defineProps<{
  item: FloatingPanelItem;
}>();

const emit = defineEmits<{
  close: [];
}>();

void props;
</script>

<style scoped>
.base-floating-panel{display:grid;grid-template-rows:auto 1fr auto;background:rgba(15,23,42,.98);border:1px solid rgba(71,85,105,.58);border-radius:12px;box-shadow:0 18px 36px rgba(2,6,23,.58);overflow:hidden;min-height:120px}
.base-floating-panel.is-sm{width:260px}
.base-floating-panel.is-md{width:340px}
.base-floating-panel.is-lg{width:440px}
.base-floating-panel__header{display:flex;justify-content:space-between;gap:8px;align-items:flex-start;padding:10px 12px;border-bottom:1px solid rgba(71,85,105,.42)}
.base-floating-panel__title{margin:0;color:#f8fafc;font-size:13px;font-weight:700}
.base-floating-panel__subtitle{margin:4px 0 0;color:#94a3b8;font-size:11px}
.base-floating-panel__close{width:28px;height:28px;border-radius:8px;border:1px solid rgba(71,85,105,.55);background:rgba(30,41,59,.8);color:#cbd5e1;font-size:16px;line-height:1}
.base-floating-panel__close:disabled{opacity:.6;cursor:not-allowed}
.base-floating-panel__content{padding:10px 12px;max-height:min(60vh,520px);overflow:auto}
.base-floating-panel__state{font-size:12px;color:#94a3b8;padding:6px 0}
.base-floating-panel__footer{padding:10px 12px;border-top:1px solid rgba(71,85,105,.42)}
</style>
