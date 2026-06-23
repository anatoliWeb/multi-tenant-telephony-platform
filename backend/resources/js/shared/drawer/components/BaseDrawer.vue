<template>
  <article class="base-drawer" :class="[`is-${item.position}`, `is-${item.size}`]" role="dialog" aria-modal="true" :aria-labelledby="headerId">
    <header v-if="item.title || item.subtitle" class="base-drawer__header" :class="{ 'is-sticky': item.stickyHeader }">
      <div class="base-drawer__titles">
        <h3 :id="headerId" class="base-drawer__title">{{ item.title }}</h3>
        <p v-if="item.subtitle" class="base-drawer__subtitle">{{ item.subtitle }}</p>
      </div>
      <button type="button" class="base-drawer__close" :disabled="item.loading" aria-label="Close drawer" @click="emit('close')">?</button>
    </header>

    <section class="base-drawer__content">
      <slot />
    </section>

    <footer v-if="item.actions?.length || $slots.footer" class="base-drawer__footer" :class="{ 'is-sticky': item.stickyFooter }">
      <slot name="footer">
        <button
          v-for="(action, index) in item.actions"
          :key="`${item.id}-${index}`"
          type="button"
          class="base-drawer__action"
          :class="[`is-${action.kind ?? 'secondary'}`]"
          :disabled="item.loading || action.disabled"
          @click="emit('action', index)"
        >
          {{ action.label }}
        </button>
      </slot>
    </footer>
  </article>
</template>

<script setup lang="ts">
import { computed } from 'vue';

import type { DrawerItem } from '../types/drawer.types';

const props = defineProps<{
  item: DrawerItem;
}>();

const emit = defineEmits<{
  close: [];
  action: [index: number];
}>();

const headerId = computed(() => `drawer-title-${props.item.id}`);
</script>

<style scoped>
.base-drawer{display:grid;grid-template-rows:auto 1fr auto;background:rgba(15,23,42,.985);border:1px solid rgba(71,85,105,.55);box-shadow:0 24px 54px rgba(2,6,23,.62);overflow:hidden}
.base-drawer.is-right,.base-drawer.is-left{height:100vh;max-height:100vh}
.base-drawer.is-right{border-left-width:1px;border-right:0;border-top:0;border-bottom:0;border-radius:14px 0 0 14px}
.base-drawer.is-left{border-right-width:1px;border-left:0;border-top:0;border-bottom:0;border-radius:0 14px 14px 0}
.base-drawer.is-bottom{width:100%;max-height:88vh;border-top-left-radius:14px;border-top-right-radius:14px;border-left:0;border-right:0;border-bottom:0}
.base-drawer.is-sm{width:min(100vw,420px)}
.base-drawer.is-md{width:min(100vw,560px)}
.base-drawer.is-lg{width:min(100vw,760px)}
.base-drawer.is-xl{width:min(100vw,960px)}
.base-drawer.is-fullscreen{width:100vw;height:100vh;max-height:100vh;border-radius:0;border:0}
.base-drawer.is-bottom.is-sm,.base-drawer.is-bottom.is-md,.base-drawer.is-bottom.is-lg,.base-drawer.is-bottom.is-xl{width:100%}
.base-drawer__header{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;padding:14px 16px;border-bottom:1px solid rgba(71,85,105,.4);background:rgba(15,23,42,.97)}
.base-drawer__header.is-sticky{position:sticky;top:0;z-index:1}
.base-drawer__title{margin:0;color:#f8fafc;font-size:16px}
.base-drawer__subtitle{margin:5px 0 0;color:#94a3b8;font-size:12px;line-height:1.4}
.base-drawer__close{border:1px solid rgba(71,85,105,.55);background:rgba(30,41,59,.75);color:#cbd5e1;width:30px;height:30px;border-radius:8px;font-size:18px;line-height:1}
.base-drawer__content{padding:14px 16px;overflow:auto}
.base-drawer__footer{display:flex;justify-content:flex-end;gap:8px;padding:12px 16px;border-top:1px solid rgba(71,85,105,.4);background:rgba(15,23,42,.97)}
.base-drawer__footer.is-sticky{position:sticky;bottom:0;z-index:1}
.base-drawer__action{height:34px;padding:0 12px;border-radius:9px;border:1px solid rgba(71,85,105,.55);background:rgba(30,41,59,.82);color:#e2e8f0;font-size:12px}
.base-drawer__action.is-primary{border-color:rgba(59,130,246,.55);background:rgba(59,130,246,.2);color:#bfdbfe}
.base-drawer__action.is-danger{border-color:rgba(239,68,68,.55);background:rgba(239,68,68,.2);color:#fecaca}
.base-drawer__action:disabled,.base-drawer__close:disabled{opacity:.6;cursor:not-allowed}
@media (max-width:760px){.base-drawer.is-right,.base-drawer.is-left{width:min(100vw,100%);border-radius:0}}
</style>
