<template>
  <section class="command-palette" data-command-palette role="dialog" aria-modal="true" aria-label="Command palette">
    <div class="command-palette__search-wrap">
      <input
        ref="searchInput"
        class="command-palette__search"
        :value="query"
        type="text"
        placeholder="Type a command or search..."
        @input="onInput"
        @keydown.down.prevent="emit('move-next')"
        @keydown.up.prevent="emit('move-prev')"
        @keydown.enter.prevent="emit('execute')"
        @keydown.esc.prevent="emit('close')"
      />
    </div>

    <div class="command-palette__results">
      <div v-if="loading" class="command-palette__state">Loading commands...</div>
      <div v-else-if="!groupEntries.length" class="command-palette__state">No commands found.</div>
      <template v-else>
        <section v-for="([group, entries]) in groupEntries" :key="group" class="command-palette__group">
          <h4 class="command-palette__group-title">{{ group }}</h4>
          <button
            v-for="item in entries"
            :key="item.id"
            type="button"
            class="command-palette__item"
            :class="{ 'is-active': item.id === activeItemId }"
            @mouseenter="emit('set-active-by-id', item.id)"
            @click="emit('execute-by-id', item.id)"
          >
            <span class="command-palette__icon">{{ item.icon ?? '•' }}</span>
            <span class="command-palette__text">
              <span class="command-palette__title">{{ item.title }}</span>
              <span v-if="item.subtitle" class="command-palette__subtitle">{{ item.subtitle }}</span>
            </span>
          </button>
        </section>
      </template>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';

import type { CommandPaletteItem } from '../types/command-palette.types';
import { groupItems } from '../utils/command-palette.utils';

/**
 * Base command palette renderer.
 *
 * Presents grouped searchable commands with keyboard-first interactions and a
 * layout ready for future entity search / AI suggestion providers.
 */
const props = defineProps<{
  query: string;
  items: CommandPaletteItem[];
  activeItemId: string;
  loading?: boolean;
}>();

const emit = defineEmits<{
  close: [];
  execute: [];
  'execute-by-id': [id: string];
  'move-next': [];
  'move-prev': [];
  'set-query': [value: string];
  'set-active-by-id': [id: string];
}>();

const searchInput = ref<HTMLInputElement | null>(null);

const groupEntries = computed(() => Object.entries(groupItems(props.items)));

const onInput = (event: Event): void => {
  emit('set-query', (event.target as HTMLInputElement).value);
};

onMounted(async () => {
  await nextTick();
  searchInput.value?.focus();
});
</script>

<style scoped>
.command-palette{width:min(760px,calc(100vw - 24px));max-height:min(72vh,640px);display:grid;grid-template-rows:auto 1fr;border-radius:14px;border:1px solid rgba(71,85,105,.62);background:rgba(15,23,42,.98);box-shadow:0 28px 56px rgba(2,6,23,.62);overflow:hidden}
.command-palette__search-wrap{padding:12px;border-bottom:1px solid rgba(71,85,105,.42)}
.command-palette__search{width:100%;height:40px;border-radius:10px;border:1px solid rgba(71,85,105,.6);background:rgba(15,23,42,.82);color:#e2e8f0;padding:0 12px;font-size:13px}
.command-palette__search:focus{outline:none;border-color:rgba(96,165,250,.55);box-shadow:0 0 0 3px rgba(59,130,246,.14)}
.command-palette__results{overflow:auto;padding:8px;display:grid;gap:8px}
.command-palette__state{padding:14px;color:#94a3b8;font-size:12px}
.command-palette__group{display:grid;gap:4px}
.command-palette__group-title{margin:0;padding:4px 8px;color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:.04em}
.command-palette__item{width:100%;border:0;border-radius:10px;background:transparent;color:#e2e8f0;padding:8px;display:grid;grid-template-columns:20px 1fr;gap:8px;align-items:flex-start;text-align:left}
.command-palette__item:hover,.command-palette__item.is-active{background:rgba(51,65,85,.72)}
.command-palette__icon{display:inline-flex;align-items:center;justify-content:center;color:#93c5fd;font-size:12px;height:20px}
.command-palette__text{display:grid;gap:2px;min-width:0}
.command-palette__title{font-size:13px;color:#f8fafc}
.command-palette__subtitle{font-size:11px;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
</style>

